<?php

/**
 * セキュリティヘッダー管理クラス（後方互換性のため保持）
 * 統合されたUnifiedSecurityHeadersクラスを使用することを推奨
 * @deprecated 統合されたUnifiedSecurityHeadersクラスを使用してください
 */
class SecurityHeaders {
    
    /**
     * セキュリティヘッダーを設定
     * @deprecated 統合されたUnifiedSecurityHeadersクラスを使用してください
     */
    public static function setSecurityHeaders() {
        // 統合されたクラスを使用することを推奨
        if (class_exists('UnifiedSecurityHeaders')) {
            trigger_error('SecurityHeaders::setSecurityHeaders()は非推奨です。UnifiedSecurityHeadersクラスを使用してください。', E_USER_DEPRECATED);
        }
        // XSS対策
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // HTTPS強制（本番環境）
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net https://www.googletagmanager.com; " .
               "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "img-src 'self' data: https: blob:; " .
               "font-src 'self' https://unpkg.com; " .
               "connect-src 'self' https: https://www.google-analytics.com https://analytics.google.com; " .
               "frame-ancestors 'none'; " .
               "object-src 'none'; " .
               "base-uri 'self';";
        
        header("Content-Security-Policy: {$csp}");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=*, microphone=(), camera=()');
    }
    
    /**
     * CORSヘッダーを設定
     */
    public static function setCorsHeaders($allowedOrigins = ['http://localhost:8000']) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }
        
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
    
    /**
     * キャッシュ制御ヘッダーを設定
     */
    public static function setCacheHeaders($maxAge = 3600, $isPublic = false) {
        $cacheControl = $isPublic ? 'public' : 'private';
        header("Cache-Control: {$cacheControl}, max-age={$maxAge}");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    }
    
    /**
     * 開発環境用のヘッダー
     */
    public static function setDevelopmentHeaders() {
        // 開発環境ではセキュリティヘッダーを緩和
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        
        // 開発環境用のCSP（より緩い設定）
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; " .
               "style-src 'self' 'unsafe-inline' https:; " .
               "img-src 'self' data: https: blob:; " .
               "font-src 'self' https:; " .
               "connect-src 'self' https:; " .
               "frame-ancestors 'none';";
        
        header("Content-Security-Policy: {$csp}");
        
        // COEPを無効化してOpenStreetMapタイルの読み込みを許可
        // header('Cross-Origin-Embedder-Policy: require-corp');
        header('Cross-Origin-Resource-Policy: cross-origin');
        
        // 開発環境用のPermissions Policy（位置情報を許可）
        header('Permissions-Policy: geolocation=*, microphone=(), camera=()');
        
        // デバッグ情報を表示（開発環境のみ）
        if (defined('DEBUG') && DEBUG) {
            header('X-Debug-Mode: enabled');
        }
    }
    
    /**
     * 本番環境用のヘッダー
     */
    public static function setProductionHeaders() {
        self::setSecurityHeaders();
        
        // 追加のセキュリティヘッダー
        header('X-Permitted-Cross-Domain-Policies: none');
        // COEPを無効化してOpenStreetMapタイルの読み込みを許可
        // header('Cross-Origin-Embedder-Policy: require-corp');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: cross-origin');
    }
    
    /**
     * 環境に応じてヘッダーを設定
     */
    public static function setHeadersByEnvironment() {
        $isProduction = !defined('DEBUG') || !DEBUG;
        
        if ($isProduction) {
            self::setProductionHeaders();
        } else {
            self::setDevelopmentHeaders();
        }
        
        // 共通のヘッダー
        header('Server: PocketNavi/1.0');
        header('X-Powered-By: PHP/' . PHP_VERSION);
    }
}
