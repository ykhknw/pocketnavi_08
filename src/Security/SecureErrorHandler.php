<?php
/**
 * セキュアエラーハンドリングクラス
 * 本番環境での情報漏洩を防止
 */
class SecureErrorHandler {
    private $isProduction;
    private $logFile;
    private $errorTypes = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    public function __construct($isProduction = false, $logFile = null) {
        $this->isProduction = $isProduction;
        $this->logFile = $logFile ?: __DIR__ . '/../../logs/security_errors.log';
        
        $this->initializeErrorHandling();
    }
    
    /**
     * エラーハンドリングの初期化
     */
    private function initializeErrorHandling() {
        // エラーレポートレベルの設定
        if ($this->isProduction) {
            error_reporting(E_ALL);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        }
        
        // カスタムエラーハンドラーの設定
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
        
        // ログディレクトリの作成
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * エラーハンドリング
     */
    public function handleError($severity, $message, $file, $line) {
        // エラーレポートが無効な場合は何もしない
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = $this->errorTypes[$severity] ?? 'Unknown Error';
        $errorInfo = [
            'type' => $errorType,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
        ];
        
        // ログに記録
        $this->logError($errorInfo);
        
        // 本番環境では一般化されたエラーメッセージを表示
        if ($this->isProduction) {
            $this->displaySecureError($errorType);
        } else {
            // 開発環境では詳細なエラー情報を表示
            $this->displayDetailedError($errorInfo);
        }
        
        // 致命的なエラーの場合は処理を停止
        if ($severity === E_ERROR || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
            exit(1);
        }
        
        return true;
    }
    
    /**
     * 例外ハンドリング
     */
    public function handleException($exception) {
        $errorInfo = [
            'type' => 'Uncaught Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
        ];
        
        // ログに記録
        $this->logError($errorInfo);
        
        // 本番環境では一般化されたエラーメッセージを表示
        if ($this->isProduction) {
            $this->displaySecureError('System Error');
        } else {
            // 開発環境では詳細なエラー情報を表示
            $this->displayDetailedError($errorInfo);
        }
        
        exit(1);
    }
    
    /**
     * シャットダウン時のエラーハンドリング
     */
    public function handleShutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $errorInfo = [
                'type' => 'Fatal Error (Shutdown)',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
            ];
            
            // ログに記録
            $this->logError($errorInfo);
            
            // 本番環境では一般化されたエラーメッセージを表示
            if ($this->isProduction) {
                $this->displaySecureError('System Error');
            }
        }
    }
    
    /**
     * セキュアなエラーメッセージの表示
     */
    private function displaySecureError($errorType) {
        // 既に出力が開始されている場合は何もしない
        if (headers_sent()) {
            return;
        }
        
        // 適切なHTTPステータスコードを設定
        http_response_code(500);
        
        // セキュリティヘッダーを設定
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // 一般化されたエラーページを表示
        echo $this->getSecureErrorPage($errorType);
    }
    
    /**
     * 詳細なエラーメッセージの表示（開発環境用）
     */
    private function displayDetailedError($errorInfo) {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px; border-radius: 5px;'>";
        echo "<h3>Error: {$errorInfo['type']}</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($errorInfo['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($errorInfo['file']) . "</p>";
        echo "<p><strong>Line:</strong> {$errorInfo['line']}</p>";
        echo "<p><strong>Time:</strong> {$errorInfo['timestamp']}</p>";
        
        if (isset($errorInfo['trace'])) {
            echo "<p><strong>Stack Trace:</strong></p>";
            echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto;'>";
            echo htmlspecialchars($errorInfo['trace']);
            echo "</pre>";
        }
        
        echo "</div>";
    }
    
    /**
     * セキュアなエラーページの取得
     */
    private function getSecureErrorPage($errorType) {
        return "
        <!DOCTYPE html>
        <html lang='ja'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>システムエラー</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .error-container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .error-icon { font-size: 48px; color: #dc3545; text-align: center; margin-bottom: 20px; }
                .error-title { color: #dc3545; text-align: center; margin-bottom: 20px; }
                .error-message { color: #6c757d; text-align: center; line-height: 1.6; }
                .error-actions { text-align: center; margin-top: 30px; }
                .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 0 10px; }
                .btn:hover { background-color: #0056b3; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <div class='error-icon'>⚠️</div>
                <h1 class='error-title'>システムエラーが発生しました</h1>
                <p class='error-message'>
                    申し訳ございませんが、システムエラーが発生しました。<br>
                    しばらく時間をおいてから再度お試しください。<br>
                    問題が解決しない場合は、管理者にお問い合わせください。
                </p>
                <div class='error-actions'>
                    <a href='/' class='btn'>ホームに戻る</a>
                    <a href='javascript:history.back()' class='btn'>前のページに戻る</a>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * エラーログの記録
     */
    private function logError($errorInfo) {
        // ログディレクトリの作成
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // ログエントリの構築
        $logEntry = [
            'timestamp' => $errorInfo['timestamp'],
            'type' => $errorInfo['type'],
            'message' => $errorInfo['message'],
            'file' => $errorInfo['file'],
            'line' => $errorInfo['line'],
            'ip' => $errorInfo['ip'],
            'user_agent' => $errorInfo['user_agent'],
            'request_uri' => $errorInfo['request_uri']
        ];
        
        if (isset($errorInfo['trace'])) {
            $logEntry['trace'] = $errorInfo['trace'];
        }
        
        // ログファイルに書き込み
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * クライアントIPの取得
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * セキュリティイベントのログ記録
     */
    public function logSecurityEvent($eventType, $details = []) {
        $eventInfo = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $eventType,
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'details' => $details
        ];
        
        $this->logError($eventInfo);
    }
}
?>
