<?php

/**
 * セキュリティミドルウェア
 */
class SecurityMiddleware {
    
    private $securityManager;
    private $authManager;
    
    public function __construct() {
        $this->securityManager = SecurityManager::getInstance();
        $this->authManager = AuthenticationManager::getInstance();
    }
    
    /**
     * リクエストの前処理
     */
    public function beforeRequest() {
        // セキュリティシステムの初期化
        $this->securityManager->initialize();
        
        // セッション検証
        if (!$this->authManager->validateSession()) {
            $this->handleUnauthorized();
        }
        
        // リクエストの検証
        $this->validateRequest();
        
        // セキュリティヘッダーの設定
        $this->setSecurityHeaders();
    }
    
    /**
     * レスポンスの後処理
     */
    public function afterResponse() {
        // セキュリティイベントのログ記録
        $this->logRequest();
        
        // セッションの更新
        $this->updateSession();
    }
    
    /**
     * リクエストの検証
     */
    private function validateRequest() {
        // HTTPメソッドの検証
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (!in_array($method, $allowedMethods)) {
            $this->securityManager->logSecurityEvent('INVALID_METHOD', "Invalid HTTP method: {$method}");
            $this->handleBadRequest();
        }
        
        // リクエストサイズの制限
        $maxSize = 10 * 1024 * 1024; // 10MB
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        
        if ($contentLength > $maxSize) {
            $this->securityManager->logSecurityEvent('REQUEST_TOO_LARGE', "Request size: {$contentLength} bytes");
            $this->handleRequestTooLarge();
        }
        
        // 危険なパラメータの検出
        $this->detectMaliciousInput();
    }
    
    /**
     * 悪意のある入力の検出
     */
    private function detectMaliciousInput() {
        $maliciousPatterns = [
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/union\s+select/i',
            '/drop\s+table/i',
            '/delete\s+from/i',
            '/insert\s+into/i',
            '/update\s+set/i',
            '/exec\s*\(/i',
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i'
        ];
        
        $inputs = array_merge($_GET, $_POST, $_COOKIE);
        
        foreach ($inputs as $key => $value) {
            if (is_string($value)) {
                foreach ($maliciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->securityManager->logSecurityEvent('MALICIOUS_INPUT_DETECTED', "Malicious input in {$key}: " . substr($value, 0, 100));
                        $this->handleMaliciousInput();
                    }
                }
            }
        }
    }
    
    /**
     * セキュリティヘッダーの設定
     */
    private function setSecurityHeaders() {
        // 既にSecurityManagerで設定されているが、追加のヘッダーを設定
        
        // キャッシュ制御（機密情報のキャッシュ防止）
        if ($this->isSensitivePage()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        
        // フレーム埋め込み防止（管理画面）
        if ($this->isAdminPage()) {
            header('X-Frame-Options: DENY');
        }
    }
    
    /**
     * 機密ページの判定
     */
    private function isSensitivePage() {
        $sensitivePaths = ['/admin/', '/api/', '/login', '/logout'];
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach ($sensitivePaths as $path) {
            if (strpos($requestUri, $path) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 管理画面ページの判定
     */
    private function isAdminPage() {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($requestUri, '/admin/') !== false;
    }
    
    /**
     * リクエストのログ記録
     */
    private function logRequest() {
        $user = $this->authManager->getCurrentUser();
        $username = $user ? $user['username'] : 'anonymous';
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'username' => $username,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'status_code' => http_response_code()
        ];
        
        $this->securityManager->logSecurityEvent('REQUEST_COMPLETED', json_encode($logData));
    }
    
    /**
     * セッションの更新
     */
    private function updateSession() {
        if (isset($_SESSION['user_id'])) {
            $_SESSION['last_activity'] = time();
        }
    }
    
    /**
     * 未認証時の処理
     */
    private function handleUnauthorized() {
        if ($this->isApiRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized', 'message' => '認証が必要です。']);
            exit;
        } else {
            // ログインページにリダイレクト
            $loginUrl = '/admin/login.php';
            header("Location: {$loginUrl}");
            exit;
        }
    }
    
    /**
     * 不正なリクエスト時の処理
     */
    private function handleBadRequest() {
        http_response_code(400);
        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Bad Request', 'message' => '不正なリクエストです。']);
        } else {
            echo '<h1>400 Bad Request</h1><p>不正なリクエストです。</p>';
        }
        exit;
    }
    
    /**
     * リクエストサイズ超過時の処理
     */
    private function handleRequestTooLarge() {
        http_response_code(413);
        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Request Too Large', 'message' => 'リクエストサイズが大きすぎます。']);
        } else {
            echo '<h1>413 Request Too Large</h1><p>リクエストサイズが大きすぎます。</p>';
        }
        exit;
    }
    
    /**
     * 悪意のある入力検出時の処理
     */
    private function handleMaliciousInput() {
        http_response_code(400);
        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Bad Request', 'message' => '不正な入力が検出されました。']);
        } else {
            echo '<h1>400 Bad Request</h1><p>不正な入力が検出されました。</p>';
        }
        exit;
    }
    
    /**
     * APIリクエストの判定
     */
    private function isApiRequest() {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($requestUri, '/api/') !== false;
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
     * CSRFトークンの検証（POSTリクエスト用）
     */
    public function validateCsrfToken() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            
            if (!$this->securityManager->validateCsrfToken($token)) {
                $this->securityManager->logSecurityEvent('CSRF_VALIDATION_FAILED', 'CSRF token validation failed');
                $this->handleBadRequest();
            }
        }
    }
    
    /**
     * 管理者権限のチェック
     */
    public function requireAdmin() {
        if (!$this->authManager->isAdmin()) {
            $this->securityManager->logSecurityEvent('ADMIN_ACCESS_DENIED', 'Non-admin user attempted admin access');
            $this->handleUnauthorized();
        }
    }
    
    /**
     * セキュリティレポートの生成
     */
    public function generateSecurityReport() {
        return [
            'security_manager' => $this->securityManager->generateSecurityReport(),
            'authentication' => [
                'current_user' => $this->authManager->getCurrentUser(),
                'is_admin' => $this->authManager->isAdmin()
            ],
            'request_info' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'is_api' => $this->isApiRequest(),
                'is_admin_page' => $this->isAdminPage(),
                'is_sensitive' => $this->isSensitivePage()
            ]
        ];
    }
}
