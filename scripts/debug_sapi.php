#!/usr/local/php/8.3/bin/php
<?php
/**
 * 検索履歴データのクリーンアップスクリプト（CRON実行版・改善版）
 * searched_atカラム使用（インデックスあり）でパフォーマンス最適化
 * 
 * 使用方法:
 *   CRON: /path/to/cleanup_search_history.php [days] [archive]
 * 
 * 引数（省略可能）:
 *   days     - 保持期間（日数）デフォルト: 30
 *   archive  - アーカイブ有効化（1=有効, 0=無効）デフォルト: 0
 */

// エラーレポートを有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

// ==== 設定 ====
$logFile = __DIR__ . '/../logs/cleanup_search_history_cron.log';
$debugLog = __DIR__ . '/../logs/cleanup_debug.log';
$batchSize = 5000;
$sleepMicroseconds = 100000;

// デバッグログ関数
function debugLog($message) {
    global $debugLog;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debugLog, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// 初回ログ（スクリプト起動確認）
debugLog("=== Script Started ===");
debugLog("SAPI: " . php_sapi_name());
debugLog("__FILE__: " . __FILE__);
debugLog("__DIR__: " . __DIR__);
debugLog("getcwd(): " . getcwd());

// CLI実行のチェック（緩和版）
$sapi = php_sapi_name();
$isCli = ($sapi === 'cli' || $sapi === 'cgi-fcgi' || $sapi === 'cli-server');

if (!$isCli && isset($_SERVER['REQUEST_METHOD'])) {
    // Webブラウザからのアクセスは拒否
    debugLog("ERROR: Web access detected");
    die("このスクリプトはコマンドラインからのみ実行できます\n");
}

debugLog("CLI check passed (SAPI: $sapi)");

// コマンドライン引数から設定を取得（デフォルト値あり）
$retentionDays = 30;
$archive = false;

if (isset($argv) && is_array($argv)) {
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $retentionDays = (int)$argv[1];
    }
    if (isset($argv[2]) && is_numeric($argv[2])) {
        $archive = (bool)(int)$argv[2];
    }
}

debugLog("Retention days: $retentionDays");
debugLog("Archive: " . ($archive ? 'YES' : 'NO'));

// ログ出力関数
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    
    echo $logLine;
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// グローバルエラーハンドラ
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $message = "PHP Error [$errno]: $errstr in $errfile on line $errline";
    writeLog($message);
    debugLog($message);
    error_log($message);
});

set_exception_handler(function($exception) {
    $message = "Exception: " . $exception->getMessage();
    writeLog($message);
    debugLog($message . "\nStack: " . $exception->getTraceAsString());
    error_log($message);
});

// ログディレクトリ確認
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0775, true);
}

debugLog("Log directory checked");

// 設定ファイルの存在確認
$configPath = __DIR__ . '/../config/database_unified.php';
debugLog("Config path: $configPath");
debugLog("Config exists: " . (file_exists($configPath) ? 'YES' : 'NO'));

if (!file_exists($configPath)) {
    $errorMsg = "❌ 設定ファイルが見つかりません: $configPath";
    writeLog($errorMsg);
    debugLog($errorMsg);
    exit(1);
}

// 設定読み込み
try {
    require_once $configPath;
    debugLog("Config loaded successfully");
} catch (Exception $e) {
    $errorMsg = "❌ 設定ファイル読み込みエラー: " . $e->getMessage();
    writeLog($errorMsg);
    debugLog($errorMsg);
    exit(1);
}

writeLog("=== 検索履歴クリーンアップ（CRON実行）開始 ===");
writeLog("保持期間: {$retentionDays}日");
writeLog("アーカイブ: " . ($archive ? '有効' : '無効'));
writeLog("バッチサイズ: {$batchSize}件");
writeLog("使用カラム: searched_at（インデックス最適化済み）");

// ==== DB接続 ====
$pdo = null;
try {
    debugLog("Attempting DB connection...");
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
    debugLog("DB connection successful");
} catch (PDOException $e) {
    $errorMessage = "❌ DB接続エラー: " . $e->getMessage();
    writeLog($errorMessage);
    debugLog($errorMessage);
    error_log($errorMessage);
    exit(1);
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
        writeLog("削除対象なし。正常終了します。");
        debugLog("No records to delete. Exit 0");
        exit(0);
    }
} catch (Exception $e) {
    $errorMessage = "❌ カウント処理でエラー: " . $e->getMessage();
    writeLog($errorMessage);
    debugLog($errorMessage);
    error_log($errorMessage);
    exit(1);
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
        $errorMessage = "❌ テーブル確認でエラー: " . $e->getMessage();
        writeLog($errorMessage);
        debugLog($errorMessage);
        error_log($errorMessage);
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
            
            if ($batchCount % 20 == 0 || $archivedCount == 0) {
                writeLog("バッチ#{$batchCount}: {$archivedCount}件アーカイブ完了（{$duration}秒）- 合計: " . number_format($archivedTotal));
            }
            
            if ($archivedCount > 0) {
                usleep($sleepMicroseconds);
            }
            
        } while ($archivedCount > 0 && $batchCount < 1000);
        
        writeLog("✅ アーカイブ完了: " . number_format($archivedTotal) . "件");
        
    } catch (PDOException $e) {
        $errorMessage = "❌ アーカイブ処理でエラー: " . $e->getMessage();
        writeLog($errorMessage);
        debugLog($errorMessage);
        error_log($errorMessage);
        writeLog("削除処理に進みます");
    }
}

// ==== 削除処理（バッチ） ====
writeLog("--- 削除処理開始 ---");
$deletedTotal = 0;
$batchCount = 0;
$startCleanup = microtime(true);

try {
    do {
        $batchCount++;
        $startTime = microtime(true);
        
        $sql = "DELETE FROM global_search_history WHERE searched_at < ? LIMIT {$batchSize}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cutoffDate]);
        $deletedCount = $stmt->rowCount();
        $deletedTotal += $deletedCount;
        
        $duration = round(microtime(true) - $startTime, 2);
        $progress = $totalToDelete > 0 ? round(($deletedTotal / $totalToDelete) * 100, 1) : 0;
        
        if ($batchCount % 20 == 0 || $deletedCount == 0) {
            writeLog("バッチ#{$batchCount}: {$deletedCount}件削除完了（{$duration}秒）- 進捗: {$progress}% ({$deletedTotal}/{$totalToDelete})");
        }
        
        if ($deletedCount > 0) {
            usleep($sleepMicroseconds);
        }
        
        if ($batchCount >= 1000) {
            writeLog("⚠️ 安全上限に達しました（1000バッチ）");
            break;
        }
        
    } while ($deletedCount > 0);
    
    $totalDuration = round(microtime(true) - $startCleanup, 2);
    writeLog("✅ 削除完了: " . number_format($deletedTotal) . "件（{$totalDuration}秒）");
    
} catch (PDOException $e) {
    $errorMessage = "❌ 削除処理でエラー: " . $e->getMessage();
    writeLog($errorMessage);
    debugLog($errorMessage);
    error_log($errorMessage);
    exit(1);
}

// ==== 最終結果 ====
writeLog("=== クリーンアップ完了 ===");
writeLog("アーカイブ: " . number_format($archivedTotal) . "件");
writeLog("削除: " . number_format($deletedTotal) . "件");
writeLog("メモリ使用量: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");

debugLog("=== Script Completed Successfully ===");

exit(0);