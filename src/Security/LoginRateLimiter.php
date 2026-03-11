<?php
/**
 * ログイン専用レート制限クラス
 * ログイン試行制限、アカウントロック機能を管理
 */
class LoginRateLimiter {
    private $rateLimiter;
    private $config;
    private $db;
    
    public function __construct() {
        $this->rateLimiter = new RateLimiter();
        $this->loadConfig();
        $this->initializeDatabase();
    }
    
    /**
     * 設定の読み込み
     */
    private function loadConfig() {
        $configFile = __DIR__ . '/../../config/rate_limit_config.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $this->config = $config['login'] ?? [];
        } else {
            // デフォルト設定
            $this->config = [
                'max_attempts' => 5,
                'lockout_duration' => 900,
                'admin_notification' => true,
                'reset_attempts_after' => 3600
            ];
        }
    }
    
    /**
     * データベース接続の初期化
     */
    private function initializeDatabase() {
        try {
            $this->db = getDB();
        } catch (Exception $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            $this->db = null;
        }
    }
    
    /**
     * ログイン試行のチェック
     */
    public function checkLoginAttempt($ip, $username = null) {
        // IP別の制限チェック
        if (!$this->checkIPLimit($ip)) {
            $this->logAttempt($ip, $username, 'IP_BLOCKED');
            return [
                'allowed' => false,
                'reason' => 'IP_BLOCKED',
                'message' => 'IP address is temporarily blocked due to too many failed attempts.',
                'retry_after' => $this->getIPBlockTime($ip)
            ];
        }
        
        // ユーザー名別の制限チェック
        if ($username && !$this->checkUsernameLimit($username)) {
            $this->logAttempt($ip, $username, 'USERNAME_BLOCKED');
            return [
                'allowed' => false,
                'reason' => 'USERNAME_BLOCKED',
                'message' => 'Account is temporarily locked due to too many failed attempts.',
                'retry_after' => $this->getUsernameBlockTime($username)
            ];
        }
        
        return [
            'allowed' => true,
            'reason' => 'ALLOWED',
            'message' => 'Login attempt allowed.'
        ];
    }
    
    /**
     * IP別の制限チェック
     */
    private function checkIPLimit($ip) {
        $key = "login_ip:{$ip}";
        $maxAttempts = $this->config['max_attempts'];
        $window = $this->config['reset_attempts_after'];
        
        $attempts = $this->getAttemptCount($key, $window);
        
        if ($attempts >= $maxAttempts) {
            $this->blockIP($ip);
            return false;
        }
        
        return true;
    }
    
    /**
     * ユーザー名別の制限チェック
     */
    private function checkUsernameLimit($username) {
        $key = "login_user:{$username}";
        $maxAttempts = $this->config['max_attempts'];
        $window = $this->config['reset_attempts_after'];
        
        $attempts = $this->getAttemptCount($key, $window);
        
        if ($attempts >= $maxAttempts) {
            $this->blockUsername($username);
            return false;
        }
        
        return true;
    }
    
    /**
     * ログイン試行の記録
     */
    public function recordLoginAttempt($ip, $username, $success = false) {
        if (!$success) {
            // 失敗した場合のみカウント
            $this->incrementAttemptCount("login_ip:{$ip}");
            if ($username) {
                $this->incrementAttemptCount("login_user:{$username}");
            }
            
            $this->logAttempt($ip, $username, 'FAILED');
            
            // 管理者通知
            if ($this->config['admin_notification']) {
                $this->notifyAdmin($ip, $username);
            }
        } else {
            // 成功した場合は試行回数をリセット
            $this->resetAttempts("login_ip:{$ip}");
            if ($username) {
                $this->resetAttempts("login_user:{$username}");
            }
            
            $this->logAttempt($ip, $username, 'SUCCESS');
        }
    }
    
    /**
     * 試行回数の取得
     */
    private function getAttemptCount($key, $window) {
        if ($this->rateLimiter->redis) {
            try {
                $now = time();
                $start = $now - $window;
                
                $this->rateLimiter->redis->zRemRangeByScore($key, 0, $start);
                return $this->rateLimiter->redis->zCard($key);
            } catch (Exception $e) {
                error_log('Redis error: ' . $e->getMessage());
                return $this->getFallbackAttemptCount($key, $window);
            }
        }
        
        return $this->getFallbackAttemptCount($key, $window);
    }
    
    /**
     * 試行回数の増加
     */
    private function incrementAttemptCount($key) {
        if ($this->rateLimiter->redis) {
            try {
                $now = time();
                $this->rateLimiter->redis->zAdd($key, $now, $now . ':' . uniqid());
                $this->rateLimiter->redis->expire($key, $this->config['reset_attempts_after']);
            } catch (Exception $e) {
                error_log('Redis error: ' . $e->getMessage());
                $this->incrementFallbackAttemptCount($key);
            }
        } else {
            $this->incrementFallbackAttemptCount($key);
        }
    }
    
    /**
     * 試行回数のリセット
     */
    private function resetAttempts($key) {
        if ($this->rateLimiter->redis) {
            try {
                $this->rateLimiter->redis->del($key);
            } catch (Exception $e) {
                error_log('Redis error: ' . $e->getMessage());
            }
        }
        
        // フォールバックストレージからも削除
        unset($this->rateLimiter->fallbackStorage[$key]);
    }
    
    /**
     * IPのブロック
     */
    private function blockIP($ip) {
        $key = "block_ip:{$ip}";
        $duration = $this->config['lockout_duration'];
        $expireTime = time() + $duration;
        
        if ($this->rateLimiter->redis) {
            try {
                $this->rateLimiter->redis->setex($key, $duration, $expireTime);
            } catch (Exception $e) {
                error_log('Redis error: ' . $e->getMessage());
                $this->rateLimiter->setFallbackBlock($key, $expireTime);
            }
        } else {
            $this->rateLimiter->setFallbackBlock($key, $expireTime);
        }
    }
    
    /**
     * ユーザー名のブロック
     */
    private function blockUsername($username) {
        $key = "block_user:{$username}";
        $duration = $this->config['lockout_duration'];
        $expireTime = time() + $duration;
        
        if ($this->rateLimiter->redis) {
            try {
                $this->rateLimiter->redis->setex($key, $duration, $expireTime);
            } catch (Exception $e) {
                error_log('Redis error: ' . $e->getMessage());
                $this->rateLimiter->setFallbackBlock($key, $expireTime);
            }
        } else {
            $this->rateLimiter->setFallbackBlock($key, $expireTime);
        }
    }
    
    /**
     * IPブロック時間の取得
     */
    private function getIPBlockTime($ip) {
        $key = "block_ip:{$ip}";
        return $this->rateLimiter->getFallbackBlock($key);
    }
    
    /**
     * ユーザー名ブロック時間の取得
     */
    private function getUsernameBlockTime($username) {
        $key = "block_user:{$username}";
        return $this->rateLimiter->getFallbackBlock($key);
    }
    
    /**
     * フォールバックストレージの試行回数取得
     */
    private function getFallbackAttemptCount($key, $window) {
        if (!isset($this->rateLimiter->fallbackStorage[$key])) {
            return 0;
        }
        
        $now = time();
        $start = $now - $window;
        $count = 0;
        
        foreach ($this->rateLimiter->fallbackStorage[$key] as $timestamp => $value) {
            if ($timestamp >= $start) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * フォールバックストレージの試行回数増加
     */
    private function incrementFallbackAttemptCount($key) {
        if (!isset($this->rateLimiter->fallbackStorage[$key])) {
            $this->rateLimiter->fallbackStorage[$key] = [];
        }
        
        $now = time();
        $this->rateLimiter->fallbackStorage[$key][$now] = true;
        
        // 古いエントリを削除
        $start = $now - $this->config['reset_attempts_after'];
        foreach ($this->rateLimiter->fallbackStorage[$key] as $timestamp => $value) {
            if ($timestamp < $start) {
                unset($this->rateLimiter->fallbackStorage[$key][$timestamp]);
            }
        }
    }
    
    /**
     * ログイン試行のログ記録
     */
    private function logAttempt($ip, $username, $status) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'username' => $username,
            'status' => $status,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ];
        
        // ファイルログ
        $logFile = __DIR__ . '/../../logs/login_attempts.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logLine = json_encode($logData) . "\n";
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // データベースログ（オプション）
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO login_attempts 
                    (ip_address, username, status, user_agent, referer, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $ip,
                    $username,
                    $status,
                    $logData['user_agent'],
                    $logData['referer']
                ]);
            } catch (Exception $e) {
                error_log('Database log error: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * 管理者通知
     */
    private function notifyAdmin($ip, $username) {
        $subject = 'Login Security Alert - ' . $_SERVER['HTTP_HOST'];
        $message = "
        Security Alert: Multiple failed login attempts detected
        
        IP Address: {$ip}
        Username: " . ($username ?: 'Unknown') . "
        Time: " . date('Y-m-d H:i:s') . "
        User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "
        
        Please review the security logs for more details.
        ";
        
        $adminEmail = $this->config['admin_email'] ?? 'admin@kenchikuka.com';
        
        // メール送信（簡易版）
        if (function_exists('mail')) {
            mail($adminEmail, $subject, $message);
        }
        
        // ログに記録
        error_log("Security Alert: Failed login attempts from {$ip} for user {$username}");
    }
    
    /**
     * ブロックの解除
     */
    public function unblockIP($ip) {
        $key = "block_ip:{$ip}";
        $this->rateLimiter->unblock('ip', $ip);
        $this->resetAttempts("login_ip:{$ip}");
    }
    
    /**
     * ユーザー名のブロック解除
     */
    public function unblockUsername($username) {
        $key = "block_user:{$username}";
        $this->rateLimiter->unblock('user', $username);
        $this->resetAttempts("login_user:{$username}");
    }
    
    /**
     * ログイン統計の取得
     */
    public function getLoginStats($hours = 24) {
        $stats = [
            'total_attempts' => 0,
            'failed_attempts' => 0,
            'successful_attempts' => 0,
            'blocked_ips' => 0,
            'blocked_users' => 0,
            'top_attack_ips' => [],
            'top_attack_users' => []
        ];
        
        // ログファイルから統計を取得
        $logFile = __DIR__ . '/../../logs/login_attempts.log';
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES);
            $cutoff = time() - ($hours * 3600);
            
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if (!$data) continue;
                
                $timestamp = strtotime($data['timestamp']);
                if ($timestamp < $cutoff) continue;
                
                $stats['total_attempts']++;
                
                if ($data['status'] === 'SUCCESS') {
                    $stats['successful_attempts']++;
                } else {
                    $stats['failed_attempts']++;
                    
                    // 攻撃元IPの統計
                    $ip = $data['ip'];
                    if (!isset($stats['top_attack_ips'][$ip])) {
                        $stats['top_attack_ips'][$ip] = 0;
                    }
                    $stats['top_attack_ips'][$ip]++;
                    
                    // 攻撃対象ユーザーの統計
                    if ($data['username']) {
                        $username = $data['username'];
                        if (!isset($stats['top_attack_users'][$username])) {
                            $stats['top_attack_users'][$username] = 0;
                        }
                        $stats['top_attack_users'][$username]++;
                    }
                }
            }
            
            // ソート
            arsort($stats['top_attack_ips']);
            arsort($stats['top_attack_users']);
            
            // 上位10件のみ
            $stats['top_attack_ips'] = array_slice($stats['top_attack_ips'], 0, 10, true);
            $stats['top_attack_users'] = array_slice($stats['top_attack_users'], 0, 10, true);
        }
        
        return $stats;
    }
}
