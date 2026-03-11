<?php
/**
 * CSRF Protection Class
 * 
 * CSRF（Cross-Site Request Forgery）攻撃を防ぐためのトークン管理クラス
 * 
 * @package PocketNavi
 * @subpackage Security
 */

class CSRFProtection
{
    private static $instance = null;
    private $sessionKey = 'csrf_tokens';
    private $tokenLifetime = 3600; // 1時間
    
    /**
     * シングルトンパターンでインスタンスを取得
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct()
    {
        // セッションが開始されていない場合は開始
        if (session_status() === PHP_SESSION_NONE) {
            // ヘッダーが送信されていない場合のみセッション開始
            if (!headers_sent()) {
                session_start();
            }
        }
        
        // CSRFトークン配列を初期化
        if (!isset($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }
        
        // 古いトークンをクリーンアップ
        $this->cleanupExpiredTokens();
    }
    
    /**
     * CSRFトークンを生成
     * 
     * @param string $action アクション名（オプション）
     * @return string 生成されたCSRFトークン
     */
    public function generateToken($action = 'default')
    {
        // ランダムなトークンを生成
        $token = bin2hex(random_bytes(32));
        
        // トークンの有効期限を設定
        $expires = time() + $this->tokenLifetime;
        
        // セッションにトークンを保存
        $_SESSION[$this->sessionKey][$action] = [
            'token' => $token,
            'expires' => $expires,
            'created' => time()
        ];
        
        return $token;
    }
    
    /**
     * CSRFトークンを検証
     * 
     * @param string $token 検証するトークン
     * @param string $action アクション名（オプション）
     * @return bool 検証結果
     */
    public function validateToken($token, $action = 'default')
    {
        // セッションにトークンが存在するかチェック
        if (!isset($_SESSION[$this->sessionKey][$action])) {
            return false;
        }
        
        $storedToken = $_SESSION[$this->sessionKey][$action];
        
        // トークンが一致するかチェック
        if (!hash_equals($storedToken['token'], $token)) {
            return false;
        }
        
        // トークンの有効期限をチェック
        if (time() > $storedToken['expires']) {
            // 期限切れのトークンを削除
            unset($_SESSION[$this->sessionKey][$action]);
            return false;
        }
        
        return true;
    }
    
    /**
     * CSRFトークンを取得（存在しない場合は生成）
     * 
     * @param string $action アクション名（オプション）
     * @return string CSRFトークン
     */
    public function getToken($action = 'default')
    {
        // 既存のトークンが有効かチェック
        if (isset($_SESSION[$this->sessionKey][$action])) {
            $storedToken = $_SESSION[$this->sessionKey][$action];
            
            // トークンが有効期限内の場合
            if (time() <= $storedToken['expires']) {
                return $storedToken['token'];
            }
        }
        
        // 新しいトークンを生成
        return $this->generateToken($action);
    }
    
    /**
     * 使用済みトークンを削除
     * 
     * @param string $action アクション名（オプション）
     */
    public function consumeToken($action = 'default')
    {
        if (isset($_SESSION[$this->sessionKey][$action])) {
            unset($_SESSION[$this->sessionKey][$action]);
        }
    }
    
    /**
     * 期限切れのトークンをクリーンアップ
     */
    private function cleanupExpiredTokens()
    {
        $currentTime = time();
        
        foreach ($_SESSION[$this->sessionKey] as $action => $tokenData) {
            if ($currentTime > $tokenData['expires']) {
                unset($_SESSION[$this->sessionKey][$action]);
            }
        }
    }
    
    /**
     * すべてのCSRFトークンをクリア
     */
    public function clearAllTokens()
    {
        $_SESSION[$this->sessionKey] = [];
    }
    
    /**
     * トークンの有効期限を設定
     * 
     * @param int $lifetime 有効期限（秒）
     */
    public function setTokenLifetime($lifetime)
    {
        $this->tokenLifetime = $lifetime;
    }
    
    /**
     * 現在のトークン数を取得
     * 
     * @return int トークン数
     */
    public function getTokenCount()
    {
        return count($_SESSION[$this->sessionKey]);
    }
    
    /**
     * デバッグ用：トークン情報を取得
     * 
     * @return array トークン情報
     */
    public function getDebugInfo()
    {
        $info = [
            'token_count' => $this->getTokenCount(),
            'tokens' => []
        ];
        
        foreach ($_SESSION[$this->sessionKey] as $action => $tokenData) {
            $info['tokens'][$action] = [
                'created' => date('Y-m-d H:i:s', $tokenData['created']),
                'expires' => date('Y-m-d H:i:s', $tokenData['expires']),
                'is_expired' => time() > $tokenData['expires']
            ];
        }
        
        return $info;
    }
}
