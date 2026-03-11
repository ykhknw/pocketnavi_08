<?php
/**
 * 統合キャッシュメンテナンススクリプト
 * 全てのキャッシュ管理タスクを実行
 */

// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ログファイルの設定
$logFile = __DIR__ . '/cache_maintenance.log';

// ログ関数
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage;
}

writeLog("=== 統合キャッシュメンテナンス開始 ===");

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
            'max_files' => 50000,
            'max_size_mb' => 500
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
    
    // 1. 現在の状況を確認
    $cacheFiles = glob($cacheDir . '/*.cache');
    $totalFiles = count($cacheFiles);
    $totalSize = 0;
    $currentTime = time();
    $oldFiles = [];
    
    foreach ($cacheFiles as $file) {
        $totalSize += filesize($file);
        $fileTime = filemtime($file);
        $fileAge = $currentTime - $fileTime;
        
        if ($fileAge > 604800) { // 7日以上
            $oldFiles[] = $file;
        }
    }
    
    $totalSizeMB = round($totalSize / 1024 / 1024, 2);
    $oldFilesCount = count($oldFiles);
    
    writeLog("現在の状況:");
    writeLog("  総ファイル数: $totalFiles");
    writeLog("  総サイズ: {$totalSizeMB}MB");
    writeLog("  7日以上古いファイル: $oldFilesCount");
    
    // 2. 古いキャッシュの削除
    if ($oldFilesCount > 0) {
        writeLog("古いキャッシュの削除を開始...");
        $deletedCount = 0;
        $deletedSize = 0;
        
        foreach ($oldFiles as $file) {
            $fileSize = filesize($file);
            if (unlink($file)) {
                $deletedCount++;
                $deletedSize += $fileSize;
            }
        }
        
        $deletedSizeMB = round($deletedSize / 1024 / 1024, 2);
        writeLog("古いキャッシュ削除完了: {$deletedCount}件, {$deletedSizeMB}MB");
        
        // ファイルリストを更新
        $cacheFiles = glob($cacheDir . '/*.cache');
        $totalFiles = count($cacheFiles);
    }
    
    // 3. ファイル数制限の実行
    if ($totalFiles > $maxFiles) {
        writeLog("ファイル数制限を実行...");
        
        // ファイルを更新日時でソート（古い順）
        usort($cacheFiles, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $filesToDelete = $totalFiles - $maxFiles;
        $deletedCount = 0;
        $deletedSize = 0;
        
        for ($i = 0; $i < $filesToDelete; $i++) {
            $fileSize = filesize($cacheFiles[$i]);
            if (unlink($cacheFiles[$i])) {
                $deletedCount++;
                $deletedSize += $fileSize;
            }
        }
        
        $deletedSizeMB = round($deletedSize / 1024 / 1024, 2);
        writeLog("ファイル数制限完了: {$deletedCount}件, {$deletedSizeMB}MB");
    }
    
    // 4. 最終統計
    $finalFiles = glob($cacheDir . '/*.cache');
    $finalCount = count($finalFiles);
    $finalSize = 0;
    
    foreach ($finalFiles as $file) {
        $finalSize += filesize($file);
    }
    
    $finalSizeMB = round($finalSize / 1024 / 1024, 2);
    $fileUsagePercent = round(($finalCount / $maxFiles) * 100, 1);
    
    writeLog("メンテナンス完了:");
    writeLog("  最終ファイル数: $finalCount");
    writeLog("  最終サイズ: {$finalSizeMB}MB");
    writeLog("  ファイル数使用率: {$fileUsagePercent}%");
    
    // 5. 結果の判定
    if ($fileUsagePercent > 90) {
        writeLog("⚠️  警告: ファイル数使用率が90%を超えています");
        exit(1);
    } elseif ($fileUsagePercent > 80) {
        writeLog("⚠️  注意: ファイル数使用率が80%を超えています");
        exit(0);
    } else {
        writeLog("✅ キャッシュの状態は良好です");
        exit(0);
    }
    
} catch (Exception $e) {
    writeLog("エラーが発生しました: " . $e->getMessage());
    writeLog("スタックトレース: " . $e->getTraceAsString());
    exit(1);
}

writeLog("=== 統合キャッシュメンテナンス終了 ===");
?>
