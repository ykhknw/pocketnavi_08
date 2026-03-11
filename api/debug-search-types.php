<?php
header('Content-Type: application/json; charset=utf-8');

// 必要なファイルを読み込み
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/SearchLogService.php';

try {
    $searchLogService = new SearchLogService();
    $db = $searchLogService->getDatabase();
    
    // データベースからsearch_typeの一覧を取得
    $sql = "
        SELECT 
            search_type, 
            COUNT(*) as count,
            COUNT(DISTINCT query) as unique_queries
        FROM global_search_history 
        WHERE search_type IS NOT NULL 
        AND search_type != ''
        AND searched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY search_type 
        ORDER BY count DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $searchTypes = $stmt->fetchAll();
    
    // 最近の検索履歴も取得
    $recentSql = "
        SELECT 
            query,
            search_type,
            searched_at
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY searched_at DESC
        LIMIT 20
    ";
    
    $recentStmt = $db->prepare($recentSql);
    $recentStmt->execute();
    $recentSearches = $recentStmt->fetchAll();
    
    $response = [
        'success' => true,
        'data' => [
            'search_types' => $searchTypes,
            'recent_searches' => $recentSearches
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Debug search types error: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>
