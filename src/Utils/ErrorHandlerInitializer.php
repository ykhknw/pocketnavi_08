<?php

/**
 * エラーハンドリング初期化クラス
 * アプリケーション起動時にエラーハンドリングを設定
 */
class ErrorHandlerInitializer {
    
    private static $initialized = false;
    
    /**
     * エラーハンドリングの初期化
     */
    public static function initialize() {
        if (self::$initialized) {
            return;
        }
        
        // 必要なファイルの読み込み
        require_once __DIR__ . '/ErrorHandler.php';
        require_once __DIR__ . '/../Exceptions/AppException.php';
        
        // エラーハンドラーの初期化
        ErrorHandler::initialize();
        
        // 環境設定
        $environment = getenv('APP_ENV') ?: 'production';
        
        // 開発環境でのエラー表示設定
        if ($environment === 'development') {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
        }
        
        // ログ設定
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../../logs/php_errors_' . date('Y-m-d') . '.log');
        
        self::$initialized = true;
        
        // 初期化完了をログに記録
        ErrorHandler::info("Error handling system initialized", [
            'environment' => $environment,
            'error_reporting' => error_reporting(),
            'display_errors' => ini_get('display_errors')
        ]);
    }
    
    /**
     * 初期化状態の確認
     */
    public static function isInitialized() {
        return self::$initialized;
    }
    
    /**
     * グローバルエラーハンドリング関数
     */
    public static function handleGlobalError($message, $context = []) {
        if (!self::$initialized) {
            self::initialize();
        }
        
        ErrorHandler::error($message, $context);
    }
    
    /**
     * グローバル例外ハンドリング関数
     */
    public static function handleGlobalException($exception, $context = []) {
        if (!self::$initialized) {
            self::initialize();
        }
        
        $errorContext = array_merge($context, [
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);
        
        ErrorHandler::error($exception->getMessage(), $errorContext);
    }
}
