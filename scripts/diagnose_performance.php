<?php
/**
 * パフォーマンス診断スクリプト
 * Windows環境でのlocalhost/index.php表示遅延の原因を特定するための診断ツール
 */

echo "========================================\n";
echo "パフォーマンス診断ツール\n";
echo "========================================\n\n";

$results = [];

// 1. PHP設定の確認
echo "[1] PHP設定の確認\n";
echo "----------------------------------------\n";
$phpSettings = [
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'default_socket_timeout' => ini_get('default_socket_timeout'),
    'max_input_time' => ini_get('max_input_time'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
];

foreach ($phpSettings as $key => $value) {
    echo sprintf("  %s: %s\n", $key, $value);
}
$results['php_settings'] = $phpSettings;
echo "\n";

// 2. データベース接続のテスト
echo "[2] データベース接続のテスト\n";
echo "----------------------------------------\n";
$dbStartTime = microtime(true);
try {
    require_once __DIR__ . '/../config/database_unified.php';
    $pdo = getDatabaseConnection();
    $dbEndTime = microtime(true);
    $dbConnectionTime = round(($dbEndTime - $dbStartTime) * 1000, 2);
    
    echo "  ✓ データベース接続成功\n";
    echo sprintf("  接続時間: %s ms\n", $dbConnectionTime);
    
    // データベース名の確認
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo sprintf("  接続DB: %s\n", $dbName);
    
    // 簡単なクエリの実行時間テスト
    $queryStartTime = microtime(true);
    $stmt = $pdo->query("SELECT 1");
    $stmt->fetch();
    $queryEndTime = microtime(true);
    $queryTime = round(($queryEndTime - $queryStartTime) * 1000, 2);
    echo sprintf("  クエリ実行時間: %s ms\n", $queryTime);
    
    $results['database'] = [
        'status' => 'success',
        'connection_time_ms' => $dbConnectionTime,
        'query_time_ms' => $queryTime,
        'database_name' => $dbName
    ];
    
    if ($dbConnectionTime > 1000) {
        echo "  ⚠ 警告: データベース接続に1秒以上かかっています\n";
    }
    if ($queryTime > 100) {
        echo "  ⚠ 警告: クエリ実行に100ms以上かかっています\n";
    }
    
} catch (Exception $e) {
    $dbEndTime = microtime(true);
    $dbConnectionTime = round(($dbEndTime - $dbStartTime) * 1000, 2);
    echo "  ✗ データベース接続失敗\n";
    echo sprintf("  エラー: %s\n", $e->getMessage());
    echo sprintf("  試行時間: %s ms\n", $dbConnectionTime);
    $results['database'] = [
        'status' => 'error',
        'error' => $e->getMessage(),
        'connection_time_ms' => $dbConnectionTime
    ];
}
echo "\n";

// 3. ファイルシステムの確認
echo "[3] ファイルシステムの確認\n";
echo "----------------------------------------\n";
$filesToCheck = [
    'index.php',
    'config/database_unified.php',
    'src/Views/includes/functions.php',
    'src/Services/CachedBuildingService.php',
    'cache'
];

foreach ($filesToCheck as $file) {
    $filePath = __DIR__ . '/../' . $file;
    if (file_exists($filePath)) {
        $readStartTime = microtime(true);
        $content = file_get_contents($filePath);
        $readEndTime = microtime(true);
        $readTime = round(($readEndTime - $readStartTime) * 1000, 2);
        $fileSize = filesize($filePath);
        
        echo sprintf("  %s: 存在 (サイズ: %s bytes, 読み込み時間: %s ms)\n", 
            $file, 
            number_format($fileSize), 
            $readTime
        );
        
        if ($readTime > 100) {
            echo "    ⚠ 警告: ファイル読み込みに100ms以上かかっています\n";
        }
    } else {
        echo sprintf("  %s: 存在しない\n", $file);
    }
}
echo "\n";

// 4. 環境変数の確認
echo "[4] 環境変数の確認\n";
echo "----------------------------------------\n";
$envVars = ['DB_HOST', 'DB_NAME', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_PORT'];
foreach ($envVars as $var) {
    $value = getenv($var);
    if ($value !== false) {
        // パスワードは表示しない
        if ($var === 'DB_PASSWORD') {
            echo sprintf("  %s: %s\n", $var, str_repeat('*', strlen($value)));
        } else {
            echo sprintf("  %s: %s\n", $var, $value);
        }
    } else {
        echo sprintf("  %s: 未設定\n", $var);
    }
}
echo "\n";

// 5. キャッシュディレクトリの確認
echo "[5] キャッシュディレクトリの確認\n";
echo "----------------------------------------\n";
$cacheDir = __DIR__ . '/../cache';
if (is_dir($cacheDir)) {
    $cacheStartTime = microtime(true);
    $cacheFiles = glob($cacheDir . '/*');
    $cacheEndTime = microtime(true);
    $cacheScanTime = round(($cacheEndTime - $cacheStartTime) * 1000, 2);
    
    $cacheCount = count($cacheFiles);
    $cacheSize = 0;
    foreach ($cacheFiles as $file) {
        if (is_file($file)) {
            $cacheSize += filesize($file);
        }
    }
    
    echo sprintf("  キャッシュファイル数: %s\n", number_format($cacheCount));
    echo sprintf("  キャッシュ総サイズ: %s bytes (%s MB)\n", 
        number_format($cacheSize), 
        round($cacheSize / 1024 / 1024, 2)
    );
    echo sprintf("  スキャン時間: %s ms\n", $cacheScanTime);
    
    if ($cacheScanTime > 1000) {
        echo "  ⚠ 警告: キャッシュディレクトリのスキャンに1秒以上かかっています\n";
    }
    
    $results['cache'] = [
        'file_count' => $cacheCount,
        'total_size_bytes' => $cacheSize,
        'scan_time_ms' => $cacheScanTime
    ];
} else {
    echo "  キャッシュディレクトリが存在しません\n";
    $results['cache'] = ['status' => 'not_found'];
}
echo "\n";

// 6. 外部リソースの確認（簡易版）
echo "[6] 外部リソースの確認\n";
echo "----------------------------------------\n";
$externalResources = [
    'https://www.googletagmanager.com/gtag/js?id=G-9FY04VHM17',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://unpkg.com/lucide@latest',
];

foreach ($externalResources as $url) {
    $resourceStartTime = microtime(true);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $resourceEndTime = microtime(true);
    $resourceTime = round(($resourceEndTime - $resourceStartTime) * 1000, 2);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 400) {
        echo sprintf("  %s: アクセス可能 (応答時間: %s ms)\n", 
            parse_url($url, PHP_URL_HOST), 
            $resourceTime
        );
    } else {
        echo sprintf("  %s: アクセス不可 (HTTP %s, 応答時間: %s ms)\n", 
            parse_url($url, PHP_URL_HOST), 
            $httpCode, 
            $resourceTime
        );
    }
    
    if ($resourceTime > 2000) {
        echo "    ⚠ 警告: 外部リソースへのアクセスに2秒以上かかっています\n";
    }
}
echo "\n";

// 7. メモリ使用量の確認
echo "[7] メモリ使用量の確認\n";
echo "----------------------------------------\n";
$memoryUsage = memory_get_usage(true);
$memoryPeak = memory_get_peak_usage(true);
echo sprintf("  現在のメモリ使用量: %s MB\n", round($memoryUsage / 1024 / 1024, 2));
echo sprintf("  ピークメモリ使用量: %s MB\n", round($memoryPeak / 1024 / 1024, 2));
$results['memory'] = [
    'current_mb' => round($memoryUsage / 1024 / 1024, 2),
    'peak_mb' => round($memoryPeak / 1024 / 1024, 2)
];
echo "\n";

// 結果のサマリー
echo "========================================\n";
echo "診断結果サマリー\n";
echo "========================================\n";

$warnings = [];
if (isset($results['database']['connection_time_ms']) && $results['database']['connection_time_ms'] > 1000) {
    $warnings[] = "データベース接続が遅い（" . $results['database']['connection_time_ms'] . "ms）";
}
if (isset($results['cache']['scan_time_ms']) && $results['cache']['scan_time_ms'] > 1000) {
    $warnings[] = "キャッシュディレクトリのスキャンが遅い（" . $results['cache']['scan_time_ms'] . "ms）";
}

if (empty($warnings)) {
    echo "✓ 明らかな問題は見つかりませんでした。\n";
    echo "  ブラウザの開発者ツールでNetworkタブを確認してください。\n";
} else {
    echo "⚠ 以下の問題が検出されました:\n";
    foreach ($warnings as $warning) {
        echo "  - " . $warning . "\n";
    }
}

echo "\n";
echo "詳細な診断手順は '診断手順_パフォーマンス問題.md' を参照してください。\n";
