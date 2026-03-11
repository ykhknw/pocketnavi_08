<?php
/**
 * SameSite Cookie Manager
 * 
 * SameSite属性を持つCookieの管理クラス
 * CSRF攻撃を防ぐためのCookie設定を提供
 * 
 * @package PocketNavi
 * @subpackage Security
 */

class SameSiteCookieManager
{
    private static $instance = null;
    private $defaultOptions = [];
    private $isProduction = false;
    
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
        // 本番環境かどうかを判定
        $this->isProduction = !isset($_SERVER['HTTP_HOST']) || 
                             !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
        
        // デフォルト設定
        $this->defaultOptions = [
            'samesite' => 'Lax',           // SameSite属性（Strict, Lax, None）
            'secure' => $this->isProduction, // HTTPS環境でのみSecure
            'httponly' => true,            // JavaScriptからのアクセスを禁止
            'path' => '/',                 // Cookieの有効パス
            'domain' => null,              // ドメイン（nullの場合は自動設定）
        ];
    }
    
    /**
     * SameSite属性付きCookieを設定
     * 
     * @param string $name Cookie名
     * @param string $value Cookie値
     * @param array $options オプション
     * @return bool 設定結果
     */
    public function setCookie($name, $value, $options = [])
    {
        // オプションをマージ
        $options = array_merge($this->defaultOptions, $options);
        
        // 有効期限の設定
        $expire = $options['expire'] ?? 0;
        if (is_string($expire)) {
            $expire = strtotime($expire);
        }
        
        // ドメインの設定
        $domain = $options['domain'] ?? null;
        if ($domain === null) {
            $domain = $this->getDefaultDomain();
        }
        
        // Cookieオプションを構築
        $cookieOptions = [
            'expires' => $expire,
            'path' => $options['path'] ?? '/',
            'domain' => $domain,
            'secure' => $options['secure'] ?? false,
            'httponly' => $options['httponly'] ?? true,
            'samesite' => $options['samesite'] ?? 'Lax'
        ];
        
        // SameSite=Noneの場合はSecure必須
        if ($options['samesite'] === 'None' && !$options['secure']) {
            throw new InvalidArgumentException('SameSite=None requires Secure=true');
        }
        
        // Cookieを設定
        return setcookie($name, $value, $cookieOptions);
    }
    
    /**
     * セッションCookieを設定
     * 
     * @param array $options オプション
     * @return bool 設定結果
     */
    public function setSessionCookie($options = [])
    {
        // セッション用の設定
        $sessionOptions = array_merge([
            'samesite' => 'Lax',
            'secure' => $this->isProduction,
            'httponly' => true,
            'path' => '/',
        ], $options);
        
        // セッション設定を適用
        return $this->configureSession($sessionOptions);
    }
    
    /**
     * CSRFトークン用Cookieを設定
     * 
     * @param string $token CSRFトークン
     * @param array $options オプション
     * @return bool 設定結果
     */
    public function setCSRFTokenCookie($token, $options = [])
    {
        $csrfOptions = array_merge([
            'samesite' => 'Strict',        // CSRFトークンはStrict
            'secure' => $this->isProduction,
            'httponly' => true,
            'expire' => time() + 3600,     // 1時間
            'path' => '/',
        ], $options);
        
        return $this->setCookie('csrf_token', $token, $csrfOptions);
    }
    
    /**
     * 認証用Cookieを設定
     * 
     * @param string $name Cookie名
     * @param string $value Cookie値
     * @param array $options オプション
     * @return bool 設定結果
     */
    public function setAuthCookie($name, $value, $options = [])
    {
        $authOptions = array_merge([
            'samesite' => 'Strict',        // 認証CookieはStrict
            'secure' => $this->isProduction,
            'httponly' => true,
            'expire' => time() + (30 * 24 * 60 * 60), // 30日
            'path' => '/',
        ], $options);
        
        return $this->setCookie($name, $value, $authOptions);
    }
    
    /**
     * 分析用Cookieを設定（Google Analytics等）
     * 
     * @param string $name Cookie名
     * @param string $value Cookie値
     * @param array $options オプション
     * @return bool 設定結果
     */
    public function setAnalyticsCookie($name, $value, $options = [])
    {
        $analyticsOptions = array_merge([
            'samesite' => 'None',          // 分析CookieはNone（クロスサイト許可）
            'secure' => true,              // Noneの場合はSecure必須
            'httponly' => false,           // JavaScriptからアクセス可能
            'expire' => time() + (365 * 24 * 60 * 60), // 1年
            'path' => '/',
        ], $options);
        
        return $this->setCookie($name, $value, $analyticsOptions);
    }
    
    /**
     * Cookieを削除
     * 
     * @param string $name Cookie名
     * @param array $options オプション
     * @return bool 削除結果
     */
    public function deleteCookie($name, $options = [])
    {
        $deleteOptions = array_merge([
            'expire' => time() - 3600,     // 過去の日付で削除
            'path' => '/',
            'domain' => $this->getDefaultDomain(),
            'secure' => $this->isProduction,
            'httponly' => true,
            'samesite' => 'Lax',
        ], $options);
        
        return $this->setCookie($name, '', $deleteOptions);
    }
    
    /**
     * セッション設定を適用
     * 
     * @param array $options セッションオプション
     * @return bool 設定結果
     */
    private function configureSession($options)
    {
        // セッションが開始されていない場合は開始
        if (session_status() === PHP_SESSION_NONE) {
            // セッション設定
            ini_set('session.cookie_samesite', $options['samesite'] ?? 'Lax');
            ini_set('session.cookie_secure', ($options['secure'] ?? false) ? '1' : '0');
            ini_set('session.cookie_httponly', ($options['httponly'] ?? true) ? '1' : '0');
            ini_set('session.cookie_path', $options['path'] ?? '/');
            
            // ドメイン設定
            if (isset($options['domain']) && $options['domain']) {
                ini_set('session.cookie_domain', $options['domain']);
            }
            
            // セッション開始
            return session_start();
        }
        
        return true;
    }
    
    /**
     * デフォルトドメインを取得
     * 
     * @return string|null ドメイン
     */
    private function getDefaultDomain()
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            
            // ポート番号を除去
            if (strpos($host, ':') !== false) {
                $host = substr($host, 0, strpos($host, ':'));
            }
            
            // ローカルホストの場合はnull
            if (in_array($host, ['localhost', '127.0.0.1'])) {
                return null;
            }
            
            // サブドメインを除去してメインドメインのみ
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                return '.' . implode('.', array_slice($parts, -2));
            }
            
            return $host;
        }
        
        return null;
    }
    
    /**
     * 現在のCookie設定を取得
     * 
     * @return array Cookie設定
     */
    public function getCurrentSettings()
    {
        return [
            'is_production' => $this->isProduction,
            'default_options' => $this->defaultOptions,
            'session_settings' => [
                'cookie_samesite' => ini_get('session.cookie_samesite'),
                'cookie_secure' => ini_get('session.cookie_secure'),
                'cookie_httponly' => ini_get('session.cookie_httponly'),
                'cookie_path' => ini_get('session.cookie_path'),
                'cookie_domain' => ini_get('session.cookie_domain'),
            ],
            'current_domain' => $this->getDefaultDomain(),
        ];
    }
    
    /**
     * Cookie設定の検証
     * 
     * @return array 検証結果
     */
    public function validateSettings()
    {
        $issues = [];
        $settings = $this->getCurrentSettings();
        
        // SameSite=Noneの場合はSecure必須
        if ($settings['session_settings']['cookie_samesite'] === 'None' && 
            !$settings['session_settings']['cookie_secure']) {
            $issues[] = 'SameSite=None requires Secure=true';
        }
        
        // 本番環境でSecure=false
        if ($this->isProduction && !$settings['session_settings']['cookie_secure']) {
            $issues[] = 'Production environment should use Secure=true';
        }
        
        // HttpOnly=false
        if (!$settings['session_settings']['cookie_httponly']) {
            $issues[] = 'HttpOnly should be enabled for security';
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'settings' => $settings,
        ];
    }
    
    /**
     * デバッグ情報を取得
     * 
     * @return array デバッグ情報
     */
    public function getDebugInfo()
    {
        return [
            'cookie_manager' => $this->getCurrentSettings(),
            'validation' => $this->validateSettings(),
            'server_info' => [
                'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
            ],
            'cookies' => $_COOKIE ?? [],
        ];
    }
}
