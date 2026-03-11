<?php

/**
 * セキュリティヘルパー
 */
class SecurityHelper {
    
    /**
     * HTML出力用のエスケープ
     */
    public static function escapeHtml($input) {
        if ($input === null) {
            return '';
        }
        
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * 属性値用のエスケープ
     */
    public static function escapeAttribute($input) {
        if ($input === null) {
            return '';
        }
        
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * JavaScript用のエスケープ
     */
    public static function escapeJs($input) {
        if ($input === null) {
            return '';
        }
        
        return json_encode($input, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
    
    /**
     * URL用のエスケープ
     */
    public static function escapeUrl($input) {
        if ($input === null) {
            return '';
        }
        
        return urlencode($input);
    }
    
    /**
     * 安全なHTML出力（許可されたタグのみ）
     */
    public static function safeHtml($input, $allowedTags = '<p><br><strong><em><u><a>') {
        if ($input === null) {
            return '';
        }
        
        // 許可されたタグのみを残す
        $input = strip_tags($input, $allowedTags);
        
        // 属性をサニタイズ
        $input = preg_replace_callback(
            '/<(\w+)([^>]*)>/i',
            function($matches) {
                $tag = strtolower($matches[1]);
                $attributes = $matches[2];
                
                // 許可された属性のみ
                $allowedAttributes = ['href', 'title', 'class', 'id'];
                
                if (preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $attributes, $attrMatches, PREG_SET_ORDER)) {
                    $cleanAttributes = '';
                    foreach ($attrMatches as $attrMatch) {
                        $attrName = strtolower($attrMatch[1]);
                        $attrValue = $attrMatch[2];
                        
                        if (in_array($attrName, $allowedAttributes)) {
                            if ($attrName === 'href') {
                                // URLの検証
                                if (filter_var($attrValue, FILTER_VALIDATE_URL)) {
                                    $cleanAttributes .= ' ' . $attrName . '="' . self::escapeAttribute($attrValue) . '"';
                                }
                            } else {
                                $cleanAttributes .= ' ' . $attrName . '="' . self::escapeAttribute($attrValue) . '"';
                            }
                        }
                    }
                    return '<' . $tag . $cleanAttributes . '>';
                }
                
                return '<' . $tag . '>';
            },
            $input
        );
        
        return $input;
    }
    
    /**
     * CSRFトークンの生成
     */
    public static function generateCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * CSRFトークンの検証
     */
    public static function validateCsrfToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * レート制限のチェック
     */
    public static function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600) {
        $key = 'rate_limit_' . md5($identifier);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $now = time();
        $requests = $_SESSION[$key] ?? [];
        
        // 古いリクエストを削除
        $requests = array_filter($requests, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        // リクエスト数をチェック
        if (count($requests) >= $maxRequests) {
            return false;
        }
        
        // 現在のリクエストを記録
        $requests[] = $now;
        $_SESSION[$key] = $requests;
        
        return true;
    }
    
    /**
     * ファイルアップロードの検証
     */
    public static function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5242880) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // ファイルサイズのチェック
        if ($file['size'] > $maxSize) {
            return false;
        }
        
        // ファイルタイプのチェック
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return false;
        }
        
        // ファイル名のサニタイズ
        $filename = basename($file['name']);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        return $filename;
    }
    
    /**
     * パスワードの強度チェック
     */
    public static function validatePasswordStrength($password) {
        if (strlen($password) < 8) {
            return false;
        }
        
        // 大文字、小文字、数字、記号のうち3つ以上を含む
        $patterns = [
            '/[a-z]/',  // 小文字
            '/[A-Z]/',  // 大文字
            '/[0-9]/',  // 数字
            '/[^a-zA-Z0-9]/'  // 記号
        ];
        
        $matches = 0;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $password)) {
                $matches++;
            }
        }
        
        return $matches >= 3;
    }
    
    /**
     * 安全なランダム文字列の生成
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * ログイン試行の記録
     */
    public static function logLoginAttempt($identifier, $success, $ip = null) {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        
        error_log("Login attempt: {$status} - {$identifier} from {$ip} at {$timestamp}");
    }
}
