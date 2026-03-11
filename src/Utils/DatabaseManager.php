<?php

/**
 * データベース接続管理クラス
 */
class DatabaseManager {
    private static $instance = null;
    private $connections = [];
    private $maxConnections = 10;
    private $connectionTimeout = 30;
    
    private function __construct() {
        // プライベートコンストラクタ（シングルトン）
    }
    
    /**
     * シングルトンインスタンスを取得
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * データベース接続を取得
     */
    public function getConnection($connectionName = 'default') {
        // 既存の接続をチェック
        if (isset($this->connections[$connectionName])) {
            $connection = $this->connections[$connectionName];
            
            // 接続が有効かチェック
            if ($this->isConnectionValid($connection)) {
                return $connection;
            } else {
                // 無効な接続を削除
                unset($this->connections[$connectionName]);
            }
        }
        
        // 新しい接続を作成
        if (count($this->connections) >= $this->maxConnections) {
            // 最も古い接続を削除
            $this->closeOldestConnection();
        }
        
        $connection = $this->createConnection($connectionName);
        if ($connection) {
            $this->connections[$connectionName] = $connection;
        }
        
        return $connection;
    }
    
    /**
     * 新しいデータベース接続を作成
     */
    private function createConnection($connectionName = 'default') {
        try {
            $config = $this->getDatabaseConfig($connectionName);
            
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false, // 接続プールで管理するため
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $connection = new PDO($dsn, $config['username'], $config['password'], $options);
            
            // 接続時刻を記録
            $connection->created_at = time();
            
            return $connection;
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * データベース設定を取得
     */
    private function getDatabaseConfig($connectionName = 'default') {
        // 既存の設定ファイルから取得
        require_once __DIR__ . '/../../config/environment.php';
        $config = getDatabaseConfig();
        
        return [
            'host' => $config['host'],
            'dbname' => $config['db_name'],
            'username' => $config['username'],
            'password' => $config['password']
        ];
    }
    
    /**
     * 接続が有効かチェック
     */
    private function isConnectionValid($connection) {
        try {
            // 簡単なクエリで接続をテスト
            $connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * 最も古い接続を閉じる
     */
    private function closeOldestConnection() {
        if (empty($this->connections)) {
            return;
        }
        
        $oldestConnection = null;
        $oldestTime = PHP_INT_MAX;
        $oldestKey = null;
        
        foreach ($this->connections as $key => $connection) {
            if (isset($connection->created_at) && $connection->created_at < $oldestTime) {
                $oldestTime = $connection->created_at;
                $oldestKey = $key;
            }
        }
        
        if ($oldestKey !== null) {
            unset($this->connections[$oldestKey]);
        }
    }
    
    /**
     * 接続プールの統計情報を取得
     */
    public function getPoolStats() {
        return [
            'active_connections' => count($this->connections),
            'max_connections' => $this->maxConnections,
            'connection_names' => array_keys($this->connections)
        ];
    }
    
    /**
     * すべての接続を閉じる
     */
    public function closeAllConnections() {
        foreach ($this->connections as $connection) {
            $connection = null;
        }
        $this->connections = [];
    }
    
    /**
     * 接続プールをクリーンアップ
     */
    public function cleanup() {
        $now = time();
        
        foreach ($this->connections as $key => $connection) {
            if (isset($connection->created_at) && 
                ($now - $connection->created_at) > $this->connectionTimeout) {
                
                if (!$this->isConnectionValid($connection)) {
                    unset($this->connections[$key]);
                }
            }
        }
    }
    
    /**
     * デストラクタ
     */
    public function __destruct() {
        $this->closeAllConnections();
    }
}
