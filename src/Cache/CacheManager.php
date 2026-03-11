<?php

/**
 * 高度なキャッシュ管理システム
 * メモリキャッシュ、ファイルキャッシュ、データベースキャッシュを統合
 */
class CacheManager {
    
    private static $instance = null;
    private $memoryCache = [];
    private $cacheConfig = [];
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];
    
    private function __construct() {
        $this->cacheConfig = [
            'memory' => [
                'enabled' => true,
                'max_size' => 1000,
                'ttl' => 3600 // 1時間
            ],
            'file' => [
                'enabled' => true,
                'path' => __DIR__ . '/../../cache/',
                'ttl' => 7200 // 2時間
            ]
        ];
        
        $this->ensureCacheDirectory();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * キャッシュから値を取得
     */
    public function get($key, $default = null) {
        $key = $this->normalizeKey($key);
        
        // メモリキャッシュから取得
        if ($this->cacheConfig['memory']['enabled']) {
            $value = $this->getFromMemory($key);
            if ($value !== null) {
                $this->stats['hits']++;
                return $value;
            }
        }
        
        // ファイルキャッシュから取得
        if ($this->cacheConfig['file']['enabled']) {
            $value = $this->getFromFile($key);
            if ($value !== null) {
                // メモリキャッシュにも保存
                if ($this->cacheConfig['memory']['enabled']) {
                    $this->setToMemory($key, $value);
                }
                $this->stats['hits']++;
                return $value;
            }
        }
        
        $this->stats['misses']++;
        return $default;
    }
    
    /**
     * キャッシュに値を保存
     */
    public function set($key, $value, $ttl = null) {
        $key = $this->normalizeKey($key);
        $ttl = $ttl ?: $this->cacheConfig['memory']['ttl'];
        
        $success = true;
        
        // メモリキャッシュに保存
        if ($this->cacheConfig['memory']['enabled']) {
            $success = $this->setToMemory($key, $value, $ttl) && $success;
        }
        
        // ファイルキャッシュに保存
        if ($this->cacheConfig['file']['enabled']) {
            $success = $this->setToFile($key, $value, $ttl) && $success;
        }
        
        if ($success) {
            $this->stats['sets']++;
        }
        
        return $success;
    }
    
    /**
     * キャッシュから値を削除
     */
    public function delete($key) {
        $key = $this->normalizeKey($key);
        
        $success = true;
        
        // メモリキャッシュから削除
        if ($this->cacheConfig['memory']['enabled']) {
            $success = $this->deleteFromMemory($key) && $success;
        }
        
        // ファイルキャッシュから削除
        if ($this->cacheConfig['file']['enabled']) {
            $success = $this->deleteFromFile($key) && $success;
        }
        
        if ($success) {
            $this->stats['deletes']++;
        }
        
        return $success;
    }
    
    /**
     * キャッシュの統計情報を取得
     */
    public function getStats() {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0;
        
        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'sets' => $this->stats['sets'],
            'deletes' => $this->stats['deletes'],
            'hit_rate' => round($hitRate, 2),
            'memory_size' => count($this->memoryCache),
            'memory_limit' => $this->cacheConfig['memory']['max_size']
        ];
    }
    
    /**
     * メモリキャッシュから取得
     */
    private function getFromMemory($key) {
        if (!isset($this->memoryCache[$key])) {
            return null;
        }
        
        $item = $this->memoryCache[$key];
        
        // 有効期限チェック
        if ($item['expires'] > 0 && time() > $item['expires']) {
            unset($this->memoryCache[$key]);
            return null;
        }
        
        return $item['value'];
    }
    
    /**
     * メモリキャッシュに保存
     */
    private function setToMemory($key, $value, $ttl = null) {
        $ttl = $ttl ?: $this->cacheConfig['memory']['ttl'];
        $expires = $ttl > 0 ? time() + $ttl : 0;
        
        // メモリ制限チェック
        if (count($this->memoryCache) >= $this->cacheConfig['memory']['max_size']) {
            $this->evictOldestFromMemory();
        }
        
        $this->memoryCache[$key] = [
            'value' => $value,
            'expires' => $expires,
            'created' => time()
        ];
        
        return true;
    }
    
    /**
     * メモリキャッシュから削除
     */
    private function deleteFromMemory($key) {
        if (isset($this->memoryCache[$key])) {
            unset($this->memoryCache[$key]);
            return true;
        }
        return false;
    }
    
    /**
     * ファイルキャッシュから取得
     */
    private function getFromFile($key) {
        $filePath = $this->getFilePath($key);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $data = include $filePath;
        
        if (!is_array($data) || !isset($data['value']) || !isset($data['expires'])) {
            return null;
        }
        
        // 有効期限チェック
        if ($data['expires'] > 0 && time() > $data['expires']) {
            unlink($filePath);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * ファイルキャッシュに保存
     */
    private function setToFile($key, $value, $ttl = null) {
        $ttl = $ttl ?: $this->cacheConfig['file']['ttl'];
        $expires = $ttl > 0 ? time() + $ttl : 0;
        
        $data = [
            'value' => $value,
            'expires' => $expires,
            'created' => time()
        ];
        
        $filePath = $this->getFilePath($key);
        $content = "<?php\nreturn " . var_export($data, true) . ";\n";
        
        return file_put_contents($filePath, $content, LOCK_EX) !== false;
    }
    
    /**
     * ファイルキャッシュから削除
     */
    private function deleteFromFile($key) {
        $filePath = $this->getFilePath($key);
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return true;
    }
    
    /**
     * ファイルパスを取得
     */
    private function getFilePath($key) {
        $hash = md5($key);
        $dir = $this->cacheConfig['file']['path'] . substr($hash, 0, 2);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return $dir . '/' . $hash . '.php';
    }
    
    /**
     * キャッシュキーを正規化
     */
    private function normalizeKey($key) {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $key);
    }
    
    /**
     * 古いアイテムをメモリから削除
     */
    private function evictOldestFromMemory() {
        $oldestKey = null;
        $oldestTime = time();
        
        foreach ($this->memoryCache as $key => $item) {
            if ($item['created'] < $oldestTime) {
                $oldestTime = $item['created'];
                $oldestKey = $key;
            }
        }
        
        if ($oldestKey) {
            unset($this->memoryCache[$oldestKey]);
        }
    }
    
    /**
     * キャッシュディレクトリの作成
     */
    private function ensureCacheDirectory() {
        $cacheDir = $this->cacheConfig['file']['path'];
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }
}
