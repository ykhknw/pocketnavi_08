#!/usr/local/php/8.3/bin/php
<?php
/**
 * ！！！重要！！！
 * 秀丸で、改行コード「LF」で保存してからアップロードすること
 * 
 * キャッシュファイルクリーンアップスクリプト（CRON運用版）
 * 検索キャッシュファイル数を制限し、古いファイルを自動削除
 * 
 * 使用方法:
 *   CRON: /path/to/cleanup_cache_files.php [max_files] [max_days]
 * 
 * 引数（省略可能）:
 *   max_files - 最大ファイル数　デフォルト: 50000
 *   max_days  - 最大保持日数　デフォルト: 7（この日数を超えるファイルも削除）
 * 
 * 例:
 *   毎日午前4時に実行
 *   0 4 * * * /usr/local/php/8.3/bin/php /path/to/cleanup_cache_files.php
 * 
 *   ファイル数を30,000件、保持期間を3日に設定
 *   0 4 * * * /usr/local/php/8.3/bin/php /path/to/cleanup_cache_files.php 30000 3
 */

// エラーレポートを有効化
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

// ==== 設定 ====
$logFile = __DIR__ . '/../logs/cleanup_cache_files_cron.log';
$batchSize = 1000; // 一度に削除するファイル数

// コマンドライン引数から設定を取得（デフォルト値あり）
$maxFiles = 50000;
$maxDays = 7;

if (isset($argv) && is_array($argv)) {
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $maxFiles = (int)$argv[1];
    }
    if (isset($argv[2]) && is_numeric($argv[2])) {
        $maxDays = (int)$argv[2];
    }
}

// ログ出力関数
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    
    echo $logLine;
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// グローバルエラーハンドラ
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Warningレベルのエラーは無視（ファイル操作で発生する可能性）
    if ($errno === E_WARNING || $errno === E_NOTICE) {
        return true;
    }
    $message = "PHP Error [$errno]: $errstr in $errfile on line $errline";
    writeLog($message);
    error_log($message);
});

set_exception_handler(function($exception) {
    $message = "Exception: " . $exception->getMessage();
    writeLog($message);
    error_log($message);
});

// ログディレクトリ確認
if (!is_dir(dirname($logFile))) {
    @mkdir(dirname($logFile), 0775, true);
}

writeLog("=== キャッシュファイルクリーンアップ開始 ===");
writeLog("最大ファイル数: " . number_format($maxFiles) . "件");
writeLog("最大保持日数: {$maxDays}日");
writeLog("バッチサイズ: {$batchSize}件");

// ==== キャッシュディレクトリの検索 ====
$cacheDir = null;
$possiblePaths = [
    __DIR__ . '/../cache/search',
    __DIR__ . '/../../cache/search',
    'cache/search',
    '../cache/search',
    './cache/search',
    'cache/search_results'
];

foreach ($possiblePaths as $path) {
    if (is_dir($path)) {
        $cacheDir = $path;
        break;
    }
}

if (!$cacheDir) {
    writeLog("❌ キャッシュディレクトリが見つかりません");
    writeLog("検索パス: " . implode(', ', $possiblePaths));
    exit(1);
}

$cacheDir = realpath($cacheDir);
writeLog("✅ キャッシュディレクトリ: {$cacheDir}");

// ==== キャッシュファイルの取得 ====
$startTime = microtime(true);
$cacheFiles = @glob($cacheDir . '/*.cache');

if ($cacheFiles === false) {
    writeLog("❌ キャッシュファイルの取得に失敗しました");
    exit(1);
}

$totalFiles = count($cacheFiles);
writeLog("現在のファイル数: " . number_format($totalFiles) . "件");

if ($totalFiles === 0) {
    writeLog("✅ キャッシュファイルがありません。正常終了します。");
    exit(0);
}

// ==== ファイル情報の取得とソート ====
writeLog("ファイル情報を取得中...");
$fileInfo = [];
$currentTime = time();

foreach ($cacheFiles as $file) {
    if (!file_exists($file)) {
        continue;
    }
    
    $mtime = @filemtime($file);
    $size = @filesize($file);
    
    if ($mtime === false) {
        $mtime = 0;
    }
    if ($size === false) {
        $size = 0;
    }
    
    $fileInfo[] = [
        'path' => $file,
        'mtime' => $mtime,
        'size' => $size,
        'age_days' => round(($currentTime - $mtime) / 86400, 1)
    ];
}

// 更新日時でソート（古い順）
usort($fileInfo, function($a, $b) {
    return $a['mtime'] - $b['mtime'];
});

$totalSize = array_sum(array_column($fileInfo, 'size'));
$totalSizeMB = round($totalSize / 1024 / 1024, 2);
writeLog("総サイズ: {$totalSizeMB} MB");

// ==== 削除対象の判定 ====
$filesToDelete = [];

// 1. 古すぎるファイル（保持期間超過）
$maxAge = $maxDays * 86400; // 日数を秒に変換
foreach ($fileInfo as $info) {
    if (($currentTime - $info['mtime']) > $maxAge) {
        $filesToDelete[] = $info;
    }
}

$oldFilesCount = count($filesToDelete);
writeLog("{$maxDays}日以上古いファイル: {$oldFilesCount}件");

// 2. ファイル数超過分（古い順に追加）
if ($totalFiles > $maxFiles) {
    $excessCount = $totalFiles - $maxFiles;
    writeLog("ファイル数超過: " . number_format($excessCount) . "件");
    
    // 既に削除対象のファイルパスを取得
    $alreadyMarked = array_column($filesToDelete, 'path');
    
    // 追加で削除が必要なファイルを選択
    foreach ($fileInfo as $info) {
        if (count($filesToDelete) >= ($oldFilesCount + $excessCount)) {
            break;
        }
        if (!in_array($info['path'], $alreadyMarked)) {
            $filesToDelete[] = $info;
        }
    }
}

$totalToDelete = count($filesToDelete);

if ($totalToDelete === 0) {
    writeLog("✅ 削除対象なし。ファイル数・保持期間ともに正常範囲です。");
    writeLog("メモリ使用量: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");
    writeLog("処理時間: " . round(microtime(true) - $startTime, 2) . " 秒");
    exit(0);
}

writeLog("--- 削除処理開始 ---");
writeLog("削除対象: " . number_format($totalToDelete) . "件");

// ==== 削除処理（バッチ） ====
$deletedCount = 0;
$deletedSize = 0;
$failedCount = 0;
$batchCount = 0;

foreach (array_chunk($filesToDelete, $batchSize) as $batch) {
    $batchCount++;
    $batchStartTime = microtime(true);
    $batchDeletedCount = 0;
    $batchDeletedSize = 0;
    
    foreach ($batch as $info) {
        if (file_exists($info['path'])) {
            if (@unlink($info['path'])) {
                $deletedCount++;
                $deletedSize += $info['size'];
                $batchDeletedCount++;
                $batchDeletedSize += $info['size'];
            } else {
                $failedCount++;
            }
        }
    }
    
    $batchDuration = round(microtime(true) - $batchStartTime, 2);
    $progress = round(($deletedCount / $totalToDelete) * 100, 1);
    
    if ($batchCount % 10 == 0 || $batchDeletedCount === 0) {
        $batchSizeMB = round($batchDeletedSize / 1024 / 1024, 2);
        writeLog("バッチ#{$batchCount}: {$batchDeletedCount}件削除 ({$batchSizeMB}MB) - 進捗: {$progress}% (" . number_format($deletedCount) . "/" . number_format($totalToDelete) . ")");
    }
    
    // CPU負荷軽減のため少し待機
    if ($batchDeletedCount > 0) {
        usleep(50000); // 50ms
    }
}

$totalDuration = round(microtime(true) - $startTime, 2);
$deletedSizeMB = round($deletedSize / 1024 / 1024, 2);
$remainingFiles = $totalFiles - $deletedCount;

writeLog("✅ 削除完了: " . number_format($deletedCount) . "件 ({$deletedSizeMB} MB)");
if ($failedCount > 0) {
    writeLog("⚠️ 削除失敗: {$failedCount}件");
}
writeLog("残りファイル数: " . number_format($remainingFiles) . "件");

// ==== 最終結果 ====
writeLog("=== クリーンアップ完了 ===");
writeLog("削除: " . number_format($deletedCount) . "件");
writeLog("削除サイズ: {$deletedSizeMB} MB");
writeLog("処理時間: {$totalDuration} 秒");
writeLog("メモリ使用量: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");

exit(0);

