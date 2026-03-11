<?php

/**
 * 統一されたエラーハンドリングクラス
 * アプリケーション全体で一貫したエラー処理を提供
 */
class ErrorHandler {
    
    // ログレベル定数
    const LOG_LEVEL_DEBUG = 'debug';
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_CRITICAL = 'critical';
    
    // エラータイプ定数
    const ERROR_TYPE_DATABASE = 'database';
    const ERROR_TYPE_VALIDATION = 'validation';
    const ERROR_TYPE_SECURITY = 'security';
    const ERROR_TYPE_SYSTEM = 'system';
    const ERROR_TYPE_USER = 'user';
    
    private static $initialized = false;
    private static $logFile = null;
    private static $environment = 'production';
    
    /**
     * エラーハンドラーの初期化
     */
    public static function initialize() {
        if (self::$initialized) {
            return;
        }
        
        // ログファイルの設定
        self::$logFile = __DIR__ . '/../../logs/application.log';
        
        // ログディレクトリの作成
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // 環境の設定
        self::$environment = $_ENV['APP_ENV'] ?? 'production';
        
        // エラーハンドラーの設定
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$initialized = true;
    }
    
    /**
     * エラーログの記録
     */
    public static function log($message, $level = self::LOG_LEVEL_ERROR, $context = [], $type = self::ERROR_TYPE_SYSTEM) {
        $logEntry = self::formatLogEntry($message, $level, $context, $type);
        
        // ログファイルが設定されている場合のみ記録
        if (self::$logFile && is_writable(dirname(self::$logFile))) {
            error_log($logEntry, 3, self::$logFile);
        } else {
            // フォールバック: システムログに記録
            error_log($logEntry);
        }
        
        // 開発環境では標準出力にも表示
        if (self::$environment === 'development') {
            echo "<div style='color: red; background: #ffe6e6; padding: 10px; margin: 5px; border: 1px solid #ff9999;'>";
            echo "<strong>Error:</strong> " . htmlspecialchars($message) . "<br>";
            if (!empty($context)) {
                echo "<strong>Context:</strong> " . htmlspecialchars(print_r($context, true));
            }
            echo "</div>";
        }
    }
    
    /**
     * ログエントリのフォーマット
     */
    private static function formatLogEntry($message, $level, $context, $type) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        
        return sprintf(
            "[%s] %s.%s: %s %s\n",
            $timestamp,
            strtoupper($level),
            strtoupper($type),
            $message,
            $contextStr
        );
    }
    
    /**
     * PHPエラーのハンドリング
     */
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $level = self::getLogLevel($severity);
        $context = [
            'file' => $file,
            'line' => $line,
            'severity' => $severity
        ];
        
        self::log($message, $level, $context, self::ERROR_TYPE_SYSTEM);
        
        // 致命的エラーの場合は例外を投げる
        if ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
        
        return true;
    }
    
    /**
     * 例外のハンドリング
     */
    public static function handleException($exception) {
        $message = $exception->getMessage();
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        self::log($message, self::LOG_LEVEL_CRITICAL, $context, self::ERROR_TYPE_SYSTEM);
        
        // 本番環境では一般的なエラーページを表示
        if (self::$environment === 'production') {
            http_response_code(500);
            echo "<!DOCTYPE html>
<html>
<head>
    <title>システムエラー</title>
    <meta charset='UTF-8'>
</head>
<body>
    <h1>⚠️ システムエラーが発生しました</h1>
    <p>申し訳ございませんが、システムに一時的な問題が発生しています。</p>
    <p>しばらく時間をおいてから再度お試しください。</p>
    <p>問題が解決しない場合は、管理者にお問い合わせください。</p>
    <p><a href='/'>トップページに戻る</a></p>
</body>
</html>";
        } else {
            // 開発環境では詳細なエラー情報を表示
            echo "<h1>Exception: " . htmlspecialchars($message) . "</h1>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        }
    }
    
    /**
     * シャットダウン時のエラーハンドリング
     */
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $context = [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ];
            
            self::log($error['message'], self::LOG_LEVEL_CRITICAL, $context, self::ERROR_TYPE_SYSTEM);
        }
    }
    
    /**
     * エラーレベルの取得
     */
    private static function getLogLevel($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return self::LOG_LEVEL_ERROR;
                
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return self::LOG_LEVEL_WARNING;
                
            case E_NOTICE:
            case E_USER_NOTICE:
                return self::LOG_LEVEL_INFO;
                
            default:
                return self::LOG_LEVEL_ERROR;
        }
    }
    
    /**
     * データベースエラーの記録
     */
    public static function logDatabaseError($message, $query = null, $params = []) {
        $context = [
            'query' => $query,
            'params' => $params
        ];
        
        self::log($message, self::LOG_LEVEL_ERROR, $context, self::ERROR_TYPE_DATABASE);
    }
    
    /**
     * セキュリティエラーの記録
     */
    public static function logSecurityError($message, $ip = null, $userAgent = null) {
        $context = [
            'ip' => $ip ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $userAgent ?: $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        self::log($message, self::LOG_LEVEL_WARNING, $context, self::ERROR_TYPE_SECURITY);
    }
    
    /**
     * バリデーションエラーの記録
     */
    public static function logValidationError($message, $field = null, $value = null) {
        $context = [
            'field' => $field,
            'value' => $value
        ];
        
        self::log($message, self::LOG_LEVEL_WARNING, $context, self::ERROR_TYPE_VALIDATION);
    }
}
