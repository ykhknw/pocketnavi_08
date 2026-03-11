<?php
/**
 * 環境設定ローダー
 * .envファイルから設定を読み込む
 */
class EnvironmentLoader {
    
    private static $config = [];
    private static $loaded = false;
    
    /**
     * 設定を読み込む
     */
    public function load() {
        if (self::$loaded) {
            return self::$config;
        }
        
        // 複数の.envファイルを試行
        $envFiles = [
            __DIR__ . '/../../config/.env',
            __DIR__ . '/../../config/.env.local',
            __DIR__ . '/../../.env',
            __DIR__ . '/../../.env.local'
        ];
        
        foreach ($envFiles as $envFile) {
            if (file_exists($envFile)) {
                $this->parseEnvFile($envFile);
                break;
            }
        }
        
        // デフォルト値を設定
        $this->setDefaults();
        
        self::$loaded = true;
        return self::$config;
    }
    
    /**
     * .envファイルを解析
     */
    private function parseEnvFile($filePath) {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // コメント行をスキップ
            if (strpos($line, '#') === 0) {
                continue;
            }
            
            // キー=値の形式を解析
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // クォートを削除
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                self::$config[$key] = $value;
            }
        }
    }
    
    /**
     * デフォルト値を設定
     */
    private function setDefaults() {
        $defaults = [
            'DB_HOST' => 'localhost',
            'DB_NAME' => '_shinkenchiku_12',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'APP_NAME' => 'PocketNavi',
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'true',
            'APP_KEY' => '0a53961ea1609c394e8178c61b64c58491d0b59629ec310c60f9ac8b75eb8d4a',
            'SESSION_LIFETIME' => '7200',
            'SESSION_SECURE' => 'false',
            'SESSION_HTTP_ONLY' => 'true',
            'SESSION_SAME_SITE' => 'strict',
            'LOG_LEVEL' => 'debug',
            'LOG_FILE' => 'logs/application.log',
            'CACHE_ENABLED' => 'true',
            'CACHE_TTL' => '300',
            'CSRF_ENABLED' => 'true',
            'RATE_LIMIT_ENABLED' => 'false',
            'DEFAULT_LANGUAGE' => 'ja',
            'SUPPORTED_LANGUAGES' => 'ja,en'
        ];
        
        foreach ($defaults as $key => $value) {
            if (!isset(self::$config[$key])) {
                self::$config[$key] = $value;
            }
        }
    }
    
    /**
     * 設定値を取得
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            $instance = new self();
            $instance->load();
        }
        
        return self::$config[$key] ?? $default;
    }
}