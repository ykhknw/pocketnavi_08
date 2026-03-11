<?php
require_once '/home/users/1/yukihiko/vendor/autoload.php';
session_start();

if (!isset($_SESSION['access_token'])) {
    echo "アクセストークンがありません。先に認証してください。";
    exit();
}

$client = new Google_Client();
$client->setAccessToken($_SESSION['access_token']);

if ($client->isAccessTokenExpired()) {
    echo "アクセストークンの有効期限が切れています。再認証が必要です。";
    exit();
}

$youtube = new Google_Service_YouTube($client);

try {
    // "mine=true" で認証ユーザーのチャンネル情報を取得
    $channelsResponse = $youtube->channels->listChannels('snippet', ['mine' => true]);

    if (count($channelsResponse->getItems()) === 0) {
        echo "認証中のユーザーのチャンネルが見つかりません。";
        exit();
    }

    $channel = $channelsResponse->getItems()[0];
    $channelTitle = $channel->getSnippet()->getTitle();
    $channelId = $channel->getId();

    echo "<h2>認証中のYouTubeアカウント情報</h2>";
    echo "<p><strong>チャンネル名：</strong>" . htmlspecialchars($channelTitle) . "</p>";
    echo "<p><strong>チャンネルID：</strong>" . htmlspecialchars($channelId) . "</p>";

} catch (Google_Service_Exception $e) {
    echo "YouTube API エラー: " . htmlspecialchars($e->getMessage());
} catch (Exception $e) {
    echo "エラー: " . htmlspecialchars($e->getMessage());
}
