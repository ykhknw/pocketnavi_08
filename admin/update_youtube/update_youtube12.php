<?php
//require_once __DIR__ . '/vendor/autoload.php';
require_once '/home/users/1/yukihiko' . '/vendor/autoload.php';

session_start();

$client = new Google_Client();
//$client->setAuthConfig('path/to/your/client_secret.json'); // ダウンロードしたJSONファイルのパス
$client->setAuthConfig('/home/users/1/yukihiko/web/kenchikuka.com/update_youtube/client_secret.json'); // ダウンロードしたJSONファイルのパス
$client->setRedirectUri('https://kenchikuka.com/update_youtube/oauth_callback.php'); // 認証後のリダイレクトURL
$client->addScope(Google_Service_YouTube::YOUTUBE); // YouTubeへのフルアクセススコープ

if (!isset($_GET['code'])) {
    // 認証コードがない場合、Google認証ページへリダイレクト
    $authUrl = $client->createAuthUrl();
    header('Location: ' . $authUrl);
    exit();
} else {
    // 認証コードがある場合 (リダイレクト後)、トークンを取得
    $accessToken = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($accessToken);

    // アクセストークンをセッションに保存（またはデータベースなどに保存）
    $_SESSION['access_token'] = $accessToken;

    // トークン取得後、メインの処理を行うページへリダイレクト
    header('Location: update_video.php');
    exit();
}
?>