<?php
/**
 * キャッシュ統計レポートスクリプト
 * 定期実行用（週次・月次レポート）
 */

// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ログファイルの設定
$logFile = __DIR__ . '/cache_report.log';

// ログ関数
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage;
}

writeLog("=== キャッシュ統計レポート開始 ===");

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
    $maxSizeMB = $cacheConfig['max_size_mb'] ?? 500;
    
    writeLog("キャッシュディレクトリ: $cacheDir");
    writeLog("最大ファイル数: $maxFiles");
    writeLog("最大サイズ: {$maxSizeMB}MB");
    
    if (!is_dir($cacheDir)) {
        writeLog("エラー: キャッシュディレクトリが存在しません: $cacheDir");
        exit(1);
    }
    
    // キャッシュファイルの統計
    $cacheFiles = glob($cacheDir . '/*.cache');
    $totalFiles = count($cacheFiles);
    $totalSize = 0;
    $fileAges = [];
    $currentTime = time();
    
    foreach ($cacheFiles as $file) {
        $fileSize = filesize($file);
        $totalSize += $fileSize;
        
        $fileTime = filemtime($file);
        $fileAge = $currentTime - $fileTime;
        $fileAges[] = $fileAge;
    }
    
    $totalSizeMB = round($totalSize / 1024 / 1024, 2);
    $avgFileSize = $totalFiles > 0 ? round($totalSize / $totalFiles / 1024, 2) : 0;
    
    // ファイル年齢の統計
    sort($fileAges);
    $oldestFile = $fileAges[0] ?? 0;
    $newestFile = end($fileAges) ?: 0;
    $avgAge = $fileAges ? round(array_sum($fileAges) / count($fileAges) / 86400, 1) : 0;
    
    // 7日以上古いファイル数
    $oldFiles = array_filter($fileAges, function($age) {
        return $age > 604800; // 7日
    });
    $oldFilesCount = count($oldFiles);
    
    // 使用率の計算
    $fileUsagePercent = round(($totalFiles / $maxFiles) * 100, 1);
    $sizeUsagePercent = round(($totalSizeMB / $maxSizeMB) * 100, 1);
    
    // レポートの出力
    writeLog("=== キャッシュ統計レポート ===");
    writeLog("総ファイル数: $totalFiles");
    writeLog("総サイズ: {$totalSizeMB}MB");
    writeLog("平均ファイルサイズ: {$avgFileSize}KB");
    writeLog("最古ファイル: " . round($oldestFile / 86400, 1) . "日前");
    writeLog("最新ファイル: " . round($newestFile / 86400, 1) . "日前");
    writeLog("平均ファイル年齢: {$avgAge}日");
    writeLog("7日以上古いファイル: $oldFilesCount");
    writeLog("ファイル数使用率: {$fileUsagePercent}%");
    writeLog("サイズ使用率: {$sizeUsagePercent}%");
    
    // 警告レベルのチェック
    $warnings = [];
    
    if ($fileUsagePercent > 80) {
        $warnings[] = "ファイル数使用率が80%を超えています ({$fileUsagePercent}%)";
    }
    
    if ($sizeUsagePercent > 80) {
        $warnings[] = "サイズ使用率が80%を超えています ({$sizeUsagePercent}%)";
    }
    
    if ($oldFilesCount > $totalFiles * 0.5) {
        $warnings[] = "7日以上古いファイルが50%を超えています ({$oldFilesCount}/{$totalFiles})";
    }
    
    if (!empty($warnings)) {
        writeLog("=== 警告 ===");
        foreach ($warnings as $warning) {
            writeLog("⚠️  $warning");
        }
    } else {
        writeLog("✅ キャッシュの状態は正常です");
    }
    
    // 推奨アクション
    $recommendations = [];
    
    if ($oldFilesCount > 100) {
        $recommendations[] = "古いキャッシュの削除を実行してください";
    }
    
    if ($fileUsagePercent > 70) {
        $recommendations[] = "ファイル数制限の実行を検討してください";
    }
    
    if ($avgAge > 7) {
        $recommendations[] = "キャッシュの有効期限を短縮することを検討してください";
    }
    
    if (!empty($recommendations)) {
        writeLog("=== 推奨アクション ===");
        foreach ($recommendations as $recommendation) {
            writeLog("💡 $recommendation");
        }
    }
    
    writeLog("=== キャッシュ統計レポート完了 ===");
    exit(0);
    
} catch (Exception $e) {
    writeLog("エラーが発生しました: " . $e->getMessage());
    writeLog("スタックトレース: " . $e->getTraceAsString());
    exit(1);
}
?>
