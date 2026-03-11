<?php
/**
 * 建築物の写真を取得するAPI
 * WebP画像のみを返却（JPGは除外）
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// エラーハンドリング
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

// パラメータの取得と検証
$uid = $_GET['uid'] ?? '';

if (empty($uid)) {
    sendError('UID parameter is required', 400);
}

// UIDの安全性チェック（英数字とハイフンのみ許可）
if (!preg_match('/^[A-Za-z0-9_-]+$/', $uid)) {
    sendError('Invalid UID format', 400);
}

// 画像フォルダのパスを構築
$picturesDir = __DIR__ . '/../pictures/' . $uid;

// フォルダの存在確認
if (!is_dir($picturesDir)) {
    sendError('Photo folder not found', 404);
}

// WebPファイルをスキャン
$webpFiles = [];
$files = scandir($picturesDir);

if ($files === false) {
    sendError('Failed to read photo folder', 500);
}

foreach ($files as $file) {
    // WebPファイルのみを対象
    if (pathinfo($file, PATHINFO_EXTENSION) === 'webp') {
        $webpFiles[] = $file;
    }
}

// ファイル名でソート（時系列順）
sort($webpFiles);

// 画像URLの配列を生成
$photoUrls = [];
foreach ($webpFiles as $file) {
    $photoUrls[] = '/pictures/' . urlencode($uid) . '/' . urlencode($file);
}

// レスポンス
echo json_encode([
    'success' => true,
    'uid' => $uid,
    'photos' => $photoUrls,
    'count' => count($photoUrls)
]);
?>

