<?php
require_once '/home/users/1/yukihiko/vendor/autoload.php';
session_start();

$client = new Google_Client();
$client->setAuthConfig('/home/users/1/yukihiko/web/kenchikuka.com/update_youtube/client_secret.json');
$client->setRedirectUri('https://kenchikuka.com/update_youtube/oauth_callback.php');
$client->addScope(Google_Service_YouTube::YOUTUBE);

if (isset($_GET['code'])) {
    $accessToken = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($accessToken['error'])) {
        echo "認証エラー: " . htmlspecialchars($accessToken['error_description']);
        exit();
    }
    $client->setAccessToken($accessToken);
    $_SESSION['access_token'] = $accessToken;

    if (!isset($_SESSION['uid'], $_SESSION['videoId'])) {
        echo "UIDまたはVideoIDがセッションに存在しません。";
        exit();
    }
    $uid = urlencode($_SESSION['uid']);
    $videoId = urlencode($_SESSION['videoId']);
    header("Location: update_youtube05.php?uid={$uid}&videoId={$videoId}");
    exit();
} else {
    echo "認証コードがありません。";
}
