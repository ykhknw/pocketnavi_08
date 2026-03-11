#!/usr/local/php/8.3/bin/php
<?php
/**
 * HETEML環境用検索履歴クリーンアップスクリプト
 * 
 * HETEMLの制約（実行時間30秒、メモリ128MB）に最適化
 * 
 * 使用方法:
 * php scripts/heteml_cleanup_search_history.php [retention_days] [--archive] [--stats]
 */

// スクリプトのパスを取得
$scriptDir = dirname(__FILE__);
$projectRoot = dirname($scriptDir);

// プロジェクトのルートディレクトリをインクルードパスに追加
set_include_path($projectRoot . PATH_SEPARATOR . get_include_path());

// 必要なファイルを読み込み
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/src/Services/SearchLogService.php';
require_once $scriptDir . '/heteml_cleanup_config.php';

// HETEML設定を読み込み
$config = require $scriptDir . '/heteml_cleanup_config.php';
$hetemlConfig = $config['heteml'];

// HETEML環境の制約を設定
ini_set('max_execution_time', $hetemlConfig['max_execution_time']);
ini_set('memory_limit', $hetemlConfig['memory_limit']);

/**
 * HETEML用ログ関数
 */
function hetemlLog($message, $level = 'INFO') {
    global $hetemlConfig;
    
    $logFile = $hetemlConfig['log_file'];
    $logDir = dirname($logFile);
    
    // ログディレクトリが存在しない場合は作成
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // ログファイルサイズチェック
    if (file_exists($logFile) && filesize($logFile) > $hetemlConfig['max_log_size']) {
        // ログファイルをローテート
        rename($logFile, $logFile . '.' . date('Y-m-d-H-i-s'));
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // コンソールにも出力
    echo $logMessage;
}

/**
 * HETEML用の軽量クリーンアップ
 */
function performHetemlCleanup($searchLogService, $retentionDays, $archive) {
    global $hetemlConfig;
    
    hetemlLog("HETEML環境用クリーンアップ開始 - 保持期間: {$retentionDays}日, アーカイブ: " . ($archive ? '有効' : '無効'));
    
    $startTime = microtime(true);
    $deletedCount = 0;
    $archivedCount = 0;
    
    try {
        $db = $searchLogService->getDatabase();
        $db->beginTransaction();
        
        // バッチサイズで処理
        $batchSize = $hetemlConfig['batch_size'];
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        
        // アーカイブ処理（軽量化）
        if ($archive) {
            $archivedCount = performLightweightArchive($db, $cutoffDate, $batchSize);
        }
        
        // 削除処理（バッチ処理）
        $deletedCount = performBatchDelete($db, $cutoffDate, $batchSize);
        
        $db->commit();
        
        $executionTime = round(microtime(true) - $startTime, 2);
        hetemlLog("クリーンアップ完了 - 削除: {$deletedCount}件, アーカイブ: {$archivedCount}件, 実行時間: {$executionTime}秒");
        
        return [
            'deleted_count' => $deletedCount,
            'archived_count' => $archivedCount,
            'execution_time' => $executionTime,
            'error' => null
        ];
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        
        hetemlLog("クリーンアップエラー: " . $e->getMessage(), 'ERROR');
        
        return [
            'deleted_count' => 0,
            'archived_count' => 0,
            'execution_time' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * 軽量アーカイブ処理
 */
function performLightweightArchive($db, $cutoffDate, $batchSize) {
    global $hetemlConfig;
    
    // アーカイブテーブルを作成
    $createArchiveTableSql = "
        CREATE TABLE IF NOT EXISTS `global_search_history_archive` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `original_id` BIGINT NOT NULL,
            `query` VARCHAR(255) NOT NULL,
            `search_type` VARCHAR(20) NOT NULL,
            `search_count` INT NOT NULL,
            `last_searched` TIMESTAMP NOT NULL,
            `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_query_type` (`query`, `search_type`),
            INDEX `idx_archived_at` (`archived_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($createArchiveTableSql);
    
    // 人気検索ワードを集計してアーカイブ（軽量化）
    $archiveSql = "
        INSERT INTO global_search_history_archive 
        (original_id, query, search_type, search_count, last_searched)
        SELECT 
            MAX(id) as original_id,
            query,
            search_type,
            COUNT(*) as search_count,
            MAX(searched_at) as last_searched
        FROM global_search_history
        WHERE searched_at < ?
        GROUP BY query, search_type
        HAVING COUNT(*) >= ?
        LIMIT ?
    ";
    
    $stmt = $db->prepare($archiveSql);
    $stmt->execute([
        $cutoffDate,
        $hetemlConfig['archive_threshold'],
        $batchSize
    ]);
    
    return $stmt->rowCount();
}

/**
 * バッチ削除処理
 */
function performBatchDelete($db, $cutoffDate, $batchSize) {
    $totalDeleted = 0;
    $maxIterations = 10; // 最大10回のバッチ処理（30秒制限を考慮）
    
    for ($i = 0; $i < $maxIterations; $i++) {
        // 実行時間チェック
        if (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] > 25) {
            hetemlLog("実行時間制限に近づいたため、処理を中断", 'WARNING');
            break;
        }
        
        $deleteSql = "
            DELETE FROM global_search_history 
            WHERE searched_at < ?
            LIMIT ?
        ";
        
        $stmt = $db->prepare($deleteSql);
        $stmt->execute([$cutoffDate, $batchSize]);
        $deleted = $stmt->rowCount();
        $totalDeleted += $deleted;
        
        if ($deleted < $batchSize) {
            // 削除対象がなくなった
            break;
        }
        
        // メモリ使用量チェック
        if (memory_get_usage(true) > 100 * 1024 * 1024) { // 100MB
            hetemlLog("メモリ使用量が上限に近づいたため、処理を中断", 'WARNING');
            break;
        }
    }
    
    return $totalDeleted;
}

/**
 * HETEML用統計情報取得
 */
function getHetemlStats($searchLogService) {
    global $hetemlConfig, $config;
    
    try {
        $db = $searchLogService->getDatabase();
        
        // 軽量な統計情報のみ取得
        $statsSql = "
            SELECT 
                COUNT(*) as total_records,
                COUNT(DISTINCT query) as unique_queries,
                MIN(searched_at) as oldest_record,
                MAX(searched_at) as newest_record,
                COUNT(CASE WHEN searched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as records_last_week,
                COUNT(CASE WHEN searched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as records_last_month
            FROM global_search_history
        ";
        
        $stmt = $db->query($statsSql);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // テーブルサイズ情報
        $sizeSql = "
            SELECT 
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                table_rows
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'global_search_history'
        ";
        
        $stmt = $db->query($sizeSql);
        $sizeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // HETEML用の推奨事項を生成
        $recommendations = generateHetemlRecommendations($stats, $sizeInfo, $config['alerts']);
        
        return [
            'stats' => $stats,
            'size_info' => $sizeInfo,
            'recommendations' => $recommendations,
            'heteml_limits' => $hetemlConfig
        ];
        
    } catch (Exception $e) {
        hetemlLog("統計情報取得エラー: " . $e->getMessage(), 'ERROR');
        return ['error' => $e->getMessage()];
    }
}

/**
 * HETEML用推奨事項生成
 */
function generateHetemlRecommendations($stats, $sizeInfo, $alerts) {
    $recommendations = [];
    
    if (empty($stats)) {
        return $recommendations;
    }
    
    $totalRecords = $stats['total_records'] ?? 0;
    $sizeMB = $sizeInfo['size_mb'] ?? 0;
    
    // テーブルサイズチェック
    if ($sizeMB > $alerts['table_size_critical']) {
        $recommendations[] = [
            'type' => 'critical',
            'message' => "テーブルサイズが{$sizeMB}MBで制限に近づいています。緊急クリーンアップが必要です。",
            'action' => 'immediate_cleanup'
        ];
    } elseif ($sizeMB > $alerts['table_size_warning']) {
        $recommendations[] = [
            'type' => 'warning',
            'message' => "テーブルサイズが{$sizeMB}MBです。クリーンアップを検討してください。",
            'action' => 'schedule_cleanup'
        ];
    }
    
    // レコード数チェック
    if ($totalRecords > $alerts['record_count_critical']) {
        $recommendations[] = [
            'type' => 'critical',
            'message' => "レコード数が{$totalRecords}件で制限に近づいています。",
            'action' => 'immediate_cleanup'
        ];
    } elseif ($totalRecords > $alerts['record_count_warning']) {
        $recommendations[] = [
            'type' => 'warning',
            'message' => "レコード数が{$totalRecords}件です。",
            'action' => 'schedule_cleanup'
        ];
    }
    
    return $recommendations;
}

/**
 * コマンドライン引数を解析
 */
function parseArguments($argv) {
    $options = [
        'retention_days' => 60,  // HETEML用デフォルト
        'archive' => false,
        'stats' => false,
        'help' => false
    ];
    
    foreach ($argv as $arg) {
        if (is_numeric($arg)) {
            $options['retention_days'] = (int)$arg;
        } elseif ($arg === '--archive') {
            $options['archive'] = true;
        } elseif ($arg === '--stats') {
            $options['stats'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
        }
    }
    
    return $options;
}

/**
 * ヘルプメッセージを表示
 */
function showHelp() {
    echo "HETEML環境用検索履歴クリーンアップスクリプト\n\n";
    echo "使用方法:\n";
    echo "  php scripts/heteml_cleanup_search_history.php [retention_days] [options]\n\n";
    echo "引数:\n";
    echo "  retention_days    データ保持期間（日数、デフォルト: 60）\n\n";
    echo "オプション:\n";
    echo "  --archive         重要なデータをアーカイブしてから削除\n";
    echo "  --stats           データベースの統計情報を表示\n";
    echo "  --help, -h        このヘルプメッセージを表示\n\n";
    echo "HETEML制約:\n";
    echo "  - 実行時間: 30秒以内\n";
    echo "  - メモリ: 128MB以内\n";
    echo "  - バッチ処理: 1000件ずつ\n\n";
    echo "例:\n";
    echo "  php scripts/heteml_cleanup_search_history.php 60 --archive\n";
    echo "  php scripts/heteml_cleanup_search_history.php --stats\n";
}

/**
 * メイン処理
 */
function main() {
    global $argv;
    
    $options = parseArguments($argv);
    
    if ($options['help']) {
        showHelp();
        return;
    }
    
    try {
        $searchLogService = new SearchLogService();
        
        if ($options['stats']) {
            $result = getHetemlStats($searchLogService);
            
            if (isset($result['error'])) {
                hetemlLog("統計情報取得エラー: " . $result['error'], 'ERROR');
                return;
            }
            
            echo "=== HETEML環境 データベース統計情報 ===\n\n";
            echo "テーブルサイズ: " . ($result['size_info']['size_mb'] ?? 0) . " MB\n";
            echo "総レコード数: " . number_format($result['stats']['total_records'] ?? 0) . "\n";
            echo "ユニーク検索語: " . number_format($result['stats']['unique_queries'] ?? 0) . "\n";
            echo "過去1週間: " . number_format($result['stats']['records_last_week'] ?? 0) . " レコード\n";
            echo "過去1ヶ月: " . number_format($result['stats']['records_last_month'] ?? 0) . " レコード\n\n";
            
            if (!empty($result['recommendations'])) {
                echo "推奨事項:\n";
                foreach ($result['recommendations'] as $rec) {
                    $icon = $rec['type'] === 'critical' ? '🚨' : '⚠️';
                    echo "  {$icon} " . $rec['message'] . "\n";
                }
            }
            
        } else {
            $result = performHetemlCleanup(
                $searchLogService, 
                $options['retention_days'], 
                $options['archive']
            );
            
            if ($result['error']) {
                hetemlLog("クリーンアップ失敗: " . $result['error'], 'ERROR');
                exit(1);
            }
            
            echo "✅ HETEML環境用クリーンアップ完了\n";
            echo "削除: " . number_format($result['deleted_count']) . " 件\n";
            echo "アーカイブ: " . number_format($result['archived_count']) . " 件\n";
            echo "実行時間: " . $result['execution_time'] . " 秒\n";
        }
        
    } catch (Exception $e) {
        hetemlLog("メイン処理エラー: " . $e->getMessage(), 'ERROR');
        exit(1);
    }
}

// スクリプトが直接実行された場合のみメイン処理を実行
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    main();
}
