<?php
require_once '/home/users/1/yukihiko/vendor/autoload.php';
session_start();

// str_starts_with 互換関数
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

// extractUid, extractVideoId 関数
function extractUid($input) {
    if (filter_var($input, FILTER_VALIDATE_URL)) {
        $parts = parse_url($input);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            if (isset($query['uid'])) {
                return $query['uid'];
            }
        }
    }
    return trim($input);
}

function extractVideoId($input) {
    if (filter_var($input, FILTER_VALIDATE_URL)) {
        $path = parse_url($input, PHP_URL_PATH);
        return basename($path);
    }
    return trim($input);
}

// 入力チェック
if (!isset($_POST['uid'], $_POST['videoId'], $_POST['password'])) {
    echo "入力が不完全です。";
    exit();
}

if ($_POST['password'] !== 'yuki11') {
    echo "パスワードが間違っています。";
    exit();
}

$uid = extractUid($_POST['uid']);
$videoId = extractVideoId($_POST['videoId']);
$_SESSION['uid'] = $uid;
$_SESSION['videoId'] = $videoId;

// Google OAuth 認証
$client = new Google_Client();
$client->setAuthConfig('/home/users/1/yukihiko/web/kenchikuka.com_new/admin/update_youtube/client_secret.json');
$client->setRedirectUri('https://kenchikuka.com/admin/update_youtube/oauth_callback.php');
$client->addScope(Google_Service_YouTube::YOUTUBE);
$client->setPrompt('select_account');

// デバッグ情報を表示
echo "<h3>デバッグ情報</h3>";
echo "クライアントID: " . $client->getClientId() . "<br>";
echo "リダイレクトURI: " . $client->getRedirectUri() . "<br>";
echo "スコープ: " . implode(', ', $client->getScopes()) . "<br>";
echo "<hr>";

$authUrl = $client->createAuthUrl();
echo "<h3>生成された認証URL:</h3>";
echo "<textarea style='width:100%; height:150px;'>" . $authUrl . "</textarea><br><br>";

// URLをパースして確認
$parsedUrl = parse_url($authUrl);
if (isset($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $params);
    echo "<h3>URLパラメータ:</h3>";
    echo "<pre>";
    print_r($params);
    echo "</pre>";
}

echo "<hr>";
echo "<a href='" . $authUrl . "'>この認証URLで試す</a><br><br>";
echo "※自動リダイレクトは停止しています";

// 確認できたら以下をコメント解除
// header('Location: ' . $authUrl);
// exit();
?>