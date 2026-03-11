<?php

require_once __DIR__ . '/../Exceptions/AppException.php';
require_once __DIR__ . '/Config.php';

/**
 * 統一されたデータベース接続管理クラス
 * シングルトンパターンで接続を管理
 */
class DatabaseConnection {
    private static $instance = null;
    private $connection = null;
    private $config = null;
    
    /**
     * プライベートコンストラクタ（シングルトンパターン）
     */
    private function __construct() {
        $this->loadConfig();
        $this->connect();
    }
    
    /**
     * インスタンスの取得（シングルトンパターン）
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * データベース接続の取得
     */
    public function getConnection() {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * 設定の読み込み
     */
    private function loadConfig() {
        // 環境変数から設定を読み込み
        $this->config = [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'dbname' => getenv('DB_NAME') ?: '_shinkenchiku_12',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false, // 接続プールは後で実装
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        ];
    }
    
    /**
     * データベース接続の確立
     */
    private function connect() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['dbname'],
                $this->config['charset']
            );
            
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
            
            // 接続成功をログに記録（開発環境時のみ、本番環境では出力しない）
            if (!Config::isProduction()) {
                error_log("Database connection established successfully");
            }
            
        } catch (PDOException $e) {
            // エラーログに記録
            error_log("Database connection failed: " . $e->getMessage());
            
            // カスタム例外を投げる
            throw new DatabaseException(
                "データベース接続エラーが発生しました。",
                0,
                $e,
                [
                    'host' => $this->config['host'],
                    'dbname' => $this->config['dbname'],
                    'username' => $this->config['username']
                ]
            );
        }
    }
    
    /**
     * 接続のテスト
     */
    public function testConnection() {
        try {
            $stmt = $this->connection->query('SELECT 1');
            return $stmt !== false;
        } catch (PDOException $e) {
            error_log("Database connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 接続の再確立
     */
    public function reconnect() {
        $this->connection = null;
        $this->connect();
    }
    
    /**
     * 設定情報の取得（デバッグ用）
     */
    public function getConfig() {
        return [
            'host' => $this->config['host'],
            'dbname' => $this->config['dbname'],
            'username' => $this->config['username'],
            'charset' => $this->config['charset']
        ];
    }
    
    /**
     * 接続状態の取得
     */
    public function isConnected() {
        return $this->connection !== null && $this->testConnection();
    }
    
    /**
     * クローンを防ぐ（シングルトンパターン）
     */
    private function __clone() {}
    
    /**
     * アンシリアライズを防ぐ（シングルトンパターン）
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
