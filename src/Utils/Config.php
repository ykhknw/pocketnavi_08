<?php

// 必要なファイルを読み込み
require_once __DIR__ . '/ConfigValidator.php';

/**
 * 設定管理クラス（ヘテムル対応版）
 */
class Config {
    private static $config = [];
    private static $loaded = false;
    
    /**
     * 設定を読み込み
     */
    public static function load() {
        if (self::$loaded) return;
        
        // 環境変数を読み込み
        self::loadEnvironment();
        
        // 設定ファイルを読み込み
        self::$config = [
            'app' => self::loadConfigFile('app'),
            'database' => self::loadConfigFile('database'),
            'logging' => self::loadConfigFile('logging'),
            'cache' => self::loadConfigFile('cache'),
        ];
        
        // 設定値を検証
        try {
            ConfigValidator::validate(self::$config);
        } catch (InvalidConfigurationException $e) {
            // 設定検証エラーはログに記録して続行
            error_log('Configuration validation failed: ' . $e->getMessage());
        }
        
        self::$loaded = true;
    }
    
    /**
     * 環境変数を読み込み
     */
    private static function loadEnvironment() {
        // 環境変数設定ファイルを読み込み
        $envFile = __DIR__ . '/../../config/env.php';
        if (file_exists($envFile)) {
            require_once $envFile;
        }
    }
    
    /**
     * 設定ファイルを読み込み
     */
    private static function loadConfigFile($name) {
        $configFile = __DIR__ . "/../../config/{$name}.php";
        if (file_exists($configFile)) {
            return require $configFile;
        }
        return [];
    }
    
    /**
     * 設定値を取得
     */
    public static function get($key, $default = null) {
        self::load();
        return self::getNestedValue(self::$config, $key, $default);
    }
    
    /**
     * 設定値を設定
     */
    public static function set($key, $value) {
        self::load();
        self::setNestedValue(self::$config, $key, $value);
    }
    
    /**
     * 設定値が存在するかチェック
     */
    public static function has($key) {
        self::load();
        return self::getNestedValue(self::$config, $key) !== null;
    }
    
    /**
     * すべての設定を取得
     */
    public static function all() {
        self::load();
        return self::$config;
    }
    
    /**
     * ネストされた配列から値を取得
     */
    private static function getNestedValue($array, $key, $default = null) {
        if (empty($key)) {
            return $array;
        }
        
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * ネストされた配列に値を設定
     */
    private static function setNestedValue(&$array, $key, $value) {
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $k) {
            if (!is_array($current)) {
                $current = [];
            }
            if (!array_key_exists($k, $current)) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        $current = $value;
    }
    
    /**
     * 設定をリセット
     */
    public static function reset() {
        self::$config = [];
        self::$loaded = false;
    }
    
    /**
     * 環境を取得
     */
    public static function getEnvironment() {
        return self::get('app.env', 'production');
    }
    
    /**
     * デバッグモードかチェック
     */
    public static function isDebug() {
        return self::get('app.debug', false);
    }
    
    /**
     * 本番環境かチェック
     */
    public static function isProduction() {
        return self::getEnvironment() === 'production';
    }
    
    /**
     * 開発環境かチェック
     */
    public static function isDevelopment() {
        return self::getEnvironment() === 'development' || self::getEnvironment() === 'local';
    }
    
    /**
     * データベース設定を取得
     */
    public static function getDatabaseConfig($connection = null) {
        $connection = $connection ?: self::get('database.default', 'mysql');
        return self::get("database.connections.{$connection}", []);
    }
    
    /**
     * アプリケーション設定を取得
     */
    public static function getAppConfig() {
        return self::get('app', []);
    }
    
    /**
     * ログ設定を取得
     */
    public static function getLoggingConfig() {
        return self::get('logging', []);
    }
    
    /**
     * キャッシュ設定を取得
     */
    public static function getCacheConfig() {
        return self::get('cache', []);
    }
    
    /**
     * 設定値を配列として取得（デバッグ用）
     */
    public static function toArray() {
        self::load();
        return self::$config;
    }
    
    /**
     * 設定値をJSONとして取得（デバッグ用）
     */
    public static function toJson($pretty = false) {
        self::load();
        $flags = JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return json_encode(self::$config, $flags);
    }
}
