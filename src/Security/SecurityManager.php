<?php

/**
 * 統合セキュリティ管理システム
 */
class SecurityManager {
    
    private static $instance = null;
    private $config;
    private $logger;
    
    private function __construct() {
        $this->config = [
            'headers' => [
                'enabled' => true,
                'csp' => true,
                'hsts' => true,
                'cors' => false
            ],
            'rate_limiting' => [
                'enabled' => true,
                'max_requests' => 100,
                'time_window' => 3600,
                'block_duration' => 1800
            ],
            'csrf' => [
                'enabled' => true,
                'token_length' => 32,
                'regenerate_on_login' => true
            ],
            'session' => [
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict',
                'lifetime' => 7200
            ],
            'logging' => [
                'enabled' => true,
                'log_level' => 'INFO',
                'log_file' => 'logs/security.log'
            ]
        ];
        
        $this->initializeLogger();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * セキュリティシステムの初期化
     */
    public function initialize() {
        $this->setSecurityHeaders();
        $this->configureSession();
        $this->checkRateLimit();
        $this->logSecurityEvent('SYSTEM_INIT', 'Security system initialized');
    }
    
    /**
     * セキュリティヘッダーの設定
     */
    private function setSecurityHeaders() {
        if (!$this->config['headers']['enabled']) {
            return;
        }
        
        // 基本的なセキュリティヘッダー
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=*, microphone=(), camera=(), payment=()');
        
        // HSTS（HTTPS環境のみ）
        if ($this->config['headers']['hsts'] && $this->isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy
        if ($this->config['headers']['csp']) {
            $this->setContentSecurityPolicy();
        }
        
        // CORS設定
        if ($this->config['headers']['cors']) {
            $this->setCorsHeaders();
        }
        
        // 追加のセキュリティヘッダー
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
    }
    
    /**
     * Content Security Policyの設定
     */
    private function setContentSecurityPolicy() {
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net https://www.googletagmanager.com",
            "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://unpkg.com",
            "connect-src 'self' https: https://www.google-analytics.com https://analytics.google.com",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests"
        ];
        
        header("Content-Security-Policy: " . implode('; ', $csp));
    }
    
    /**
     * CORSヘッダーの設定
     */
    private function setCorsHeaders() {
        $allowedOrigins = ['http://localhost:8000', 'https://yourdomain.com'];
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
     * セッション設定
     */
    private function configureSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        $sessionConfig = $this->config['session'];
        
        // セッション設定
        ini_set('session.cookie_secure', $sessionConfig['secure'] ? '1' : '0');
        ini_set('session.cookie_httponly', $sessionConfig['httponly'] ? '1' : '0');
        ini_set('session.cookie_samesite', $sessionConfig['samesite']);
        ini_set('session.gc_maxlifetime', $sessionConfig['lifetime']);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        
        session_start();
        
        // セッション固定攻撃対策
        if (!isset($_SESSION['session_regenerated'])) {
            session_regenerate_id(true);
            $_SESSION['session_regenerated'] = true;
        }
    }
    
    /**
     * レート制限チェック
     */
    private function checkRateLimit() {
        if (!$this->config['rate_limiting']['enabled']) {
            return;
        }
        
        $identifier = $this->getClientIdentifier();
        $key = 'rate_limit_' . md5($identifier);
        
        $now = time();
        $requests = $_SESSION[$key] ?? [];
        $timeWindow = $this->config['rate_limiting']['time_window'];
        $maxRequests = $this->config['rate_limiting']['max_requests'];
        
        // 古いリクエストを削除
        $requests = array_filter($requests, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        // リクエスト数チェック
        if (count($requests) >= $maxRequests) {
            $this->logSecurityEvent('RATE_LIMIT_EXCEEDED', "Rate limit exceeded for {$identifier}");
            $this->handleRateLimitExceeded();
            return;
        }
        
        // 現在のリクエストを記録
        $requests[] = $now;
        $_SESSION[$key] = $requests;
    }
    
    /**
     * レート制限超過時の処理
     */
    private function handleRateLimitExceeded() {
        http_response_code(429);
        header('Retry-After: ' . $this->config['rate_limiting']['block_duration']);
        
        $response = [
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $this->config['rate_limiting']['block_duration']
        ];
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * CSRFトークンの生成
     */
    public function generateCsrfToken() {
        if (!$this->config['csrf']['enabled']) {
            return null;
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes($this->config['csrf']['token_length']));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * CSRFトークンの検証
     */
    public function validateCsrfToken($token) {
        if (!$this->config['csrf']['enabled']) {
            return true;
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        $isValid = hash_equals($_SESSION['csrf_token'], $token);
        
        if (!$isValid) {
            $this->logSecurityEvent('CSRF_TOKEN_INVALID', 'Invalid CSRF token provided');
        }
        
        return $isValid;
    }
    
    /**
     * セキュリティイベントのログ記録
     */
    public function logSecurityEvent($event, $details = '', $level = 'INFO') {
        if (!$this->config['logging']['enabled']) {
            return;
        }
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'event' => $event,
            'details' => $details,
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        $logMessage = json_encode($logEntry) . "\n";
        file_put_contents($this->config['logging']['log_file'], $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * クライアント識別子の取得
     */
    private function getClientIdentifier() {
        $ip = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return md5($ip . $userAgent);
    }
    
    /**
     * クライアントIPアドレスの取得
     */
    private function getClientIp() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return 'unknown';
    }
    
    /**
     * HTTPS接続の確認
     */
    private function isHttps() {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
    }
    
    /**
     * ロガーの初期化
     */
    private function initializeLogger() {
        $logDir = dirname($this->config['logging']['log_file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * 設定の取得
     */
    public function getConfig($key = null) {
        if ($key === null) {
            return $this->config;
        }
        
        return $this->config[$key] ?? null;
    }
    
    /**
     * 設定の更新
     */
    public function setConfig($key, $value) {
        $this->config[$key] = $value;
    }
    
    /**
     * セキュリティレポートの生成
     */
    public function generateSecurityReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'config' => $this->config,
            'session_info' => [
                'session_id' => session_id(),
                'session_status' => session_status(),
                'session_regenerated' => $_SESSION['session_regenerated'] ?? false
            ],
            'request_info' => [
                'ip' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'https' => $this->isHttps(),
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
            ]
        ];
        
        return $report;
    }
}
