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
        
        // 環境設定の読み込み
        self::$environment = getenv('APP_ENV') ?: 'production';
        
        // ログファイルの設定
        self::$logFile = __DIR__ . '/../../logs/application_' . date('Y-m-d') . '.log';
        
        // ログディレクトリの作成
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // PHPエラーハンドラーの設定
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$initialized = true;
    }
    
    /**
     * エラーログの記録
     * @param string $message エラーメッセージ
     * @param string $level ログレベル
     * @param array $context コンテキスト情報
     * @param string $type エラータイプ
     */
    public static function log($message, $level = self::LOG_LEVEL_ERROR, $context = [], $type = self::ERROR_TYPE_SYSTEM) {
        $logEntry = self::formatLogEntry($message, $level, $context, $type);
        
        // ファイルにログを記録
        error_log($logEntry, 3, self::$logFile);
        
        // 開発環境では標準出力にも表示
        if (self::$environment === 'development') {
            echo $logEntry . "\n";
        }
    }
    
    /**
     * デバッグログの記録
     */
    public static function debug($message, $context = []) {
        if (self::$environment === 'development') {
            self::log($message, self::LOG_LEVEL_DEBUG, $context);
        }
    }
    
    /**
     * 情報ログの記録
     */
    public static function info($message, $context = []) {
        self::log($message, self::LOG_LEVEL_INFO, $context);
    }
    
    /**
     * 警告ログの記録
     */
    public static function warning($message, $context = []) {
        self::log($message, self::LOG_LEVEL_WARNING, $context);
    }
    
    /**
     * エラーログの記録
     */
    public static function error($message, $context = []) {
        self::log($message, self::LOG_LEVEL_ERROR, $context);
    }
    
    /**
     * 致命的エラーログの記録
     */
    public static function critical($message, $context = []) {
        self::log($message, self::LOG_LEVEL_CRITICAL, $context);
    }
    
    /**
     * データベースエラーの記録
     */
    public static function databaseError($message, $context = []) {
        self::log($message, self::LOG_LEVEL_ERROR, $context, self::ERROR_TYPE_DATABASE);
    }
    
    /**
     * バリデーションエラーの記録
     */
    public static function validationError($message, $context = []) {
        self::log($message, self::LOG_LEVEL_WARNING, $context, self::ERROR_TYPE_VALIDATION);
    }
    
    /**
     * セキュリティエラーの記録
     */
    public static function securityError($message, $context = []) {
        self::log($message, self::LOG_LEVEL_CRITICAL, $context, self::ERROR_TYPE_SECURITY);
    }
    
    /**
     * ログエントリのフォーマット
     */
    private static function formatLogEntry($message, $level, $context, $type) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        return "[{$timestamp}] [{$level}] [{$type}] {$message}{$contextStr}";
    }
    
    /**
     * PHPエラーハンドラー
     */
    public static function handleError($severity, $message, $file, $line) {
        // エラー報告レベルに含まれていない場合は無視
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $level = self::LOG_LEVEL_ERROR;
        if ($severity === E_WARNING || $severity === E_USER_WARNING) {
            $level = self::LOG_LEVEL_WARNING;
        } elseif ($severity === E_NOTICE || $severity === E_USER_NOTICE) {
            $level = self::LOG_LEVEL_INFO;
        }
        
        $context = [
            'file' => $file,
            'line' => $line,
            'severity' => $severity
        ];
        
        self::log($message, $level, $context);
        
        // 致命的エラーの場合は例外を投げる
        if ($severity === E_ERROR || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
        
        return true;
    }
    
    /**
     * 例外ハンドラー
     */
    public static function handleException($exception) {
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        self::log($exception->getMessage(), self::LOG_LEVEL_ERROR, $context);
        
        // 本番環境では一般的なエラーメッセージを表示
        if (self::$environment === 'production') {
            http_response_code(500);
            echo "システムエラーが発生しました。しばらく時間をおいてから再度お試しください。";
        } else {
            // 開発環境では詳細なエラー情報を表示
            echo "<h1>Exception</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
            echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
            echo "<h2>Stack Trace</h2>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        }
    }
    
    /**
     * シャットダウン時のエラーハンドラー
     */
    public static function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $context = [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ];
            
            self::log($error['message'], self::LOG_LEVEL_CRITICAL, $context);
        }
    }
    
    /**
     * エラーレスポンスの生成
     */
    public static function createErrorResponse($message, $code = 500, $details = []) {
        $response = [
            'error' => true,
            'message' => $message,
            'code' => $code,
            'timestamp' => date('c')
        ];
        
        if (self::$environment === 'development' && !empty($details)) {
            $response['details'] = $details;
        }
        
        return $response;
    }
    
    /**
     * JSONエラーレスポンスの送信
     */
    public static function sendJsonErrorResponse($message, $code = 500, $details = []) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = self::createErrorResponse($message, $code, $details);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * ログファイルの取得
     */
    public static function getLogFile() {
        return self::$logFile;
    }
    
    /**
     * 環境の取得
     */
    public static function getEnvironment() {
        return self::$environment;
    }
}