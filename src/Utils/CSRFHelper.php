<?php
/**
 * CSRF Helper Functions
 * 
 * CSRFトークンに関するヘルパー関数を提供
 * 
 * @package PocketNavi
 * @subpackage Utils
 */

require_once __DIR__ . '/../Security/CSRFProtection.php';

/**
 * CSRFトークンを取得
 * 
 * @param string $action アクション名（オプション）
 * @return string CSRFトークン
 */
function getCSRFToken($action = 'default')
{
    $csrf = CSRFProtection::getInstance();
    return $csrf->getToken($action);
}

/**
 * CSRFトークンを検証
 * 
 * @param string $token 検証するトークン
 * @param string $action アクション名（オプション）
 * @return bool 検証結果
 */
function validateCSRFToken($token, $action = 'default')
{
    $csrf = CSRFProtection::getInstance();
    return $csrf->validateToken($token, $action);
}

/**
 * CSRFトークンのHTML hidden inputを生成
 * 
 * @param string $action アクション名（オプション）
 * @param string $fieldName フィールド名（デフォルト: csrf_token）
 * @return string HTML hidden input
 */
function csrfTokenField($action = 'default', $fieldName = 'csrf_token')
{
    $token = getCSRFToken($action);
    return '<input type="hidden" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($token) . '">';
}

/**
 * CSRFトークンのメタタグを生成
 * 
 * @param string $action アクション名（オプション）
 * @return string HTML meta tag
 */
function csrfTokenMeta($action = 'default')
{
    $token = getCSRFToken($action);
    return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
}

/**
 * POSTリクエストのCSRFトークンを検証
 * 
 * @param string $action アクション名（オプション）
 * @param string $fieldName フィールド名（デフォルト: csrf_token）
 * @return bool 検証結果
 */
function validatePostCSRFToken($action = 'default', $fieldName = 'csrf_token')
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    
    if (!isset($_POST[$fieldName])) {
        return false;
    }
    
    return validateCSRFToken($_POST[$fieldName], $action);
}

/**
 * AJAXリクエストのCSRFトークンを検証
 * 
 * @param string $action アクション名（オプション）
 * @return bool 検証結果
 */
function validateAjaxCSRFToken($action = 'default')
{
    // X-CSRF-Tokenヘッダーをチェック
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if ($headerToken && validateCSRFToken($headerToken, $action)) {
        return true;
    }
    
    // POSTデータのcsrf_tokenをチェック
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['csrf_token']) && validateCSRFToken($input['csrf_token'], $action)) {
        return true;
    }
    
    return false;
}

/**
 * CSRFトークン検証エラー時のレスポンス
 * 
 * @param string $message エラーメッセージ
 * @param int $httpCode HTTPステータスコード
 */
function csrfErrorResponse($message = 'CSRF token validation failed', $httpCode = 403)
{
    http_response_code($httpCode);
    
    // AJAXリクエストの場合はJSONで返す
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'error_code' => 'CSRF_TOKEN_INVALID'
        ]);
    } else {
        // 通常のリクエストの場合はHTMLで返す
        echo '<!DOCTYPE html>
<html>
<head>
    <title>CSRF Token Error</title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>CSRF Token Error</h1>
    <p>' . htmlspecialchars($message) . '</p>
    <p><a href="javascript:history.back()">戻る</a></p>
</body>
</html>';
    }
    
    exit;
}

/**
 * AJAXリクエストかどうかを判定
 * 
 * @return bool AJAXリクエストの場合true
 */
function isAjaxRequest()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * CSRFトークンのデバッグ情報を取得
 * 
 * @return array デバッグ情報
 */
function getCSRFDebugInfo()
{
    $csrf = CSRFProtection::getInstance();
    return $csrf->getDebugInfo();
}
