<?php

/**
 * 構造化ログクラス（ヘテムル対応版）
 */
class Logger {
    const LEVEL_DEBUG = 0;
    const LEVEL_INFO = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_ERROR = 3;
    const LEVEL_CRITICAL = 4;
    
    private static $logLevel = self::LEVEL_INFO;
    private static $logDir = 'logs/';
    private static $maxFileSize = 5 * 1024 * 1024; // 5MB
    private static $maxFiles = 10; // 最大10ファイル
    private static $initialized = false;
    
    /**
     * ロガーを初期化
     */
    public static function init() {
        if (self::$initialized) return;
        
        // ログディレクトリの存在確認と作成
        if (!is_dir(self::$logDir)) {
            if (!mkdir(self::$logDir, 0755, true)) {
                // ヘテムルでは権限エラーの可能性があるため、エラーログに記録
                error_log('Failed to create log directory: ' . self::$logDir);
                self::$logDir = ''; // ログディレクトリが作成できない場合は空文字
            }
        }
        
        // ログレベルを環境変数から取得
        self::$logLevel = self::getLogLevelFromEnv();
        
        self::$initialized = true;
    }
    
    /**
     * 環境変数からログレベルを取得
     */
    private static function getLogLevelFromEnv() {
        $level = getenv('LOG_LEVEL') ?: 'INFO';
        switch (strtoupper($level)) {
            case 'DEBUG': return self::LEVEL_DEBUG;
            case 'INFO': return self::LEVEL_INFO;
            case 'WARNING': return self::LEVEL_WARNING;
            case 'ERROR': return self::LEVEL_ERROR;
            case 'CRITICAL': return self::LEVEL_CRITICAL;
            default: return self::LEVEL_INFO;
        }
    }
    
    /**
     * ログレベル名を取得
     */
    private static function getLevelName($level) {
        switch ($level) {
            case self::LEVEL_DEBUG: return 'DEBUG';
            case self::LEVEL_INFO: return 'INFO';
            case self::LEVEL_WARNING: return 'WARNING';
            case self::LEVEL_ERROR: return 'ERROR';
            case self::LEVEL_CRITICAL: return 'CRITICAL';
            default: return 'UNKNOWN';
        }
    }
    
    /**
     * リクエストIDを取得
     */
    private static function getRequestId() {
        static $requestId = null;
        if ($requestId === null) {
            $requestId = uniqid('req_', true);
        }
        return $requestId;
    }
    
    /**
     * クライアントIPを取得
     */
    private static function getClientIp() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * ユーザーエージェントを取得
     */
    private static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * 現在のユーザーIDを取得
     */
    private static function getCurrentUserId() {
        // セッションからユーザーIDを取得（実装に応じて変更）
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * DEBUGレベルでログを記録
     */
    public static function debug($message, $context = []) {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * INFOレベルでログを記録
     */
    public static function info($message, $context = []) {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * WARNINGレベルでログを記録
     */
    public static function warning($message, $context = []) {
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * ERRORレベルでログを記録
     */
    public static function error($message, $context = []) {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * CRITICALレベルでログを記録
     */
    public static function critical($message, $context = []) {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    /**
     * ログを記録
     */
    private static function log($level, $message, $context = []) {
        self::init();
        
        if ($level < self::$logLevel) return;
        
        $logEntry = [
            'timestamp' => date('c'), // ISO 8601形式
            'level' => self::getLevelName($level),
            'message' => $message,
            'context' => $context,
            'request_id' => self::getRequestId(),
            'user_id' => self::getCurrentUserId(),
            'ip' => self::getClientIp(),
            'user_agent' => self::getUserAgent(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        self::writeToFile($logEntry);
    }
    
    /**
     * ログファイルに書き込み
     */
    private static function writeToFile($logEntry) {
        if (empty(self::$logDir)) {
            // ログディレクトリが作成できない場合は、PHPのエラーログに記録
            error_log(json_encode($logEntry, JSON_UNESCAPED_UNICODE));
            return;
        }
        
        $logFile = self::$logDir . 'application_' . date('Y-m-d') . '.log';
        
        // ファイルサイズチェック
        if (file_exists($logFile) && filesize($logFile) > self::$maxFileSize) {
            self::rotateLog($logFile);
        }
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
        
        // ファイルに書き込み（ヘテムルでは権限エラーの可能性があるため、エラーハンドリング）
        $result = @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            // ファイル書き込みに失敗した場合は、PHPのエラーログに記録
            error_log('Failed to write to log file: ' . $logFile . ' - Content: ' . $logLine);
        }
    }
    
    /**
     * ログファイルをローテーション
     */
    private static function rotateLog($logFile) {
        $timestamp = date('Y-m-d_H-i-s');
        $rotatedFile = $logFile . '.' . $timestamp;
        
        if (rename($logFile, $rotatedFile)) {
            // 古いログファイルを削除
            self::cleanOldLogs($logFile);
        }
    }
    
    /**
     * 古いログファイルを削除
     */
    private static function cleanOldLogs($baseFile) {
        $pattern = $baseFile . '.*';
        $files = glob($pattern);
        
        if (count($files) > self::$maxFiles) {
            // 古いファイルを削除
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            $filesToDelete = array_slice($files, 0, count($files) - self::$maxFiles);
            foreach ($filesToDelete as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * ログレベルを設定
     */
    public static function setLogLevel($level) {
        self::$logLevel = $level;
    }
    
    /**
     * 現在のログレベルを取得
     */
    public static function getLogLevel() {
        return self::$logLevel;
    }
    
    /**
     * ログディレクトリを設定
     */
    public static function setLogDir($dir) {
        self::$logDir = rtrim($dir, '/') . '/';
    }
    
    /**
     * 最大ファイルサイズを設定
     */
    public static function setMaxFileSize($size) {
        self::$maxFileSize = $size;
    }
    
    /**
     * 最大ファイル数を設定
     */
    public static function setMaxFiles($count) {
        self::$maxFiles = $count;
    }
}
