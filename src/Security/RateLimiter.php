<?php
/**
 * レート制限管理クラス
 * API呼び出し制限、IP別制限、機能別制限を管理
 */
class RateLimiter {
    private $redis;
    private $config;
    private $fallbackStorage = [];
    
    public function __construct() {
        $this->loadConfig();
        $this->initializeRedis();
    }
    
    /**
     * 設定ファイルの読み込み
     */
    private function loadConfig() {
        $configFile = __DIR__ . '/../../config/rate_limit_config.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            // デフォルト設定
            $this->config = [
                'api' => [
                    'search' => [
                        'limit' => 30,
                        'window' => 60,
                        'block_duration' => 300,
                        'burst_limit' => 10,
                        'burst_window' => 10
                    ],
                    'general' => [
                        'limit' => 60,
                        'window' => 60,
                        'block_duration' => 300,
                        'burst_limit' => 20,
                        'burst_window' => 10
                    ],
                    'admin' => [
                        'limit' => 20,
                        'window' => 60,
                        'block_duration' => 600,
                        'burst_limit' => 5,
                        'burst_window' => 10
                    ]
                ],
                'login' => [
                    'max_attempts' => 5,
                    'lockout_duration' => 900,
                    'admin_notification' => true
                ]
            ];
        }
    }
    
    /**
     * Redis接続の初期化
     */
    private function initializeRedis() {
        try {
            if (class_exists('Redis')) {
                $this->redis = new Redis();
                $this->redis->connect('127.0.0.1', 6379);
                $this->redis->select(1); // データベース1を使用
            }
        } catch (Exception $e) {
            // Redisが利用できない場合はフォールバック
            $this->redis = null;
            error_log('Redis connection failed, using fallback storage: ' . $e->getMessage());
        }
    }
    
    /**
     * レート制限のチェック
     */
    public function checkLimit($type, $identifier, $customLimit = null, $customWindow = null) {
        $config = $this->getConfig($type);
        if (!$config) {
            return true; // 設定がない場合は制限なし
        }
        
        $limit = $customLimit ?? $config['limit'];
        $window = $customWindow ?? $config['window'];
        
        // バースト制限のチェック
        if (isset($config['burst_limit']) && isset($config['burst_window'])) {
            if (!$this->checkBurstLimit($type, $identifier, $config['burst_limit'], $config['burst_window'])) {
                return false;
            }
        }
        
        // 通常のレート制限チェック
        $key = "rate_limit:{$type}:{$identifier}";
        $current = $this->getCurrentCount($key, $window);
        
        if ($current >= $limit) {
            // 制限超過時はブロック時間を設定
            $this->setBlock($type, $identifier, $config['block_duration']);
            return false;
        }
        
        // カウントを増加
        $this->incrementCount($key, $window);
        return true;
    }
    
    /**
     * バースト制限のチェック
     */
    private function checkBurstLimit($type, $identifier, $burstLimit, $burstWindow) {
        $key = "burst_limit:{$type}:{$identifier}";
        $current = $this->getCurrentCount($key, $burstWindow);
        
        if ($current >= $burstLimit) {
            return false;
        }
        
        $this->incrementCount($key, $burstWindow);
        return true;
    }
    
    /**
     * 現在のカウント数を取得
     */
    private function getCurrentCount($key, $window) {
        if ($this->redis) {
            try {
                $now = time();
                $start = $now - $window;
                
                // スライディングウィンドウでカウント
                $this->redis->zRemRangeByScore($key, 0, $start);
                return $this->redis->zCard($key);
            } catch (Exception $e) {
                error_log('Redis error: ' . $e->getMessage());
                return $this->getFallbackCount($key, $window);
            }
        }
        
        return $this->getFallbackCount($key, $window);
    }
    
    /**
     * カウントを増加
     */
    private function incrementCount($key, $window) {
        if ($this->redis) {
            try {
                $now = time();
                $this->redis->zAdd($key, $now, $now . ':' . uniqid());
                $this->redis->expire($key, $window);
            } catch (Exception $e) {
                error_log('Redis error: ' . $e->getMessage());
                $this->incrementFallbackCount($key, $window);
            }
        } else {
            $this->incrementFallbackCount($key, $window);
        }
    }
    
    /**
     * ブロック状態の設定
     */
    private function setBlock($type, $identifier, $duration) {
        $key = "block:{$type}:{$identifier}";
        $expireTime = time() + $duration;
        
        if ($this->redis) {
            try {
                $this->redis->setex($key, $duration, $expireTime);
            } catch (Exception $e) {
                error_log('Redis error: ' . $e->getMessage());
                $this->setFallbackBlock($key, $expireTime);
            }
        } else {
            $this->setFallbackBlock($key, $expireTime);
        }
    }
    
    /**
     * ブロック状態のチェック
     */
    public function isBlocked($type, $identifier) {
        $key = "block:{$type}:{$identifier}";
        
        if ($this->redis) {
            try {
                $blockTime = $this->redis->get($key);
                if ($blockTime && $blockTime > time()) {
                    return $blockTime;
                }
                return false;
            } catch (Exception $e) {
                error_log('Redis error: ' . $e->getMessage());
                return $this->getFallbackBlock($key);
            }
        }
        
        return $this->getFallbackBlock($key);
    }
    
    /**
     * 設定の取得
     */
    private function getConfig($type) {
        if (isset($this->config['api'][$type])) {
            return $this->config['api'][$type];
        }
        return null;
    }
    
    /**
     * フォールバックストレージのカウント取得
     */
    private function getFallbackCount($key, $window) {
        if (!isset($this->fallbackStorage[$key])) {
            return 0;
        }
        
        $now = time();
        $start = $now - $window;
        $count = 0;
        
        // 古いエントリを削除しながらカウント
        $cleanedEntries = [];
        foreach ($this->fallbackStorage[$key] as $timestamp => $value) {
            // タイムスタンプ部分を抽出
            $entryTime = (int)explode('_', $timestamp)[0];
            if ($entryTime >= $start) {
                $count++;
                $cleanedEntries[$timestamp] = $value;
            }
        }
        
        // クリーンアップされたエントリで更新
        $this->fallbackStorage[$key] = $cleanedEntries;
        
        // デバッグログ
        error_log("getFallbackCount: key={$key}, window={$window}, count={$count}, entries=" . count($cleanedEntries));
        
        return $count;
    }
    
    /**
     * フォールバックストレージのカウント増加
     */
    private function incrementFallbackCount($key, $window) {
        if (!isset($this->fallbackStorage[$key])) {
            $this->fallbackStorage[$key] = [];
        }
        
        $now = time();
        // ユニークなキーを使用して重複を防ぐ
        $uniqueKey = $now . '_' . uniqid();
        $this->fallbackStorage[$key][$uniqueKey] = true;
        
        // 古いエントリを削除
        $start = $now - $window;
        $cleanedEntries = [];
        foreach ($this->fallbackStorage[$key] as $timestamp => $value) {
            // タイムスタンプ部分を抽出
            $entryTime = (int)explode('_', $timestamp)[0];
            if ($entryTime >= $start) {
                $cleanedEntries[$timestamp] = $value;
            }
        }
        $this->fallbackStorage[$key] = $cleanedEntries;
        
        // デバッグログ
        error_log("incrementFallbackCount: key={$key}, window={$window}, entries=" . count($cleanedEntries));
    }
    
    /**
     * フォールバックストレージのブロック設定
     */
    private function setFallbackBlock($key, $expireTime) {
        $this->fallbackStorage[$key] = $expireTime;
    }
    
    /**
     * フォールバックストレージのブロック取得
     */
    private function getFallbackBlock($key) {
        if (isset($this->fallbackStorage[$key])) {
            $blockTime = $this->fallbackStorage[$key];
            if ($blockTime > time()) {
                return $blockTime;
            }
            unset($this->fallbackStorage[$key]);
        }
        return false;
    }
    
    /**
     * レート制限情報の取得
     */
    public function getRateLimitInfo($type, $identifier) {
        $config = $this->getConfig($type);
        if (!$config) {
            return null;
        }
        
        $key = "rate_limit:{$type}:{$identifier}";
        $current = $this->getCurrentCount($key, $config['window']);
        $blockTime = $this->isBlocked($type, $identifier);
        
        return [
            'type' => $type,
            'identifier' => $identifier,
            'current' => $current,
            'limit' => $config['limit'],
            'window' => $config['window'],
            'remaining' => max(0, $config['limit'] - $current),
            'reset_time' => time() + $config['window'],
            'blocked' => $blockTime !== false,
            'block_until' => $blockTime
        ];
    }
    
    /**
     * ブロックの解除
     */
    public function unblock($type, $identifier) {
        $key = "block:{$type}:{$identifier}";
        
        if ($this->redis) {
            try {
                $this->redis->del($key);
            } catch (Exception $e) {
                error_log('Redis error: ' . $e->getMessage());
            }
        }
        
        unset($this->fallbackStorage[$key]);
    }
    
    /**
     * 現在のカウント数を取得（public版）
     */
    public function getCurrentCountPublic($key, $window) {
        return $this->getCurrentCount($key, $window);
    }
    
    /**
     * デバッグ情報の取得
     */
    public function getDebugInfo($type, $identifier) {
        $key = "rate_limit:{$type}:{$identifier}";
        $config = $this->getConfig($type);
        
        $debug = [
            'type' => $type,
            'identifier' => $identifier,
            'key' => $key,
            'config' => $config,
            'redis_available' => $this->redis !== null,
            'fallback_storage' => isset($this->fallbackStorage[$key]) ? $this->fallbackStorage[$key] : [],
            'current_count' => $this->getCurrentCount($key, $config['window'] ?? 60),
            'blocked' => $this->isBlocked($type, $identifier)
        ];
        
        return $debug;
    }
    
    /**
     * カウントを増加（public版）
     */
    public function incrementCountPublic($key, $window) {
        $this->incrementCount($key, $window);
    }
    
    /**
     * ブロックの設定（public版）
     */
    public function setBlockPublic($type, $identifier, $duration) {
        $this->setBlock($type, $identifier, $duration);
    }
    
    /**
     * 統計情報の取得
     */
    public function getStats() {
        $stats = [
            'redis_available' => $this->redis !== null,
            'fallback_active' => $this->redis === null,
            'config_loaded' => !empty($this->config),
            'active_blocks' => 0,
            'total_requests' => 0
        ];
        
        // フォールバックストレージの統計
        if ($this->redis === null) {
            $stats['active_blocks'] = count(array_filter($this->fallbackStorage, function($value) {
                return is_numeric($value) && $value > time();
            }));
            $stats['total_requests'] = count($this->fallbackStorage);
        }
        
        return $stats;
    }
}
