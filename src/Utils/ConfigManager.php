<?php

require_once __DIR__ . '/EnvironmentLoader.php';

/**
 * 設定管理クラス
 * アプリケーションの設定値を一元管理
 */
class ConfigManager {
    
    private static $config = [];
    private static $loaded = false;
    
    /**
     * 設定の初期化
     */
    public static function initialize() {
        if (self::$loaded) {
            return;
        }
        
        // 環境設定の読み込み
        $envLoader = new EnvironmentLoader();
        $envConfig = $envLoader->load();
        
        // 設定の統合
        self::$config = array_merge([
            'app' => [
                'name' => 'PocketNavi',
                'env' => 'production',
                'debug' => false,
                'url' => 'https://kenchikuka.com',
                'timezone' => 'Asia/Tokyo',
                'locale' => 'ja',
                'fallback_locale' => 'en'
            ],
            'database' => [
                'host' => 'localhost',
                'name' => '_shinkenchiku_12',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4'
            ],
            'cache' => [
                'driver' => 'file',
                'ttl' => 3600
            ],
            'session' => [
                'lifetime' => 7200,
                'secure' => true,
                'http_only' => true,
                'same_site' => 'strict'
            ],
            'display' => [
                'show_likes' => false  // いいね表示のON/OFF
            ]
        ], $envConfig);
        
        self::$loaded = true;
    }
    
    /**
     * 設定値の取得
     */
    public static function get($key, $default = null) {
        self::initialize();
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * 設定値の設定
     */
    public static function set($key, $value) {
        self::initialize();
        
        $keys = explode('.', $key);
        $config = &self::$config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    /**
     * すべての設定を取得
     */
    public static function all() {
        self::initialize();
        return self::$config;
    }
    
    /**
     * 設定の存在確認
     */
    public static function has($key) {
        self::initialize();
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }
        
        return true;
    }
}