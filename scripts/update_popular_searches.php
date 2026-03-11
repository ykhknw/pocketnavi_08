#!/usr/local/php/8.3/bin/php
<?php
/**
 * 人気検索キャッシュ更新スクリプト
 * このスクリプトは定期的に実行してキャッシュを更新します
 */

// エラーレポートを有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ログファイルのパスを設定
$logFile = __DIR__ . '/../logs/cron_update_popular_searches.log';

// ログ出力関数
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    echo "[$timestamp] $message\n";
}

// 必要なファイルを読み込み
//require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_unified.php';
require_once __DIR__ . '/../src/Services/PopularSearchCache.php';
require_once __DIR__ . '/../src/Services/SearchLogService.php';

// データベース接続を直接作成（CRON環境での確実な動作のため）
$pdo = null;
try {
    $dbConfig = getDatabaseConfig();
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['dbname'],
        $dbConfig['charset']
    );
    $pdo = new PDO(
        $dsn,
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
    
    // getDB()関数が存在しない場合、グローバル変数経由で定義
    if (!function_exists('getDB')) {
        $GLOBALS['_cron_db_connection'] = $pdo;
        function getDB() {
            global $_cron_db_connection;
            return $_cron_db_connection;
        }
    }
} catch (PDOException $e) {
    writeLog("データベース接続エラー: " . $e->getMessage());
    error_log("Database connection error in update_popular_searches.php: " . $e->getMessage());
    exit(1);
}

try {
    writeLog("人気検索キャッシュ更新を開始します");
    writeLog("データベース接続に成功しました");
    
    $cacheService = new PopularSearchCache();
    // データベース接続を設定（CRON環境での確実な動作のため）
    $cacheService->setDatabase($pdo);
    
    // キャッシュの状態を確認
    $status = $cacheService->getCacheStatus();
    writeLog("現在のキャッシュ状態: " . $status['status']);
    writeLog("最終更新: " . $status['last_update']);
    writeLog("データ数: " . $status['data_count']);
    
    // 各検索タイプのキャッシュを強制更新
    $searchTypes = ['', 'architect', 'building', 'prefecture', 'text'];
    
    foreach ($searchTypes as $searchType) {
        writeLog("検索タイプ '{$searchType}' のキャッシュを強制更新中");
        
        try {
            // データベースから強制的に取得してキャッシュを更新
            $result = $cacheService->forceUpdateCache(1, 50, '', $searchType);
            
            // 結果を確認
            $hasSearches = isset($result['searches']) && is_array($result['searches']);
            $searchCount = $hasSearches ? count($result['searches']) : 0;
            $total = $result['total'] ?? 0;
            
            if ($hasSearches && $searchCount > 0) {
                writeLog("  - データベースから " . $searchCount . " 件のデータを取得しました（総件数: {$total}）");
                
                // フォールバックデータのチェック（改善版）
                $fallbackQueries = ['安藤忠雄', '隈研吾', '丹下健三', '国立代々木競技場', '東京スカイツリー', '東京', '大阪', '京都', '現代建築', '住宅'];
                $fallbackThreshold = 20; // total_searchesがこの値未満の場合はフォールバックの可能性がある
                $hasSuspiciousData = false;
                
                // 最初の5件をチェック
                $checkCount = min(5, $searchCount);
                for ($i = 0; $i < $checkCount; $i++) {
                    $search = $result['searches'][$i];
                    $query = $search['query'] ?? 'N/A';
                    $totalSearches = $search['total_searches'] ?? 0;
                    
                    // フォールバック値に一致し、かつ検索数が少ない場合は警告
                    if (in_array($query, $fallbackQueries) && $totalSearches < $fallbackThreshold) {
                        $hasSuspiciousData = true;
                        break;
                    }
                }
                
                // データが少ない場合の警告
                if ($searchCount < 10 && $total < 10) {
                    writeLog("  - ⚠️ 警告: 取得されたデータが少なく、データベースに十分なデータがない可能性があります");
                } elseif ($hasSuspiciousData) {
                    writeLog("  - ⚠️ 警告: フォールバックデータが含まれている可能性があります");
                }
            } else {
                writeLog("  - ⚠️ 警告: データが見つかりませんでした");
            }
        } catch (Exception $e) {
            $errorMessage = "検索タイプ '{$searchType}' の更新中にエラーが発生しました: " . $e->getMessage();
            writeLog("  - エラー: " . $errorMessage);
            error_log("Popular searches cache update error for type '{$searchType}': " . $e->getMessage());
            // エラーが発生しても処理を続行（他のタイプを更新）
        }
    }
    
    // 更新後のキャッシュ状態を確認
    $newStatus = $cacheService->getCacheStatus();
    writeLog("更新後のキャッシュ状態:");
    writeLog("- 状態: " . $newStatus['status']);
    writeLog("- 最終更新: " . $newStatus['last_update']);
    writeLog("- データ数: " . $newStatus['data_count']);
    
    writeLog("人気検索キャッシュ更新が完了しました");
    
} catch (Exception $e) {
    $errorMessage = "エラーが発生しました: " . $e->getMessage();
    writeLog($errorMessage);
    error_log("Popular searches cache update error: " . $e->getMessage());
    exit(1);
}
?>
