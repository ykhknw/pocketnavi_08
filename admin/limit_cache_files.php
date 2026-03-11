<?php
/**
 * キャッシュファイル数制限スクリプト
 * cronジョブで定期実行用
 */

// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ログファイルの設定
$logFile = __DIR__ . '/cache_limit.log';

// ログ関数
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage;
}

writeLog("=== キャッシュファイル数制限スクリプト開始 ===");

try {
    // キャッシュ設定ファイルの読み込み
    $cacheConfigFile = '../config/cache_config.php';
    $cacheConfig = [];
    
    if (file_exists($cacheConfigFile)) {
        $cacheConfig = include $cacheConfigFile;
    } else {
        // デフォルト設定
        $cacheConfig = [
            'cache_dir' => 'cache/search',
            'max_files' => 50000
        ];
    }
    
    $cacheDir = $cacheConfig['cache_dir'] ?? 'cache/search';
    $maxFiles = $cacheConfig['max_files'] ?? 50000;
    
    writeLog("キャッシュディレクトリ: $cacheDir");
    writeLog("最大ファイル数: $maxFiles");
    
    if (!is_dir($cacheDir)) {
        writeLog("エラー: キャッシュディレクトリが存在しません: $cacheDir");
        exit(1);
    }
    
    // キャッシュファイルの取得
    $cacheFiles = glob($cacheDir . '/*.cache');
    $totalFiles = count($cacheFiles);
    writeLog("現在のファイル数: $totalFiles");
    
    if ($totalFiles <= $maxFiles) {
        writeLog("ファイル数が制限内です。削除は不要です。");
        exit(0);
    }
    
    // ファイルを更新日時でソート（古い順）
    usort($cacheFiles, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    $filesToDelete = $totalFiles - $maxFiles;
    writeLog("削除対象ファイル数: $filesToDelete");
    
    // 古いファイルから削除
    $deletedCount = 0;
    $deletedSize = 0;
    
    for ($i = 0; $i < $filesToDelete; $i++) {
        $file = $cacheFiles[$i];
        $fileTime = filemtime($file);
        $fileSize = filesize($file);
        $fileAge = time() - $fileTime;
        
        if (unlink($file)) {
            $deletedCount++;
            $deletedSize += $fileSize;
            writeLog("削除: " . basename($file) . " (サイズ: " . round($fileSize / 1024, 2) . "KB, 年齢: " . round($fileAge / 86400, 1) . "日)");
        } else {
            writeLog("削除失敗: " . basename($file));
        }
    }
    
    $deletedSizeMB = round($deletedSize / 1024 / 1024, 2);
    $remainingFiles = $totalFiles - $deletedCount;
    
    writeLog("削除完了:");
    writeLog("  削除されたファイル数: $deletedCount");
    writeLog("  削除されたサイズ: {$deletedSizeMB}MB");
    writeLog("  残りのファイル数: $remainingFiles");
    
    // 結果の判定
    if ($deletedCount > 0) {
        writeLog("キャッシュファイル数の制限が完了しました");
        exit(0);
    } else {
        writeLog("削除されたファイルがありませんでした");
        exit(0);
    }
    
} catch (Exception $e) {
    writeLog("エラーが発生しました: " . $e->getMessage());
    writeLog("スタックトレース: " . $e->getTraceAsString());
    exit(1);
}

writeLog("=== キャッシュファイル数制限スクリプト終了 ===");
?>
