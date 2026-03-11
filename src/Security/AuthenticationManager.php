<?php

/**
 * 認証管理システム
 */
class AuthenticationManager {
    
    private static $instance = null;
    private $db;
    private $securityManager;
    private $config;
    
    private function __construct() {
        $this->securityManager = SecurityManager::getInstance();
        $this->config = [
            'password' => [
                'min_length' => 8,
                'require_uppercase' => true,
                'require_lowercase' => true,
                'require_numbers' => true,
                'require_symbols' => false,
                'max_age_days' => 90
            ],
            'session' => [
                'timeout' => 7200, // 2時間
                'regenerate_interval' => 1800, // 30分
                'max_concurrent' => 3
            ],
            'login' => [
                'max_attempts' => 5,
                'lockout_duration' => 1800, // 30分
                'require_2fa' => false
            ]
        ];
        
        $this->initializeDatabase();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * データベースの初期化
     */
    private function initializeDatabase() {
        try {
            // データベース設定の確認
            if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
                throw new Exception("Database configuration not defined");
            }
            
            $this->db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            $this->createTablesIfNotExist();
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    /**
     * 必要なテーブルの作成
     */
    private function createTablesIfNotExist() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                salt VARCHAR(32) NOT NULL,
                role ENUM('admin', 'user') DEFAULT 'user',
                is_active BOOLEAN DEFAULT TRUE,
                last_login TIMESTAMP NULL,
                password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                success BOOLEAN NOT NULL,
                attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                user_agent TEXT,
                INDEX idx_username_ip (username, ip_address),
                INDEX idx_attempted_at (attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            "CREATE TABLE IF NOT EXISTS user_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                session_id VARCHAR(128) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_session_id (session_id),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
        
        foreach ($queries as $query) {
            $this->db->exec($query);
        }
    }
    
    /**
     * ユーザー認証
     */
    public function authenticate($username, $password) {
        // ログイン試行制限チェック
        if (!$this->checkLoginAttempts($username)) {
            $this->securityManager->logSecurityEvent('LOGIN_BLOCKED', "Login blocked for user: {$username}");
            return ['success' => false, 'message' => 'アカウントが一時的にロックされています。しばらくしてから再試行してください。'];
        }
        
        try {
            // ユーザー情報の取得
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->recordLoginAttempt($username, false);
                $this->securityManager->logSecurityEvent('LOGIN_FAILED', "Invalid username: {$username}");
                return ['success' => false, 'message' => 'ユーザー名またはパスワードが正しくありません。'];
            }
            
            // パスワード検証
            $hashedPassword = $this->hashPassword($password, $user['salt']);
            if (!hash_equals($user['password_hash'], $hashedPassword)) {
                $this->recordLoginAttempt($username, false);
                $this->securityManager->logSecurityEvent('LOGIN_FAILED', "Invalid password for user: {$username}");
                return ['success' => false, 'message' => 'ユーザー名またはパスワードが正しくありません。'];
            }
            
            // パスワードの有効期限チェック
            if ($this->isPasswordExpired($user['password_changed_at'])) {
                $this->securityManager->logSecurityEvent('PASSWORD_EXPIRED', "Password expired for user: {$username}");
                return ['success' => false, 'message' => 'パスワードの有効期限が切れています。パスワードを変更してください。', 'password_expired' => true];
            }
            
            // ログイン成功
            $this->recordLoginAttempt($username, true);
            $this->createUserSession($user);
            $this->updateLastLogin($user['id']);
            
            $this->securityManager->logSecurityEvent('LOGIN_SUCCESS', "Successful login for user: {$username}");
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ];
            
        } catch (Exception $e) {
            $this->securityManager->logSecurityEvent('LOGIN_ERROR', "Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'ログイン処理中にエラーが発生しました。'];
        }
    }
    
    /**
     * ログイン試行制限のチェック
     */
    private function checkLoginAttempts($username) {
        $ip = $this->getClientIp();
        $timeLimit = date('Y-m-d H:i:s', time() - $this->config['login']['lockout_duration']);
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE username = ? AND ip_address = ? AND success = FALSE AND attempted_at > ?
        ");
        $stmt->execute([$username, $ip, $timeLimit]);
        $result = $stmt->fetch();
        
        return $result['attempts'] < $this->config['login']['max_attempts'];
    }
    
    /**
     * ログイン試行の記録
     */
    private function recordLoginAttempt($username, $success) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (username, ip_address, success, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $username,
            $this->getClientIp(),
            $success ? 1 : 0,
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    /**
     * ユーザーセッションの作成
     */
    private function createUserSession($user) {
        // 既存のセッションをクリーンアップ
        $this->cleanupUserSessions($user['id']);
        
        // 新しいセッションを作成
        $sessionId = session_id();
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['session']['timeout']);
        
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $sessionId,
            $this->getClientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiresAt
        ]);
        
        // セッション情報を保存
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * ユーザーセッションのクリーンアップ
     */
    private function cleanupUserSessions($userId) {
        // 期限切れセッションの削除
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ? AND expires_at < NOW()");
        $stmt->execute([$userId]);
        
        // 最大同時セッション数の制限
        $stmt = $this->db->prepare("
            SELECT id FROM user_sessions 
            WHERE user_id = ? AND is_active = TRUE 
            ORDER BY last_activity DESC 
            LIMIT 999 OFFSET ?
        ");
        $stmt->execute([$userId, $this->config['session']['max_concurrent'] - 1]);
        $sessionsToDeactivate = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($sessionsToDeactivate)) {
            $placeholders = str_repeat('?,', count($sessionsToDeactivate) - 1) . '?';
            $stmt = $this->db->prepare("UPDATE user_sessions SET is_active = FALSE WHERE id IN ({$placeholders})");
            $stmt->execute($sessionsToDeactivate);
        }
    }
    
    /**
     * セッションの検証
     */
    public function validateSession() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // セッションタイムアウトチェック
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > $this->config['session']['timeout']) {
            $this->logout();
            return false;
        }
        
        // セッションIDの再生成（定期的に）
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > $this->config['session']['regenerate_interval']) {
            session_regenerate_id(true);
        }
        
        // データベースでのセッション検証
        $stmt = $this->db->prepare("
            SELECT us.*, u.username, u.role 
            FROM user_sessions us 
            JOIN users u ON us.user_id = u.id 
            WHERE us.session_id = ? AND us.is_active = TRUE AND us.expires_at > NOW()
        ");
        $stmt->execute([session_id()]);
        $session = $stmt->fetch();
        
        if (!$session) {
            $this->logout();
            return false;
        }
        
        // アクティビティの更新
        $_SESSION['last_activity'] = time();
        $stmt = $this->db->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$session['id']]);
        
        return true;
    }
    
    /**
     * ログアウト処理
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            // データベースのセッションを無効化
            $stmt = $this->db->prepare("UPDATE user_sessions SET is_active = FALSE WHERE session_id = ?");
            $stmt->execute([session_id()]);
            
            $this->securityManager->logSecurityEvent('LOGOUT', "User logout: {$_SESSION['username']}");
        }
        
        // セッションの破棄
        session_destroy();
        session_start();
    }
    
    /**
     * パスワードのハッシュ化
     */
    private function hashPassword($password, $salt) {
        return hash('sha256', $password . $salt);
    }
    
    /**
     * パスワードの有効期限チェック
     */
    private function isPasswordExpired($passwordChangedAt) {
        $maxAge = $this->config['password']['max_age_days'] * 24 * 60 * 60;
        return (time() - strtotime($passwordChangedAt)) > $maxAge;
    }
    
    /**
     * 最終ログイン時刻の更新
     */
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
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
     * ユーザー作成（管理者用）
     */
    public function createUser($username, $email, $password, $role = 'user') {
        // パスワード強度チェック
        if (!$this->validatePasswordStrength($password)) {
            return ['success' => false, 'message' => 'パスワードが要件を満たしていません。'];
        }
        
        // ユーザー名の重複チェック
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'ユーザー名またはメールアドレスが既に使用されています。'];
        }
        
        // パスワードのハッシュ化
        $salt = bin2hex(random_bytes(16));
        $passwordHash = $this->hashPassword($password, $salt);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, salt, role) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $passwordHash, $salt, $role]);
            
            $this->securityManager->logSecurityEvent('USER_CREATED', "User created: {$username}");
            
            return ['success' => true, 'message' => 'ユーザーが正常に作成されました。'];
            
        } catch (Exception $e) {
            $this->securityManager->logSecurityEvent('USER_CREATION_ERROR', "User creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'ユーザー作成中にエラーが発生しました。'];
        }
    }
    
    /**
     * パスワード強度の検証
     */
    private function validatePasswordStrength($password) {
        $config = $this->config['password'];
        
        if (strlen($password) < $config['min_length']) {
            return false;
        }
        
        $checks = [
            'uppercase' => $config['require_uppercase'] ? preg_match('/[A-Z]/', $password) : true,
            'lowercase' => $config['require_lowercase'] ? preg_match('/[a-z]/', $password) : true,
            'numbers' => $config['require_numbers'] ? preg_match('/[0-9]/', $password) : true,
            'symbols' => $config['require_symbols'] ? preg_match('/[^a-zA-Z0-9]/', $password) : true
        ];
        
        return array_reduce($checks, function($carry, $check) {
            return $carry && $check;
        }, true);
    }
    
    /**
     * 現在のユーザー情報の取得
     */
    public function getCurrentUser() {
        if (!$this->validateSession()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ];
    }
    
    /**
     * 管理者権限のチェック
     */
    public function isAdmin() {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === 'admin';
    }
}
