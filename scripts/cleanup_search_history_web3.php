<?php
/**
 * 検索履歴データのクリーンアップスクリプト（最適化版）
 * searched_atカラム使用（インデックスあり）でパフォーマンス最適化
 * 
 * 使用方法:
 *   ブラウザでアクセス: https://example.com/scripts/cleanup_search_history_web.php
 * 
 * オプション:
 *   ?days=60        保持期間を60日に変更
 *   ?archive=1      アーカイブを有効化
 *   ?dryrun=1       削除せずに確認のみ
 *   ?stats=1        統計情報のみ表示
 */
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ==== 設定 ====
$logFile = __DIR__ . '/../logs/cleanup_search_history.log';
$batchSize = 1000; // 1回の処理件数（調整可能）
$sleepMicroseconds = 200000; // バッチ間の待機時間（0.2秒）

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    echo htmlspecialchars($logLine) . "<br>\n";
    flush();
    if (ob_get_level() > 0) ob_flush();
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// グローバルエラーハンドラ
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    writeLog("PHP Error [$errno]: $errstr in $errfile on line $errline");
});

set_exception_handler(function($exception) {
    writeLog("Exception: " . $exception->getMessage());
    writeLog("Stack trace: " . $exception->getTraceAsString());
});

// ログディレクトリ確認
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0775, true);
}

// 設定読み込み
require_once __DIR__ . '/../config/database_unified.php';

// ==== クエリパラメータ取得 ====
$retentionDays = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$archive = isset($_GET['archive']) ? (bool)$_GET['archive'] : false;
$dryRun = isset($_GET['dryrun']) ? (bool)$_GET['dryrun'] : false;
$showStats = isset($_GET['stats']) ? (bool)$_GET['stats'] : false;

writeLog("=== 検索履歴クリーンアップ（最適化版）開始 ===");
writeLog("保持期間: {$retentionDays}日");
writeLog("アーカイブ: " . ($archive ? '有効' : '無効'));
writeLog("バッチサイズ: {$batchSize}件");
writeLog("ドライラン: " . ($dryRun ? 'はい' : 'いいえ'));
writeLog("使用カラム: searched_at（インデックス最適化済み）");

// ==== DB接続 ====
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
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    writeLog("✅ データベース接続成功");
} catch (PDOException $e) {
    writeLog("❌ DB接続エラー: " . $e->getMessage());
    exit;
}

// ==== 統計モード ====
if ($showStats) {
    writeLog("--- 統計情報 ---");
    
    try {
        // 総レコード数
        $total = $pdo->query("SELECT COUNT(*) FROM global_search_history")->fetchColumn();
        writeLog("総レコード数: " . number_format($total));
        
        // 最古・最新
        $oldest = $pdo->query("SELECT MIN(searched_at) FROM global_search_history")->fetchColumn();
        $newest = $pdo->query("SELECT MAX(searched_at) FROM global_search_history")->fetchColumn();
        writeLog("最古レコード: {$oldest}");
        writeLog("最新レコード: {$newest}");
        
        // 日別統計
        writeLog("\n--- 直近10日の検索数 ---");
        $stmt = $pdo->query("
            SELECT 
                DATE(searched_at) as date,
                COUNT(*) as count
            FROM global_search_history
            GROUP BY DATE(searched_at)
            ORDER BY date DESC
            LIMIT 10
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            writeLog("{$row['date']}: " . number_format($row['count']) . "件");
        }
        
        // 削除対象数
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        $toDelete = $pdo->prepare("SELECT COUNT(*) FROM global_search_history WHERE searched_at < ?");
        $toDelete->execute([$cutoffDate]);
        $deleteCount = $toDelete->fetchColumn();
        writeLog("\n削除対象（{$retentionDays}日以前）: " . number_format($deleteCount) . "件");
        
    } catch (Exception $e) {
        writeLog("❌ 統計取得エラー: " . $e->getMessage());
    }
    
    exit;
}

// ==== 削除対象の確認 ====
try {
    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
    writeLog("削除基準日: {$cutoffDate}");

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM global_search_history WHERE searched_at < ?");
    $stmt->execute([$cutoffDate]);
    $totalToDelete = $stmt->fetchColumn();
    writeLog("削除対象レコード数: " . number_format($totalToDelete) . "件");

    if ($totalToDelete == 0) {
        writeLog("削除対象なし。終了します。");
        exit;
    }

    if ($dryRun) {
        writeLog("ドライランモードのため、実際の削除は行いません。");
        exit;
    }
} catch (Exception $e) {
    writeLog("❌ カウント処理でエラー: " . $e->getMessage());
    exit;
}

// ==== アーカイブテーブルの存在確認 ====
if ($archive) {
    try {
        writeLog("アーカイブテーブルの存在確認中...");
        $stmt = $pdo->query("SHOW TABLES LIKE 'archived_search_history'");
        $exists = $stmt->fetch();
        
        if (!$exists) {
            writeLog("❌ archived_search_historyテーブルが存在しません");
            writeLog("アーカイブをスキップして削除のみ実行します");
            $archive = false;
        } else {
            writeLog("✅ archived_search_historyテーブル確認完了");
        }
    } catch (Exception $e) {
        writeLog("❌ テーブル確認でエラー: " . $e->getMessage());
        writeLog("アーカイブをスキップします");
        $archive = false;
    }
}

// ==== アーカイブ処理（バッチ） ====
$archivedTotal = 0;
if ($archive) {
    writeLog("--- アーカイブ処理開始 ---");
    
    try {
        $batchCount = 0;
        do {
            $batchCount++;
            $startTime = microtime(true);
            
            // バッチごとにアーカイブ（searched_at使用）
            $sql = "
                INSERT INTO archived_search_history 
                SELECT * FROM global_search_history 
                WHERE searched_at < ? 
                LIMIT {$batchSize}
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cutoffDate]);
            $archivedCount = $stmt->rowCount();
            $archivedTotal += $archivedCount;
            
            $duration = round(microtime(true) - $startTime, 2);
            writeLog("バッチ#{$batchCount}: {$archivedCount}件アーカイブ完了（{$duration}秒）- 合計: " . number_format($archivedTotal));
            
            if ($archivedCount > 0) {
                usleep($sleepMicroseconds);
            }
            
            // 10バッチごとに進捗報告
            if ($batchCount % 10 == 0) {
                $progress = round(($archivedTotal / $totalToDelete) * 100, 1);
                writeLog("📊 進捗: {$progress}% 完了");
            }
            
        } while ($archivedCount > 0 && $batchCount < 1000);
        
        writeLog("✅ アーカイブ完了: " . number_format($archivedTotal) . "件");
        
    } catch (PDOException $e) {
        writeLog("❌ アーカイブ処理でエラー: " . $e->getMessage());
        writeLog("SQLState: " . $e->getCode());
        writeLog("削除処理に進みます");
    }
}

// ==== 削除処理（バッチ） ====
writeLog("--- 削除処理開始 ---");
$deletedTotal = 0;
$batchCount = 0;

try {
    do {
        $batchCount++;
        $startTime = microtime(true);
        
        // バッチごとに削除（searched_at使用、インデックス最適化）
        $sql = "DELETE FROM global_search_history WHERE searched_at < ? LIMIT {$batchSize}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cutoffDate]);
        $deletedCount = $stmt->rowCount();
        $deletedTotal += $deletedCount;
        
        $duration = round(microtime(true) - $startTime, 2);
        $progress = $totalToDelete > 0 ? round(($deletedTotal / $totalToDelete) * 100, 1) : 0;
        
        writeLog("バッチ#{$batchCount}: {$deletedCount}件削除完了（{$duration}秒）- 進捗: {$progress}% ({$deletedTotal}/{$totalToDelete})");
        
        if ($deletedCount > 0) {
            usleep($sleepMicroseconds);
        }
        
        // 安全装置
        if ($batchCount >= 1000) {
            writeLog("⚠️ 安全上限に達しました（1000バッチ）");
            break;
        }
        
    } while ($deletedCount > 0);
    
    writeLog("✅ 削除完了: " . number_format($deletedTotal) . "件");
    
} catch (PDOException $e) {
    writeLog("❌ 削除処理でエラー: " . $e->getMessage());
    writeLog("SQLState: " . $e->getCode());
}

// ==== 最終結果 ====
$totalTime = time() - strtotime(explode('[', $logFile)[0] ?? 'now');
writeLog("=== クリーンアップ完了 ===");
writeLog("アーカイブ: " . number_format($archivedTotal) . "件");
writeLog("削除: " . number_format($deletedTotal) . "件");
writeLog("メモリ使用量: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");

?>