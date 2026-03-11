<?php
/**
 * HETEML 20GB容量での現実的な制限値計算
 */

echo "=== HETEML 20GB容量での制限値計算 ===\n\n";

// HETEMLの実際の容量
$totalCapacity = 20 * 1024; // 20GB = 20,480MB

// データベース以外の用途を考慮
$otherUsage = [
    'web_files' => 500,      // 500MB（画像、CSS、JS等）
    'logs' => 100,           // 100MB（ログファイル）
    'backups' => 1000,       // 1GB（バックアップ）
    'temp_files' => 200,     // 200MB（一時ファイル）
    'safety_margin' => 1000, // 1GB（安全マージン）
];

$totalOtherUsage = array_sum($otherUsage);
$availableForDB = $totalCapacity - $totalOtherUsage;

echo "HETEML容量配分:\n";
echo "- 総容量: " . number_format($totalCapacity) . "MB (20GB)\n";
echo "- その他用途: " . number_format($totalOtherUsage) . "MB\n";
echo "- データベース用: " . number_format($availableForDB) . "MB\n\n";

// データベース内のテーブル配分
$dbTables = [
    'buildings_table_3' => 0.4,        // 40%（メインの建築物データ）
    'individual_architects_3' => 0.1,  // 10%（建築家データ）
    'other_tables' => 0.3,             // 30%（その他のテーブル）
    'search_history' => 0.2,           // 20%（検索履歴）
];

echo "データベース内テーブル配分:\n";
foreach ($dbTables as $table => $percentage) {
    $allocated = $availableForDB * $percentage;
    echo "- {$table}: " . number_format($allocated) . "MB (" . ($percentage * 100) . "%)\n";
}

$searchHistoryLimit = $availableForDB * $dbTables['search_history'];

echo "\n=== 検索履歴テーブル用制限値 ===\n";
echo "推奨制限値: " . number_format($searchHistoryLimit) . "MB\n\n";

// 現在のデータ増加率（1日12.4MB）
$dailyGrowth = 12.4;
$monthlyGrowth = $dailyGrowth * 30;

echo "=== データ増加率分析 ===\n";
echo "1日の増加量: {$dailyGrowth}MB\n";
echo "1ヶ月の増加量: " . number_format($monthlyGrowth) . "MB\n\n";

// 各保持期間でのサイズ計算
$retentionPeriods = [30, 60, 90, 120, 180, 365];

echo "=== 保持期間別のテーブルサイズ予測 ===\n";
echo "保持期間\t予想サイズ\t使用率\t状況\n";
echo "--------\t--------\t------\t----\n";

foreach ($retentionPeriods as $days) {
    $estimatedSize = $dailyGrowth * $days;
    $usageRate = ($estimatedSize / $searchHistoryLimit) * 100;
    
    $status = '';
    if ($usageRate > 90) {
        $status = '🚨 危険';
    } elseif ($usageRate > 70) {
        $status = '⚠️ 警告';
    } elseif ($usageRate > 50) {
        $status = '⚠️ 注意';
    } else {
        $status = '✅ 安全';
    }
    
    echo "{$days}日\t\t" . round($estimatedSize, 1) . "MB\t\t" . round($usageRate, 1) . "%\t{$status}\n";
}

echo "\n";

// 推奨設定の計算
echo "=== 推奨設定 ===\n\n";

// 70%使用率を維持する場合
$safeUsageRate = 70;
$safeSize = $searchHistoryLimit * ($safeUsageRate / 100);
$recommendedRetention = floor($safeSize / $dailyGrowth);

echo "70%使用率を維持する場合:\n";
echo "- 推奨保持期間: {$recommendedRetention}日\n";
echo "- 予想サイズ: " . round($dailyGrowth * $recommendedRetention, 1) . "MB\n";
echo "- 使用率: " . round(($dailyGrowth * $recommendedRetention / $searchHistoryLimit) * 100, 1) . "%\n\n";

// 50%使用率を維持する場合
$conservativeUsageRate = 50;
$conservativeSize = $searchHistoryLimit * ($conservativeUsageRate / 100);
$conservativeRetention = floor($conservativeSize / $dailyGrowth);

echo "50%使用率を維持する場合:\n";
echo "- 推奨保持期間: {$conservativeRetention}日\n";
echo "- 予想サイズ: " . round($dailyGrowth * $conservativeRetention, 1) . "MB\n";
echo "- 使用率: " . round(($dailyGrowth * $conservativeRetention / $searchHistoryLimit) * 100, 1) . "%\n\n";

// アラート設定の推奨
echo "=== アラート設定の推奨 ===\n";
$warningThreshold = $searchHistoryLimit * 0.6;  // 60%
$criticalThreshold = $searchHistoryLimit * 0.8; // 80%

echo "警告閾値: " . number_format($warningThreshold) . "MB (60%)\n";
echo "緊急閾値: " . number_format($criticalThreshold) . "MB (80%)\n\n";

// レコード数ベースの推奨
echo "=== レコード数ベースの推奨 ===\n";
// 1レコードあたりの平均サイズを推定（JSONフィールド含む）
$avgRecordSize = 0.5; // KB
$maxRecords = ($searchHistoryLimit * 1024) / $avgRecordSize; // KBに変換して計算

echo "推定最大レコード数: " . number_format($maxRecords) . " 件\n";
echo "警告レコード数: " . number_format($maxRecords * 0.6) . " 件\n";
echo "緊急レコード数: " . number_format($maxRecords * 0.8) . " 件\n\n";

// 最終推奨
echo "=== 最終推奨設定 ===\n\n";

if ($recommendedRetention >= 90) {
    echo "✅ 余裕のある環境: 90日保持が可能\n";
    $finalRetention = 90;
    $cleanupFrequency = 'weekly';
} elseif ($recommendedRetention >= 60) {
    echo "⚠️ 中程度の環境: 60日保持が推奨\n";
    $finalRetention = 60;
    $cleanupFrequency = 'weekly';
} else {
    echo "🚨 高負荷環境: 30日保持が必要\n";
    $finalRetention = 30;
    $cleanupFrequency = 'daily';
}

echo "\n推奨設定:\n";
echo "- 保持期間: {$finalRetention}日\n";
echo "- クリーンアップ頻度: {$cleanupFrequency}\n";
echo "- テーブルサイズ制限: " . number_format($searchHistoryLimit) . "MB\n";
echo "- 警告閾値: " . number_format($warningThreshold) . "MB\n";
echo "- 緊急閾値: " . number_format($criticalThreshold) . "MB\n";
echo "- 警告レコード数: " . number_format($maxRecords * 0.6) . " 件\n";
echo "- 緊急レコード数: " . number_format($maxRecords * 0.8) . " 件\n\n";

echo "cron設定:\n";
if ($cleanupFrequency === 'daily') {
    echo "0 2 * * * /usr/local/bin/php /home/your-account/public_html/scripts/heteml_cleanup_search_history.php {$finalRetention} --archive\n";
} else {
    echo "0 2 * * 0 /usr/local/bin/php /home/your-account/public_html/scripts/heteml_cleanup_search_history.php {$finalRetention} --archive\n";
}
