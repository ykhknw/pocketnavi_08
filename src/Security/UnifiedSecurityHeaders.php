<?php
/**
 * 統合されたセキュリティヘッダー管理クラス
 * すべてのセキュリティヘッダーを一元管理
 */
class UnifiedSecurityHeaders {
    private $config;
    private $environment;
    private $headers = [];
    private $cspDirectives = [];
    
    public function __construct($environment = 'production') {
        $this->environment = $environment;
        $this->loadConfig();
        $this->initializeHeaders();
    }
    
    /**
     * 設定ファイルの読み込み
     */
    private function loadConfig() {
        $configFile = __DIR__ . '/../../config/security_headers.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            // デフォルト設定
            $this->config = $this->getDefaultConfig();
        }
    }
    
    /**
     * デフォルト設定の取得
     */
    private function getDefaultConfig() {
        return [
            'production' => [
                'permissions_policy' => [
                    'geolocation' => ['*'],
                    'microphone' => [],
                    'camera' => [],
                    'payment' => [],
                    'usb' => [],
                    'magnetometer' => [],
                    'gyroscope' => [],
                    'fullscreen' => []
                ],
                'csp' => [
                    'default-src' => ["'self'"],
                    'script-src' => ["'self'", "'unsafe-inline'", "https://cdn.jsdelivr.net", "https://unpkg.com", "https://www.googletagmanager.com", "https://www.google-analytics.com"],
                    'style-src' => ["'self'", "'unsafe-inline'", "https://cdn.jsdelivr.net", "https://unpkg.com"],
                    'img-src' => ["'self'", "data:", "https:", "*.openstreetmap.org"],
                    'font-src' => ["'self'", "https://cdn.jsdelivr.net", "https://unpkg.com"],
                    'connect-src' => ["'self'", "https://www.google-analytics.com", "https://analytics.google.com"],
                    'frame-ancestors' => ["'none'"],
                    'base-uri' => ["'self'"],
                    'form-action' => ["'self'"]
                ],
                'x_frame_options' => 'DENY',
                'x_content_type_options' => 'nosniff',
                'x_xss_protection' => '1; mode=block',
                'referrer_policy' => 'strict-origin-when-cross-origin',
                'hsts' => [
                    'enabled' => true,
                    'max_age' => 31536000,
                    'include_subdomains' => true,
                    'preload' => true
                ]
            ],
            'development' => [
                'permissions_policy' => [
                    'geolocation' => ['*'],
                    'microphone' => [],
                    'camera' => [],
                    'payment' => [],
                    'usb' => [],
                    'magnetometer' => [],
                    'gyroscope' => [],
                    'fullscreen' => []
                ],
                'csp' => [
                    'default-src' => ["'self'"],
                    'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", "https:", "https://www.googletagmanager.com", "https://www.google-analytics.com"],
                    'style-src' => ["'self'", "'unsafe-inline'", "https:"],
                    'img-src' => ["'self'", "data:", "https:", "blob:"],
                    'font-src' => ["'self'", "https:"],
                    'connect-src' => ["'self'", "https:", "https://www.google-analytics.com", "https://analytics.google.com"],
                    'frame-ancestors' => ["'none'"]
                ],
                'x_frame_options' => 'SAMEORIGIN',
                'x_content_type_options' => 'nosniff',
                'x_xss_protection' => '1; mode=block',
                'referrer_policy' => 'strict-origin-when-cross-origin',
                'hsts' => [
                    'enabled' => false
                ]
            ]
        ];
    }
    
    /**
     * ヘッダーの初期化
     */
    private function initializeHeaders() {
        $envConfig = $this->config[$this->environment] ?? $this->config['production'];
        
        // 基本セキュリティヘッダー
        $this->setXFrameOptions($envConfig['x_frame_options']);
        $this->setXContentTypeOptions($envConfig['x_content_type_options']);
        $this->setXXSSProtection($envConfig['x_xss_protection']);
        $this->setReferrerPolicy($envConfig['referrer_policy']);
        
        // Permissions Policy
        $this->setPermissionsPolicy($envConfig['permissions_policy']);
        
        // Content Security Policy
        $this->setContentSecurityPolicy($envConfig['csp']);
        
        // HSTS（HTTPS環境のみ）
        if ($envConfig['hsts']['enabled'] && $this->isHttps()) {
            $this->setStrictTransportSecurity(
                $envConfig['hsts']['max_age'],
                $envConfig['hsts']['include_subdomains'],
                $envConfig['hsts']['preload']
            );
        }
        
        // 環境固有の追加ヘッダー
        $this->setEnvironmentSpecificHeaders();
    }
    
    /**
     * 環境固有のヘッダー設定
     */
    private function setEnvironmentSpecificHeaders() {
        if ($this->environment === 'production') {
            $this->setCustomHeader('X-Permitted-Cross-Domain-Policies', 'none');
            $this->setCustomHeader('Cross-Origin-Opener-Policy', 'same-origin');
            $this->setCustomHeader('Cross-Origin-Resource-Policy', 'cross-origin');
        } else {
            $this->setCustomHeader('Cross-Origin-Resource-Policy', 'cross-origin');
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $this->setCustomHeader('X-Debug-Mode', 'enabled');
            }
        }
    }
    
    /**
     * X-Frame-Optionsの設定
     */
    public function setXFrameOptions($value) {
        $this->headers['X-Frame-Options'] = $value;
        return $this;
    }
    
    /**
     * X-Content-Type-Optionsの設定
     */
    public function setXContentTypeOptions($value) {
        $this->headers['X-Content-Type-Options'] = $value;
        return $this;
    }
    
    /**
     * X-XSS-Protectionの設定
     */
    public function setXXSSProtection($value) {
        $this->headers['X-XSS-Protection'] = $value;
        return $this;
    }
    
    /**
     * Referrer-Policyの設定
     */
    public function setReferrerPolicy($value) {
        $this->headers['Referrer-Policy'] = $value;
        return $this;
    }
    
    /**
     * Permissions-Policyの設定
     */
    public function setPermissionsPolicy($policies) {
        $policyStrings = [];
        
        foreach ($policies as $feature => $allowlist) {
            if (empty($allowlist)) {
                $policyStrings[] = $feature . '=()';
            } else {
                $policyStrings[] = $feature . '=(' . implode(' ', $allowlist) . ')';
            }
        }
        
        $this->headers['Permissions-Policy'] = implode(', ', $policyStrings);
        return $this;
    }
    
    /**
     * Content Security Policyの設定
     */
    public function setContentSecurityPolicy($directives) {
        $this->cspDirectives = $directives;
        return $this;
    }
    
    /**
     * Strict-Transport-Securityの設定
     */
    public function setStrictTransportSecurity($maxAge = 31536000, $includeSubDomains = true, $preload = false) {
        $value = "max-age={$maxAge}";
        if ($includeSubDomains) {
            $value .= "; includeSubDomains";
        }
        if ($preload) {
            $value .= "; preload";
        }
        
        $this->headers['Strict-Transport-Security'] = $value;
        return $this;
    }
    
    /**
     * カスタムヘッダーの設定
     */
    public function setCustomHeader($name, $value) {
        $this->headers[$name] = $value;
        return $this;
    }
    
    /**
     * HTTPS環境の判定
     */
    private function isHttps() {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }
    
    /**
     * ヘッダーの送信
     */
    public function sendHeaders() {
        // CSPヘッダーの構築
        if (!empty($this->cspDirectives)) {
            $cspString = $this->buildCSPString();
            $this->headers['Content-Security-Policy'] = $cspString;
            
            // デバッグモードではCSP-Report-Onlyも送信
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $this->headers['Content-Security-Policy-Report-Only'] = $cspString;
            }
        }
        
        // ヘッダーの送信
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }
    
    /**
     * CSP文字列の構築
     */
    private function buildCSPString() {
        $directives = [];
        
        foreach ($this->cspDirectives as $directive => $values) {
            if (is_array($values)) {
                $values = implode(' ', $values);
            }
            $directives[] = $directive . ' ' . $values;
        }
        
        return implode('; ', $directives);
    }
    
    /**
     * 環境の設定
     */
    public function setEnvironment($environment) {
        $this->environment = $environment;
        $this->initializeHeaders();
        return $this;
    }
    
    /**
     * ヘッダーの取得（デバッグ用）
     */
    public function getHeaders() {
        $headers = $this->headers;
        if (!empty($this->cspDirectives)) {
            $headers['Content-Security-Policy'] = $this->buildCSPString();
        }
        return $headers;
    }
    
    /**
     * 特定のヘッダーの取得
     */
    public function getHeader($name) {
        if ($name === 'Content-Security-Policy' && !empty($this->cspDirectives)) {
            return $this->buildCSPString();
        }
        return $this->headers[$name] ?? null;
    }
    
    /**
     * ヘッダーの削除
     */
    public function removeHeader($name) {
        unset($this->headers[$name]);
        return $this;
    }
    
    /**
     * 全ヘッダーのクリア
     */
    public function clearHeaders() {
        $this->headers = [];
        $this->cspDirectives = [];
        return $this;
    }
}
?>
