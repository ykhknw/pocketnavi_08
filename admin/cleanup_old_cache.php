<?php
/**
 * 古いキャッシュの自動削除スクリプト
 * cronジョブで定期実行用
 */

// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ログファイルの設定
$logFile = __DIR__ . '/cache_cleanup.log';

// ログ関数
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage;
}

writeLog("=== 古いキャッシュ削除スクリプト開始 ===");

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
            'default_ttl' => 3600
        ];
    }
    
    $cacheDir = $cacheConfig['cache_dir'] ?? 'cache/search';
    writeLog("キャッシュディレクトリ: $cacheDir");
    
    if (!is_dir($cacheDir)) {
        writeLog("エラー: キャッシュディレクトリが存在しません: $cacheDir");
        exit(1);
    }
    
    // キャッシュファイルの取得
    $cacheFiles = glob($cacheDir . '/*.cache');
    $totalFiles = count($cacheFiles);
    writeLog("総キャッシュファイル数: $totalFiles");
    
    if ($totalFiles === 0) {
        writeLog("削除対象のファイルがありません");
        exit(0);
    }
    
    // 古いファイルの削除
    $deletedCount = 0;
    $deletedSize = 0;
    $currentTime = time();
    $cutoffTime = $currentTime - 604800; // 7日前
    
    writeLog("削除対象: " . date('Y-m-d H:i:s', $cutoffTime) . " より古いファイル");
    
    foreach ($cacheFiles as $file) {
        $fileTime = filemtime($file);
        $fileAge = $currentTime - $fileTime;
        $fileSize = filesize($file);
        
        if ($fileTime < $cutoffTime) {
            if (unlink($file)) {
                $deletedCount++;
                $deletedSize += $fileSize;
                writeLog("削除: " . basename($file) . " (サイズ: " . round($fileSize / 1024, 2) . "KB, 年齢: " . round($fileAge / 86400, 1) . "日)");
            } else {
                writeLog("削除失敗: " . basename($file));
            }
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
        writeLog("古いキャッシュの削除が完了しました");
        exit(0);
    } else {
        writeLog("削除対象のファイルがありませんでした");
        exit(0);
    }
    
} catch (Exception $e) {
    writeLog("エラーが発生しました: " . $e->getMessage());
    writeLog("スタックトレース: " . $e->getTraceAsString());
    exit(1);
}

writeLog("=== 古いキャッシュ削除スクリプト終了 ===");
?>
