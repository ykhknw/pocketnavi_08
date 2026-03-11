<?php

// 必要なファイルを読み込み
require_once __DIR__ . '/Config.php';

/**
 * データベース接続管理クラス
 */
class Database {
    private static $connections = [];
    private static $defaultConnection = 'mysql';
    
    /**
     * データベース接続を取得
     */
    public static function getConnection($connection = null) {
        $connection = $connection ?: self::$defaultConnection;
        
        if (!isset(self::$connections[$connection])) {
            self::$connections[$connection] = self::createConnection($connection);
        }
        
        return self::$connections[$connection];
    }
    
    /**
     * データベース接続を作成
     */
    private static function createConnection($connection) {
        $config = Config::getDatabaseConfig($connection);
        
        if (empty($config)) {
            throw new Exception("Database configuration not found for connection: {$connection}");
        }
        
        $dsn = self::buildDsn($config);
        
        try {
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options'] ?? []
            );
            
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * DSNを構築
     */
    private static function buildDsn($config) {
        $driver = $config['driver'];
        $host = $config['host'];
        $port = $config['port'] ?? null;
        $database = $config['database'];
        $charset = $config['charset'] ?? null;
        
        $dsn = "{$driver}:host={$host}";
        
        if ($port) {
            $dsn .= ";port={$port}";
        }
        
        $dsn .= ";dbname={$database}";
        
        if ($charset) {
            $dsn .= ";charset={$charset}";
        }
        
        return $dsn;
    }
    
    /**
     * 接続を閉じる
     */
    public static function closeConnection($connection = null) {
        if ($connection) {
            unset(self::$connections[$connection]);
        } else {
            self::$connections = [];
        }
    }
    
    /**
     * 接続をテスト
     */
    public static function testConnection($connection = null) {
        try {
            $pdo = self::getConnection($connection);
            $pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 接続情報を取得
     */
    public static function getConnectionInfo($connection = null) {
        $connection = $connection ?: self::$defaultConnection;
        $config = Config::getDatabaseConfig($connection);
        
        return [
            'driver' => $config['driver'] ?? 'unknown',
            'host' => $config['host'] ?? 'unknown',
            'port' => $config['port'] ?? 'unknown',
            'database' => $config['database'] ?? 'unknown',
            'username' => $config['username'] ?? 'unknown',
            'charset' => $config['charset'] ?? 'unknown',
        ];
    }
}

/**
 * 後方互換性のためのgetDB関数
 */
function getDB($connection = null) {
    return Database::getConnection($connection);
}
