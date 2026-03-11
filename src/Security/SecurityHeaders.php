<?php
/**
 * セキュリティヘッダー管理クラス（後方互換性のため保持）
 * 統合されたUnifiedSecurityHeadersクラスを使用することを推奨
 * @deprecated 統合されたUnifiedSecurityHeadersクラスを使用してください
 */
class SecurityHeaders {
    private $headers = [];
    private $cspDirectives = [];
    
    public function __construct() {
        // 統合されたクラスを使用することを推奨
        if (class_exists('UnifiedSecurityHeaders')) {
            trigger_error('SecurityHeadersクラスは非推奨です。UnifiedSecurityHeadersクラスを使用してください。', E_USER_DEPRECATED);
        }
        $this->initializeDefaultHeaders();
    }
    
    /**
     * デフォルトヘッダーの初期化
     */
    private function initializeDefaultHeaders() {
        // X-Frame-Options
        $this->setXFrameOptions('DENY');
        
        // X-Content-Type-Options
        $this->setXContentTypeOptions('nosniff');
        
        // X-XSS-Protection
        $this->setXXSSProtection('1; mode=block');
        
        // Referrer-Policy
        $this->setReferrerPolicy('strict-origin-when-cross-origin');
        
        // Permissions-Policy（標準的な機能のみ）
        $this->setPermissionsPolicy([
            'geolocation' => ['*'],
            'microphone' => [],
            'camera' => [],
            'payment' => [],
            'usb' => [],
            'magnetometer' => [],
            'gyroscope' => [],
            'fullscreen' => []
        ]);
        
        // Content Security Policy
        $this->setContentSecurityPolicy([
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "https://cdn.jsdelivr.net"],
            'style-src' => ["'self'", "'unsafe-inline'", "https://cdn.jsdelivr.net"],
            'img-src' => ["'self'", "data:", "https:"],
            'font-src' => ["'self'", "https://cdn.jsdelivr.net"],
            'connect-src' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"]
        ]);
    }
    
    /**
     * X-Frame-Optionsの設定
     */
    public function setXFrameOptions($value) {
        $allowedValues = ['DENY', 'SAMEORIGIN'];
        if (in_array($value, $allowedValues)) {
            $this->headers['X-Frame-Options'] = $value;
        } else {
            // カスタム値の場合はそのまま設定（例: ALLOW-FROM uri）
            $this->headers['X-Frame-Options'] = $value;
        }
        return $this;
    }
    
    /**
     * X-Content-Type-Optionsの設定
     */
    public function setXContentTypeOptions($value) {
        if ($value === 'nosniff') {
            $this->headers['X-Content-Type-Options'] = $value;
        }
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
        $allowedValues = [
            'no-referrer',
            'no-referrer-when-downgrade',
            'origin',
            'origin-when-cross-origin',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
            'unsafe-url'
        ];
        
        if (in_array($value, $allowedValues)) {
            $this->headers['Referrer-Policy'] = $value;
        }
        return $this;
    }
    
    /**
     * Permissions-Policyの設定
     */
    public function setPermissionsPolicy($policies) {
        $policyStrings = [];
        foreach ($policies as $feature => $allowlist) {
            if (is_array($allowlist)) {
                // 空の配列の場合は空の括弧を設定（機能を無効化）
                if (empty($allowlist)) {
                    $allowlist = '';
                } else {
                    $allowlist = implode(' ', $allowlist);
                }
            } else {
                // 空の文字列の場合は空の括弧を設定
                if (empty($allowlist)) {
                    $allowlist = '';
                }
            }
            
            // 空の場合は括弧のみ、値がある場合は値を括弧で囲む
            if (empty($allowlist)) {
                $policyStrings[] = $feature . '=()';
            } else {
                $policyStrings[] = $feature . '=(' . $allowlist . ')';
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
     * CSPディレクティブの追加
     */
    public function addCSPDirective($directive, $values) {
        if (!is_array($values)) {
            $values = [$values];
        }
        
        if (!isset($this->cspDirectives[$directive])) {
            $this->cspDirectives[$directive] = [];
        }
        
        $this->cspDirectives[$directive] = array_merge($this->cspDirectives[$directive], $values);
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
     * 本番環境用の設定（既存サイト対応）
     */
    public function setProductionMode() {
        // 既存サイトとの互換性を保つCSP（緊急対応版）
        $this->setContentSecurityPolicy([
            'default-src' => ["'self'"],
            'script-src' => [
                "'self'", 
                "'unsafe-inline'", 
                "https://cdn.jsdelivr.net", 
                "https://unpkg.com", 
                "https://www.googletagmanager.com",
                "https://www.google-analytics.com"
            ],
            'style-src' => [
                "'self'", 
                "'unsafe-inline'", 
                "https://cdn.jsdelivr.net", 
                "https://unpkg.com",
                "https://fonts.googleapis.com"
            ],
            'img-src' => ["'self'", "data:", "https:", "http:"],
            'font-src' => [
                "'self'", 
                "https://cdn.jsdelivr.net", 
                "https://unpkg.com",
                "https://fonts.gstatic.com"
            ],
            'connect-src' => [
                "'self'", 
                "https://www.google-analytics.com", 
                "https://cdn.jsdelivr.net", 
                "https://unpkg.com",
                "https://analytics.google.com"
            ],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
            'media-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'worker-src' => ["'self'"],
            'child-src' => ["'self'"]
        ]);
        
        // 本番環境用のX-Frame-Options設定（最も厳しい設定）
        $this->setXFrameOptions('DENY');
        
        // HSTSの有効化
        $this->setStrictTransportSecurity(31536000, true, true);
        
        // 本番環境用のPermissions-Policy設定
        $this->setPermissionsPolicy([
            'geolocation' => ['*'],
            'microphone' => [],
            'camera' => [],
            'payment' => [],
            'usb' => [],
            'magnetometer' => [],
            'gyroscope' => [],
            'fullscreen' => []
        ]);
        
        return $this;
    }
    
    /**
     * 開発環境用の緩い設定
     */
    public function setDevelopmentMode() {
        // 開発用の緩いCSP（デバッグ対応）
        $this->setContentSecurityPolicy([
            'default-src' => ["'self'"],
            'script-src' => [
                "'self'", 
                "'unsafe-inline'", 
                "'unsafe-eval'", 
                "https://cdn.jsdelivr.net", 
                "https://unpkg.com", 
                "https://www.googletagmanager.com",
                "https://www.google-analytics.com"
            ],
            'style-src' => [
                "'self'", 
                "'unsafe-inline'", 
                "https://cdn.jsdelivr.net", 
                "https://unpkg.com",
                "https://fonts.googleapis.com"
            ],
            'img-src' => ["'self'", "data:", "https:", "http:"],
            'font-src' => [
                "'self'", 
                "https://cdn.jsdelivr.net", 
                "https://unpkg.com",
                "https://fonts.gstatic.com"
            ],
            'connect-src' => [
                "'self'", 
                "https://www.google-analytics.com", 
                "https://cdn.jsdelivr.net", 
                "https://unpkg.com",
                "https://analytics.google.com"
            ],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
            'media-src' => ["'self'"],
            'manifest-src' => ["'self'"]
        ]);
        
        // 開発環境用のX-Frame-Options設定（緩い設定）
        $this->setXFrameOptions('SAMEORIGIN');
        
        // 開発環境用のPermissions-Policy設定
        $this->setPermissionsPolicy([
            'geolocation' => ['*'],
            'microphone' => [],
            'camera' => [],
            'payment' => [],
            'usb' => [],
            'magnetometer' => [],
            'gyroscope' => [],
            'fullscreen' => []
        ]);
        
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
