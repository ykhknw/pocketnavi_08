<?php

/**
 * 本番環境設定管理クラス
 */
class ProductionConfig {
    
    private static $instance = null;
    private $config = [];
    private $loaded = false;
    
    private function __construct() {
        $this->loadConfig();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 設定の読み込み
     */
    private function loadConfig() {
        if ($this->loaded) {
            return;
        }
        
        // EnvironmentLoaderを使用して設定を読み込み
        require_once __DIR__ . '/EnvironmentLoader.php';
        $envLoader = new EnvironmentLoader();
        $envConfig = $envLoader->load();
        
        // 本番環境設定の読み込み
        $this->config = [
            'APP_NAME' => $envConfig['APP_NAME'] ?? 'PocketNavi',
            'APP_ENV' => $envConfig['APP_ENV'] ?? 'production',
            'APP_DEBUG' => filter_var($envConfig['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'APP_URL' => $envConfig['APP_URL'] ?? 'https://pocketnavi.example.com',
            'APP_TIMEZONE' => $envConfig['APP_TIMEZONE'] ?? 'Asia/Tokyo',
            'APP_LOCALE' => $envConfig['APP_LOCALE'] ?? 'ja',
            'APP_FALLBACK_LOCALE' => $envConfig['APP_FALLBACK_LOCALE'] ?? 'ja',
            
            // データベース設定
            'DB_HOST' => $envConfig['DB_HOST'] ?? 'localhost',
            'DB_NAME' => $envConfig['DB_NAME'] ?? '_shinkenchiku_02',
            'DB_USERNAME' => $envConfig['DB_USERNAME'] ?? 'root',
            'DB_PASSWORD' => $envConfig['DB_PASSWORD'] ?? '',
            'DB_PORT' => $envConfig['DB_PORT'] ?? '3306',
            'DB_CHARSET' => $envConfig['DB_CHARSET'] ?? 'utf8mb4',
            
            // セッション設定
            'SESSION_LIFETIME' => $envConfig['SESSION_LIFETIME'] ?? '7200',
            'SESSION_SECURE' => filter_var($envConfig['SESSION_SECURE'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'SESSION_HTTP_ONLY' => filter_var($envConfig['SESSION_HTTP_ONLY'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'SESSION_SAME_SITE' => $envConfig['SESSION_SAME_SITE'] ?? 'strict',
            
            // パフォーマンス設定
            'MAX_EXECUTION_TIME' => $envConfig['MAX_EXECUTION_TIME'] ?? '30',
            'MEMORY_LIMIT' => $envConfig['MEMORY_LIMIT'] ?? '512M',
            'UPLOAD_MAX_FILESIZE' => $envConfig['UPLOAD_MAX_FILESIZE'] ?? '10M',
            'POST_MAX_SIZE' => $envConfig['POST_MAX_SIZE'] ?? '10M',
            
            // ログ設定
            'LOG_LEVEL' => $envConfig['LOG_LEVEL'] ?? 'error',
            'LOG_FILE' => $envConfig['LOG_FILE'] ?? 'logs/production_errors.log',
            
            // セキュリティ設定
            'APP_KEY' => $envConfig['APP_KEY'] ?? 'production-secret-key-change-this',
            'CSRF_PROTECTION' => filter_var($envConfig['CSRF_PROTECTION'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'RATE_LIMITING' => filter_var($envConfig['RATE_LIMITING'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'SECURITY_HEADERS' => filter_var($envConfig['SECURITY_HEADERS'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        ];
        
        $this->loaded = true;
    }
    
    /**
     * 設定値の取得
     */
    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * 設定値の設定
     */
    public function set($key, $value) {
        $this->config[$key] = $value;
    }
    
    /**
     * 設定値の存在確認
     */
    public function has($key) {
        return array_key_exists($key, $this->config);
    }
    
    /**
     * 全設定の取得
     */
    public function all() {
        return $this->config;
    }
    
    /**
     * 環境が本番かどうか
     */
    public function isProduction() {
        return $this->get('APP_ENV') === 'production';
    }
    
    /**
     * デバッグモードかどうか
     */
    public function isDebug() {
        return $this->get('APP_DEBUG') === true;
    }
    
    /**
     * データベース設定の取得
     */
    public function getDatabaseConfig() {
        return [
            'host' => $this->get('DB_HOST'),
            'dbname' => $this->get('DB_NAME'),
            'username' => $this->get('DB_USERNAME'),
            'password' => $this->get('DB_PASSWORD'),
            'port' => $this->get('DB_PORT'),
            'charset' => $this->get('DB_CHARSET'),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ];
    }
    
    /**
     * セキュリティ設定の取得
     */
    public function getSecurityConfig() {
        return [
            'csrf_protection' => $this->get('CSRF_PROTECTION'),
            'rate_limiting' => $this->get('RATE_LIMITING'),
            'security_headers' => $this->get('SECURITY_HEADERS'),
            'session_lifetime' => $this->get('SESSION_LIFETIME'),
            'session_secure' => $this->get('SESSION_SECURE'),
            'session_http_only' => $this->get('SESSION_HTTP_ONLY'),
            'session_same_site' => $this->get('SESSION_SAME_SITE'),
            'app_key' => $this->get('APP_KEY')
        ];
    }
    
    /**
     * パフォーマンス設定の取得
     */
    public function getPerformanceConfig() {
        return [
            'max_execution_time' => $this->get('MAX_EXECUTION_TIME'),
            'memory_limit' => $this->get('MEMORY_LIMIT'),
            'upload_max_filesize' => $this->get('UPLOAD_MAX_FILESIZE'),
            'post_max_size' => $this->get('POST_MAX_SIZE')
        ];
    }
    
    /**
     * 設定情報の取得
     */
    public function getInfo() {
        return [
            'loaded' => $this->loaded,
            'config_count' => count($this->config),
            'environment' => $this->get('APP_ENV'),
            'debug_mode' => $this->get('APP_DEBUG'),
            'database' => $this->get('DB_NAME'),
            'timezone' => $this->get('APP_TIMEZONE'),
            'locale' => $this->get('APP_LOCALE')
        ];
    }
    
    /**
     * 設定のリロード
     */
    public function reload() {
        $this->loaded = false;
        $this->config = [];
        $this->loadConfig();
    }
}