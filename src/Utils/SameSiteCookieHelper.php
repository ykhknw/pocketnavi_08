<?php
/**
 * SameSite Cookie Helper Functions
 * 
 * SameSite属性を持つCookieに関するヘルパー関数を提供
 * 
 * @package PocketNavi
 * @subpackage Utils
 */

require_once __DIR__ . '/../Security/SameSiteCookieManager.php';
require_once __DIR__ . '/../../config/samesite_cookie_config.php';

/**
 * SameSite Cookie Managerのインスタンスを取得
 */
function getSameSiteCookieManager()
{
    return SameSiteCookieManager::getInstance();
}

/**
 * 設定ファイルを読み込み
 */
function getSameSiteCookieConfig()
{
    return require __DIR__ . '/../../config/samesite_cookie_config.php';
}

/**
 * セッションCookieを設定
 * 
 * @param array $options オプション
 * @return bool 設定結果
 */
function setSameSiteSessionCookie($options = [])
{
    $manager = getSameSiteCookieManager();
    return $manager->setSessionCookie($options);
}

/**
 * CSRFトークン用Cookieを設定
 * 
 * @param string $token CSRFトークン
 * @param array $options オプション
 * @return bool 設定結果
 */
function setSameSiteCSRFTokenCookie($token, $options = [])
{
    $manager = getSameSiteCookieManager();
    return $manager->setCSRFTokenCookie($token, $options);
}

/**
 * 認証用Cookieを設定
 * 
 * @param string $name Cookie名
 * @param string $value Cookie値
 * @param array $options オプション
 * @return bool 設定結果
 */
function setSameSiteAuthCookie($name, $value, $options = [])
{
    $manager = getSameSiteCookieManager();
    return $manager->setAuthCookie($name, $value, $options);
}

/**
 * 分析用Cookieを設定
 * 
 * @param string $name Cookie名
 * @param string $value Cookie値
 * @param array $options オプション
 * @return bool 設定結果
 */
function setSameSiteAnalyticsCookie($name, $value, $options = [])
{
    $manager = getSameSiteCookieManager();
    return $manager->setAnalyticsCookie($name, $value, $options);
}

/**
 * 機能別Cookieを設定
 * 
 * @param string $feature 機能名
 * @param string $name Cookie名
 * @param string $value Cookie値
 * @param array $options オプション
 * @return bool 設定結果
 */
function setSameSiteFeatureCookie($feature, $name, $value, $options = [])
{
    $config = getSameSiteCookieConfig();
    $manager = getSameSiteCookieManager();
    
    // 機能別設定を取得
    $featureConfig = $config['features'][$feature] ?? $config['defaults'];
    
    // オプションをマージ
    $mergedOptions = array_merge($featureConfig, $options);
    
    return $manager->setCookie($name, $value, $mergedOptions);
}

/**
 * Cookieを削除
 * 
 * @param string $name Cookie名
 * @param array $options オプション
 * @return bool 削除結果
 */
function deleteSameSiteCookie($name, $options = [])
{
    $manager = getSameSiteCookieManager();
    return $manager->deleteCookie($name, $options);
}

/**
 * 言語設定Cookieを設定
 * 
 * @param string $language 言語コード
 * @return bool 設定結果
 */
function setLanguageCookie($language)
{
    return setSameSiteFeatureCookie('language', 'preferred_language', $language);
}

/**
 * テーマ設定Cookieを設定
 * 
 * @param string $theme テーマ名
 * @return bool 設定結果
 */
function setThemeCookie($theme)
{
    return setSameSiteFeatureCookie('theme', 'preferred_theme', $theme);
}

/**
 * 検索履歴Cookieを設定
 * 
 * @param array $history 検索履歴
 * @return bool 設定結果
 */
function setSearchHistoryCookie($history)
{
    $jsonHistory = json_encode($history, JSON_UNESCAPED_UNICODE);
    return setSameSiteFeatureCookie('search_history', 'search_history', $jsonHistory);
}

/**
 * ユーザー設定Cookieを設定
 * 
 * @param array $preferences ユーザー設定
 * @return bool 設定結果
 */
function setUserPreferencesCookie($preferences)
{
    $jsonPreferences = json_encode($preferences, JSON_UNESCAPED_UNICODE);
    return setSameSiteFeatureCookie('user_preferences', 'user_preferences', $jsonPreferences);
}

/**
 * 現在のCookie設定を取得
 * 
 * @return array Cookie設定
 */
function getSameSiteCookieSettings()
{
    $manager = getSameSiteCookieManager();
    return $manager->getCurrentSettings();
}

/**
 * Cookie設定の検証
 * 
 * @return array 検証結果
 */
function validateSameSiteCookieSettings()
{
    $manager = getSameSiteCookieManager();
    return $manager->validateSettings();
}

/**
 * セッションを安全に開始
 * 
 * @param array $options セッションオプション
 * @return bool 開始結果
 */
function startSecureSession($options = [])
{
    $config = getSameSiteCookieConfig();
    $sessionConfig = array_merge($config['session'], $options);
    
    // セッション設定を適用
    $manager = getSameSiteCookieManager();
    $result = $manager->setSessionCookie($sessionConfig);
    
    // セッション固定攻撃対策
    if ($config['security']['regenerate_session_id'] && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    
    return $result;
}

/**
 * セッションを安全に終了
 * 
 * @param bool $destroySession セッションを破棄するか
 * @return bool 終了結果
 */
function endSecureSession($destroySession = true)
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if ($destroySession) {
            // セッションCookieを削除
            $manager = getSameSiteCookieManager();
            $manager->deleteCookie(session_name());
            
            // セッションを破棄
            session_destroy();
        } else {
            session_write_close();
        }
        return true;
    }
    
    return false;
}

/**
 * セッションタイムアウトをチェック
 * 
 * @return bool タイムアウトしている場合true
 */
function checkSessionTimeout()
{
    $config = getSameSiteCookieConfig();
    $timeout = $config['security']['session_timeout'];
    
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout) {
            return true;
        }
    }
    
    // 最終アクティビティを更新
    $_SESSION['last_activity'] = time();
    return false;
}

/**
 * セッションの有効性をチェック
 * 
 * @return bool セッションが有効な場合true
 */
function validateSession()
{
    // セッションが開始されていない
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // セッションタイムアウト
    if (checkSessionTimeout()) {
        endSecureSession(true);
        return false;
    }
    
    // セッションIDの検証
    if (isset($_SESSION['session_id']) && $_SESSION['session_id'] !== session_id()) {
        endSecureSession(true);
        return false;
    }
    
    // セッションIDを保存
    $_SESSION['session_id'] = session_id();
    
    return true;
}

/**
 * デバッグ情報を取得
 * 
 * @return array デバッグ情報
 */
function getSameSiteCookieDebugInfo()
{
    $manager = getSameSiteCookieManager();
    return $manager->getDebugInfo();
}

/**
 * Cookie設定のHTML出力
 * 
 * @return string HTML
 */
function getSameSiteCookieInfoHTML()
{
    // デバッグ情報は非表示（debug=1パラメータでも表示しない）
    return '';
    
    // 以下はコメントアウト（必要に応じて復活可能）
    /*
    $config = getSameSiteCookieConfig();
    $settings = getSameSiteCookieSettings();
    $validation = validateSameSiteCookieSettings();
    
    if (!$config['debug']['show_cookie_info']) {
        return '';
    }
    
    $html = '<div class="samesite-cookie-info" style="background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px;">';
    $html .= '<h6>SameSite Cookie Info</h6>';
    $html .= '<p><strong>Environment:</strong> ' . ($settings['is_production'] ? 'Production' : 'Development') . '</p>';
    $html .= '<p><strong>SameSite:</strong> ' . $settings['session_settings']['cookie_samesite'] . '</p>';
    $html .= '<p><strong>Secure:</strong> ' . ($settings['session_settings']['cookie_secure'] ? 'Yes' : 'No') . '</p>';
    $html .= '<p><strong>HttpOnly:</strong> ' . ($settings['session_settings']['cookie_httponly'] ? 'Yes' : 'No') . '</p>';
    
    if (!$validation['valid']) {
        $html .= '<p><strong>Issues:</strong> ' . implode(', ', $validation['issues']) . '</p>';
    }
    
    $html .= '</div>';
    
    return $html;
    */
}
?>
