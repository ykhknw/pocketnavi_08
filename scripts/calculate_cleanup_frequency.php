#!/usr/local/php/8.3/bin/php
<?php
/**
 * データ増加率に基づくクリーンアップ頻度計算ツール
 * 
 * 現在のデータ: 6.2MB (12時間)
 * 1日: 12.4MB
 * 1ヶ月: 372MB
 */

// データ増加率の計算
$currentSize = 6.2; // MB
$timePeriod = 12; // 時間
$dailyGrowth = ($currentSize / $timePeriod) * 24; // 1日の増加量
$monthlyGrowth = $dailyGrowth * 30; // 1ヶ月の増加量

echo "=== データ増加率分析 ===\n\n";

echo "現在の状況:\n";
echo "- データサイズ: {$currentSize}MB\n";
echo "- 期間: {$timePeriod}時間\n";
echo "- 1日の増加量: " . round($dailyGrowth, 2) . "MB\n";
echo "- 1ヶ月の増加量: " . round($monthlyGrowth, 2) . "MB\n\n";

// HETEMLの制約
$hetemlLimits = [
    'max_table_size' => 100, // MB
    'warning_threshold' => 80, // MB
    'critical_threshold' => 90, // MB
];

echo "HETEML制約:\n";
echo "- 最大テーブルサイズ: {$hetemlLimits['max_table_size']}MB\n";
echo "- 警告閾値: {$hetemlLimits['warning_threshold']}MB\n";
echo "- 緊急閾値: {$hetemlLimits['critical_threshold']}MB\n\n";

// 各保持期間でのサイズ計算
$retentionPeriods = [30, 60, 90, 120, 180, 365]; // 日数

echo "=== 保持期間別のテーブルサイズ予測 ===\n\n";
echo "保持期間\t予想サイズ\t状況\n";
echo "--------\t--------\t----\n";

foreach ($retentionPeriods as $days) {
    $estimatedSize = $dailyGrowth * $days;
    $status = '';
    
    if ($estimatedSize > $hetemlLimits['critical_threshold']) {
        $status = '🚨 緊急';
    } elseif ($estimatedSize > $hetemlLimits['warning_threshold']) {
        $status = '⚠️ 警告';
    } elseif ($estimatedSize > $hetemlLimits['max_table_size'] * 0.7) {
        $status = '⚠️ 注意';
    } else {
        $status = '✅ 安全';
    }
    
    echo "{$days}日\t\t" . round($estimatedSize, 1) . "MB\t\t{$status}\n";
}

echo "\n";

// 推奨クリーンアップ頻度の計算
echo "=== 推奨クリーンアップ頻度 ===\n\n";

// 安全なサイズ（70MB）を維持するための保持期間
$safeSize = 70; // MB
$recommendedRetention = floor($safeSize / $dailyGrowth);

echo "安全なサイズ（70MB）を維持する場合:\n";
echo "- 推奨保持期間: {$recommendedRetention}日\n";
echo "- 予想サイズ: " . round($dailyGrowth * $recommendedRetention, 1) . "MB\n\n";

// 警告閾値（80MB）を維持するための保持期間
$warningRetention = floor($hetemlLimits['warning_threshold'] / $dailyGrowth);

echo "警告閾値（80MB）を維持する場合:\n";
echo "- 推奨保持期間: {$warningRetention}日\n";
echo "- 予想サイズ: " . round($dailyGrowth * $warningRetention, 1) . "MB\n\n";

// クリーンアップ頻度の推奨
echo "=== クリーンアップ頻度の推奨 ===\n\n";

$cleanupFrequencies = [
    'daily' => 1,
    'weekly' => 7,
    'biweekly' => 14,
    'monthly' => 30
];

foreach ($cleanupFrequencies as $frequency => $days) {
    $sizeAtCleanup = $dailyGrowth * $days;
    $status = '';
    
    if ($sizeAtCleanup > $hetemlLimits['critical_threshold']) {
        $status = '🚨 危険';
    } elseif ($sizeAtCleanup > $hetemlLimits['warning_threshold']) {
        $status = '⚠️ 警告';
    } elseif ($sizeAtCleanup > $hetemlLimits['max_table_size'] * 0.7) {
        $status = '⚠️ 注意';
    } else {
        $status = '✅ 安全';
    }
    
    echo "{$frequency} ({$days}日): " . round($sizeAtCleanup, 1) . "MB - {$status}\n";
}

echo "\n";

// 最終推奨
echo "=== 最終推奨 ===\n\n";

if ($dailyGrowth > 5) {
    echo "🚨 高負荷環境: 毎日クリーンアップを推奨\n";
    echo "- 保持期間: 30日\n";
    echo "- クリーンアップ頻度: 毎日\n";
    echo "- 予想サイズ: " . round($dailyGrowth * 30, 1) . "MB\n";
} elseif ($dailyGrowth > 2) {
    echo "⚠️ 中負荷環境: 週1回クリーンアップを推奨\n";
    echo "- 保持期間: 60日\n";
    echo "- クリーンアップ頻度: 週1回\n";
    echo "- 予想サイズ: " . round($dailyGrowth * 60, 1) . "MB\n";
} else {
    echo "✅ 低負荷環境: 月1回クリーンアップで十分\n";
    echo "- 保持期間: 90日\n";
    echo "- クリーンアップ頻度: 月1回\n";
    echo "- 予想サイズ: " . round($dailyGrowth * 90, 1) . "MB\n";
}

echo "\n";

// HETEML用の具体的な設定
echo "=== HETEML用推奨設定 ===\n\n";

$recommendedRetention = min(60, floor($safeSize / $dailyGrowth));
$recommendedFrequency = $dailyGrowth > 3 ? 'daily' : 'weekly';

echo "推奨設定:\n";
echo "- 保持期間: {$recommendedRetention}日\n";
echo "- クリーンアップ頻度: {$recommendedFrequency}\n";
echo "- 予想サイズ: " . round($dailyGrowth * $recommendedRetention, 1) . "MB\n\n";

echo "cron設定:\n";
if ($recommendedFrequency === 'daily') {
    echo "0 2 * * * /usr/local/bin/php /home/your-account/public_html/scripts/heteml_cleanup_search_history.php {$recommendedRetention} --archive\n";
} else {
    echo "0 2 * * 0 /usr/local/bin/php /home/your-account/public_html/scripts/heteml_cleanup_search_history.php {$recommendedRetention} --archive\n";
}

echo "\n";

// 監視の推奨
echo "=== 監視の推奨 ===\n\n";
echo "以下の条件でアラートを設定してください:\n";
echo "- テーブルサイズが50MBを超えた場合: 警告\n";
echo "- テーブルサイズが70MBを超えた場合: 緊急\n";
echo "- レコード数が30,000件を超えた場合: 警告\n";
echo "- レコード数が45,000件を超えた場合: 緊急\n\n";

echo "監視方法:\n";
echo "- 週1回: Web管理画面で統計確認\n";
echo "- 月1回: ログファイルの確認\n";
echo "- 緊急時: 手動クリーンアップ実行\n";
