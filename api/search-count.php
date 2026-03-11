<?php
/**
 * Search Count API Endpoint
 * 
 * 検索結果件数を動的に取得するAPIエンドポイント
 * CSRFトークンによる保護を実装
 * 
 * @package PocketNavi
 * @subpackage API
 */

// エラーレポートを有効化（開発環境のみ）
$isProduction = !isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] !== 'localhost';
if (!$isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// セッション開始（CSRFトークン検証のため）
if (session_status() === PHP_SESSION_NONE) {
    // ヘッダーが送信されていない場合のみセッション開始
    if (!headers_sent()) {
        session_start();
    }
}

// 必要なファイルを読み込み
require_once __DIR__ . '/../src/Utils/CSRFHelper.php';
require_once __DIR__ . '/../src/Security/RateLimiter.php';

// JSONレスポンス用のヘッダーを設定
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS設定（必要に応じて）
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');

// OPTIONSリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
        'error_code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit;
}

// CSRFトークンの検証
if (!validateAjaxCSRFToken('search')) {
    // デバッグ情報（開発環境のみ）
    $debugInfo = [];
    if (!$isProduction) {
        $debugInfo = [
            'session_id' => session_id(),
            'session_status' => session_status(),
            'post_token' => $_POST['csrf_token'] ?? 'not_set',
            'header_token' => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 'not_set',
            'session_tokens' => $_SESSION['csrf_tokens'] ?? 'not_set'
        ];
    }
    
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'CSRF token validation failed',
        'error_code' => 'CSRF_TOKEN_INVALID',
        'debug' => $debugInfo
    ]);
    exit;
}

// レート制限のチェック
$rateLimiter = new RateLimiter();
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!$rateLimiter->checkLimit('search_count', $clientIP)) {
    $blockTime = $rateLimiter->isBlocked('search_count', $clientIP);
    $retryAfter = $blockTime ? $blockTime - time() : 300;
    
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    echo json_encode([
        'success' => false,
        'error' => 'Rate limit exceeded',
        'error_code' => 'RATE_LIMIT_EXCEEDED',
        'message' => 'リクエスト制限に達しました。しばらく時間をおいてから再試行してください。',
        'retry_after' => $retryAfter
    ]);
    exit;
}

try {
    // リクエストボディを取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // 検索パラメータを取得
    $query = $input['query'] ?? '';
    $prefectures = $input['prefectures'] ?? [];
    $architectsSlug = $input['architectsSlug'] ?? '';
    $completionYears = $input['completionYears'] ?? '';
    $hasPhotos = $input['hasPhotos'] ?? false;
    $hasVideos = $input['hasVideos'] ?? false;
    $userLat = $input['userLat'] ?? null;
    $userLng = $input['userLng'] ?? null;
    $radiusKm = $input['radiusKm'] ?? 10;
    
    // データベース接続
    require_once __DIR__ . '/../config/database_unified.php';
    $db = getDB();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // 検索クエリを構築
    $whereConditions = [];
    $params = [];
    
    // キーワード検索
    if (!empty($query)) {
        $whereConditions[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
        $searchTerm = "%{$query}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // 都道府県フィルター
    if (!empty($prefectures) && is_array($prefectures)) {
        $placeholders = str_repeat('?,', count($prefectures) - 1) . '?';
        $whereConditions[] = "prefecture IN ({$placeholders})";
        $params = array_merge($params, $prefectures);
    }
    
    // 建築家フィルター
    if (!empty($architectsSlug)) {
        $whereConditions[] = "architect_slug = ?";
        $params[] = $architectsSlug;
    }
    
    // 完成年フィルター
    if (!empty($completionYears)) {
        $whereConditions[] = "completion_year = ?";
        $params[] = $completionYears;
    }
    
    // 写真フィルター
    if ($hasPhotos) {
        $whereConditions[] = "has_photo = 1";
    }
    
    // 動画フィルター
    if ($hasVideos) {
        $whereConditions[] = "has_video = 1";
    }
    
    // 地理的検索
    if ($userLat !== null && $userLng !== null && $radiusKm > 0) {
        $whereConditions[] = "(
            6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(latitude))
            )
        ) <= ?";
        $params[] = $userLat;
        $params[] = $userLng;
        $params[] = $userLat;
        $params[] = $radiusKm;
    }
    
    // WHERE句を構築
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // 件数を取得
    $countQuery = "SELECT COUNT(*) as count FROM buildings_table_3 {$whereClause}";
    $stmt = $db->prepare($countQuery);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare count query: ' . $db->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'] ?? 0;
    
    $stmt->close();
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'count' => (int)$count,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    // エラーログを記録
    error_log("Search count API error: " . $e->getMessage());
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $isProduction ? 'Internal server error' : $e->getMessage(),
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
?>