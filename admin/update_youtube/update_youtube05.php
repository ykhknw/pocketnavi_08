<?php
require_once '/home/users/1/yukihiko/vendor/autoload.php';
session_start();

if (!isset($_SESSION['access_token']) || !isset($_GET['uid']) || !isset($_GET['videoId'])) {
    echo "アクセストークン、UID、またはVideoIDが不足しています。";
    exit();
}

$uid = $_GET['uid'];
$videoId = $_GET['videoId'];
$accessToken = $_SESSION['access_token'];

$client = new Google_Client();
$client->setAccessToken($accessToken);
$youtube = new Google_Service_YouTube($client);

$host = 'mysql320.phy.heteml.lan';
$db   = '_shinkenchiku_02';
$user = '_shinkenchiku_02';
$pass = 'ipgdfahuqbg3';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $stmt = $pdo->prepare("SELECT b.*, GROUP_CONCAT(a.architectJa ORDER BY ba.architect_order SEPARATOR ' / ') AS architectJa, GROUP_CONCAT(a.architectEn ORDER BY ba.architect_order SEPARATOR ' / ') AS architectEn FROM buildings_table_2 b LEFT JOIN building_architects ba ON b.id = ba.building_id LEFT JOIN architects_table a ON ba.architect_id = a.architect_id WHERE b.uid = ? GROUP BY b.id");
    $stmt->execute([$uid]);
    $building = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$building) {
        echo "UID: {$uid} に該当する建物が見つかりません。";
        exit();
    }

    $title = "{$building['title']} | {$building['titleEn']} | {$building['prefectures']} #Shorts";
    $description = "{$building['prefectures']}の、{$building['title']}のショート動画です！\n{$building['title']}を巡るショートトリップ。ぜひ最後までお楽しみください！\n\n関連情報・リンク: https://kenchikuka.com/index.php?uid={$building['uid']}\n\n建築物の基本情報:\n建築名: {$building['title']} ({$building['titleEn']})\n設計者: {$building['architectJa']} ({$building['architectEn']})\n建築年: {$building['completionYears']}年\n所在地: {$building['prefectures']} ({$building['prefecturesEn']})\n用途: {$building['buildingTypes']} ({$building['buildingTypesEn']})\n構造形式: {$building['structures']} ({$building['structuresEn']})";

    $tags = ["建築", $building['title'], $building['architectJa'], "Architecture", $building['titleEn'], "Shorts", "街歩き", "建築巡り", "日本の建築", "JapanArchitecture", "TravelJapan", "VisitJapan"];

    $videoList = $youtube->videos->listVideos("snippet", ["id" => $videoId]);
    if (count($videoList) === 0) {
        echo "指定された動画IDが見つかりません。";
        exit();
    }

    $video = $videoList[0];
    $snippet = new Google_Service_YouTube_VideoSnippet();
    $snippet->setTitle($title);
    $snippet->setDescription($description);
    $snippet->setTags($tags);
    $snippet->setCategoryId("19");
    $video->setSnippet($snippet);

    $youtube->videos->update("snippet", $video);

    echo "<h2 class='text-success'>✅ YouTubeメタデータが更新されました。</h2>";
    echo "<table class='table table-bordered mt-4'><tr><th>項目</th><th>内容</th></tr>";
    echo "<tr><td>タイトル</td><td>" . htmlspecialchars($title) . "</td></tr>";
    echo "<tr><td>説明文</td><td><pre>" . htmlspecialchars($description) . "</pre></td></tr>";
    echo "<tr><td>タグ</td><td>" . htmlspecialchars(implode(', ', $tags)) . "</td></tr>";
    echo "<tr><td>動画ID</td><td>" . htmlspecialchars($videoId) . "</td></tr>";
    echo "</table>";

    echo "<div class='mt-4'><a href='input_uid.php' class='btn btn-outline-primary'>続けて更新する</a></div>";

} catch (Exception $e) {
    echo "エラー: " . htmlspecialchars($e->getMessage());
}
?>