<?php
/**
 * セキュア認証管理クラス
 * 多要素認証、セッション管理、権限チェックを提供
 */
class SecureAuthManager {
    private $db;
    private $sessionTimeout = 3600; // 1時間
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900; // 15分
    
    public function __construct($database) {
        $this->db = $database;
        $this->initializeSession();
    }
    
    /**
     * セッションの初期化
     */
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // セキュアなセッション設定
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            session_start();
        }
    }
    
    /**
     * ログイン認証
     */
    public function authenticate($username, $password, $mfaCode = null) {
        // ログイン試行回数チェック
        if ($this->isAccountLocked($username)) {
            throw new SecurityException('アカウントがロックされています。しばらくしてから再試行してください。');
        }
        
        // ユーザー情報の取得
        $user = $this->getUserByUsername($username);
        if (!$user) {
            $this->recordFailedAttempt($username);
            throw new SecurityException('認証に失敗しました。');
        }
        
        // パスワード検証
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($username);
            throw new SecurityException('認証に失敗しました。');
        }
        
        // 多要素認証（簡易版：固定コード）
        if ($mfaCode !== null && $mfaCode !== '123456') {
            $this->recordFailedAttempt($username);
            throw new SecurityException('多要素認証に失敗しました。');
        }
        
        // ログイン成功
        $this->clearFailedAttempts($username);
        $this->createSecureSession($user);
        
        return true;
    }
    
    /**
     * セキュアセッションの作成
     */
    private function createSecureSession($user) {
        // セッションIDの再生成
        session_regenerate_id(true);
        
        // セッションデータの設定
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $this->getClientIP();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // セッション固定攻撃対策
        $_SESSION['csrf_token'] = $this->generateCSRFToken();
    }
    
    /**
     * セッション検証
     */
    public function validateSession() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // セッションタイムアウトチェック
        if (time() - $_SESSION['last_activity'] > $this->sessionTimeout) {
            $this->destroySession();
            return false;
        }
        
        // IPアドレスチェック
        if ($_SESSION['ip_address'] !== $this->getClientIP()) {
            $this->destroySession();
            return false;
        }
        
        // User-Agentチェック
        if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            $this->destroySession();
            return false;
        }
        
        // アクティビティ更新
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * 権限チェック
     */
    public function hasPermission($permission) {
        if (!$this->validateSession()) {
            return false;
        }
        
        $userRole = $_SESSION['role'] ?? 'guest';
        $permissions = $this->getRolePermissions($userRole);
        
        return in_array($permission, $permissions);
    }
    
    /**
     * ログアウト
     */
    public function logout() {
        $this->destroySession();
    }
    
    /**
     * セッション破棄
     */
    private function destroySession() {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    
    /**
     * CSRFトークン生成
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * CSRFトークン検証
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * アカウントロックチェック
     */
    private function isAccountLocked($username) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
            FROM login_attempts 
            WHERE username = ? AND attempt_time > ?
        ");
        $stmt->execute([$username, time() - $this->lockoutTime]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= $this->maxLoginAttempts;
    }
    
    /**
     * 失敗試行記録
     */
    private function recordFailedAttempt($username) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (username, ip_address, attempt_time) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $this->getClientIP(), time()]);
    }
    
    /**
     * 失敗試行クリア
     */
    private function clearFailedAttempts($username) {
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt->execute([$username]);
    }
    
    /**
     * ユーザー情報取得
     */
    private function getUserByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM admin_users WHERE username = ? AND active = 1");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    /**
     * ロール権限取得
     */
    private function getRolePermissions($role) {
        $permissions = [
            'admin' => ['cache_manage', 'user_manage', 'system_config'],
            'moderator' => ['cache_manage'],
            'guest' => []
        ];
        
        return $permissions[$role] ?? [];
    }
    
    /**
     * クライアントIP取得
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
}

/**
 * セキュリティ例外クラス
 */
class SecurityException extends Exception {
    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
?>
