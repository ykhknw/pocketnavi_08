<?php

/**
 * 本番環境用エラーハンドリング
 */

// ProductionConfigクラスの読み込み
require_once __DIR__ . '/ProductionConfig.php';

class ProductionErrorHandler {
    
    private static $instance = null;
    private $logFile;
    private $config;
    
    private function __construct() {
        // ProductionConfigが利用できない場合はデフォルト設定を使用
        try {
            $this->config = ProductionConfig::getInstance();
        } catch (Exception $e) {
            $this->config = null;
        }
        $this->logFile = __DIR__ . '/../../logs/production_errors.log';
        $this->initialize();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * エラーハンドリングの初期化
     */
    private function initialize() {
        // エラー表示を無効にする
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        
        // エラーログを有効にする
        ini_set('log_errors', 1);
        ini_set('error_log', $this->logFile);
        
        // エラーレポートレベルを設定
        error_reporting(E_ALL);
        
        // カスタムエラーハンドラーを設定
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    /**
     * エラーハンドリング
     */
    public function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error = [
            'type' => 'Error',
            'severity' => $this->getSeverityName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
        ];
        
        $this->logError($error);
        
        // 本番環境ではエラーを表示しない
        if ($this->config && $this->config->isProduction()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 例外ハンドリング
     */
    public function handleException($exception) {
        $error = [
            'type' => 'Exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
        ];
        
        $this->logError($error);
        
        // 本番環境では一般的なエラーページを表示
        if ($this->config && $this->config->isProduction()) {
            $this->showProductionErrorPage();
        } else {
            echo "Uncaught exception: " . $exception->getMessage() . "\n";
            echo "Stack trace:\n" . $exception->getTraceAsString() . "\n";
        }
    }
    
    /**
     * シャットダウン時のエラーハンドリング
     */
    public function handleShutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $errorData = [
                'type' => 'Fatal Error',
                'severity' => $this->getSeverityName($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
            ];
            
            $this->logError($errorData);
            
            if ($this->config && $this->config->isProduction()) {
                $this->showProductionErrorPage();
            }
        }
    }
    
    /**
     * エラーのログ記録
     */
    private function logError($error) {
        $logEntry = json_encode($error, JSON_UNESCAPED_UNICODE) . "\n";
        
        // ログディレクトリの作成
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // ログファイルへの書き込み
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // ログファイルのサイズ制限（10MB）
        if (file_exists($this->logFile) && filesize($this->logFile) > 10 * 1024 * 1024) {
            $this->rotateLogFile();
        }
    }
    
    /**
     * ログファイルのローテーション
     */
    private function rotateLogFile() {
        $backupFile = $this->logFile . '.' . date('Y-m-d-H-i-s');
        rename($this->logFile, $backupFile);
        
        // 古いログファイルの削除（7日以上前）
        $logDir = dirname($this->logFile);
        $files = glob($logDir . '/production_errors.log.*');
        
        foreach ($files as $file) {
            if (filemtime($file) < time() - (7 * 24 * 60 * 60)) {
                unlink($file);
            }
        }
    }
    
    /**
     * 本番環境用エラーページの表示
     */
    private function showProductionErrorPage() {
        http_response_code(500);
        
        $html = '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>システムエラー - PocketNavi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-icon { font-size: 48px; color: #e74c3c; text-align: center; margin-bottom: 20px; }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 20px; }
        p { color: #7f8c8d; line-height: 1.6; text-align: center; }
        .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; }
        .back-link:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">⚠️</div>
        <h1>システムエラーが発生しました</h1>
        <p>申し訳ございませんが、システムに一時的な問題が発生しています。</p>
        <p>しばらく時間をおいてから再度お試しください。</p>
        <p>問題が解決しない場合は、管理者にお問い合わせください。</p>
        <a href="/" class="back-link">トップページに戻る</a>
    </div>
</body>
</html>';
        
        echo $html;
        exit;
    }
    
    /**
     * エラーレベルの名前を取得
     */
    private function getSeverityName($severity) {
        $severities = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        
        return $severities[$severity] ?? 'UNKNOWN';
    }
    
    /**
     * エラーログの取得
     */
    public function getErrorLog($limit = 100) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $errors = [];
        
        foreach (array_slice($lines, -$limit) as $line) {
            $error = json_decode($line, true);
            if ($error) {
                $errors[] = $error;
            }
        }
        
        return array_reverse($errors);
    }
    
    /**
     * エラーログのクリア
     */
    public function clearErrorLog() {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }
}

