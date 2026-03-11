<?php
/**
 * 緊急クリーンアップスクリプト
 * 
 * 高負荷環境（1日12.4MB増加）での緊急対応用
 * 保持期間を極端に短縮してデータベースサイズを制御
 */

// スクリプトのパスを取得
$scriptDir = dirname(__FILE__);
$projectRoot = dirname($scriptDir);

// プロジェクトのルートディレクトリをインクルードパスに追加
set_include_path($projectRoot . PATH_SEPARATOR . get_include_path());

// 必要なファイルを読み込み
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/src/Services/SearchLogService.php';

// 緊急時の制約を設定
ini_set('max_execution_time', 30);
ini_set('memory_limit', '128M');

/**
 * 緊急クリーンアップ実行
 */
function performEmergencyCleanup() {
    echo "🚨 緊急クリーンアップ開始\n";
    echo "対象: 3日より古いデータ\n";
    echo "アーカイブ: 無効（高速化のため）\n\n";
    
    $startTime = microtime(true);
    
    try {
        $searchLogService = new SearchLogService();
        $db = $searchLogService->getDatabase();
        
        // 3日より古いデータを削除（アーカイブなし）
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-3 days'));
        
        $deleteSql = "
            DELETE FROM global_search_history 
            WHERE searched_at < ?
        ";
        
        $stmt = $db->prepare($deleteSql);
        $stmt->execute([$cutoffDate]);
        $deletedCount = $stmt->rowCount();
        
        $executionTime = round(microtime(true) - $startTime, 2);
        
        echo "✅ 緊急クリーンアップ完了\n";
        echo "削除されたレコード: " . number_format($deletedCount) . " 件\n";
        echo "実行時間: {$executionTime} 秒\n";
        
        // 統計情報を表示
        showQuickStats($db);
        
    } catch (Exception $e) {
        echo "❌ エラー: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * クイック統計情報表示
 */
function showQuickStats($db) {
    echo "\n📊 クイック統計情報:\n";
    
    try {
        // レコード数
        $stmt = $db->query("SELECT COUNT(*) as count FROM global_search_history");
        $count = $stmt->fetch()['count'];
        echo "- 現在のレコード数: " . number_format($count) . " 件\n";
        
        // テーブルサイズ
        $stmt = $db->query("
            SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'global_search_history'
        ");
        $size = $stmt->fetch()['size_mb'];
        echo "- テーブルサイズ: {$size} MB\n";
        
        // 最古のレコード
        $stmt = $db->query("SELECT MIN(searched_at) as oldest FROM global_search_history");
        $oldest = $stmt->fetch()['oldest'];
        echo "- 最古のレコード: {$oldest}\n";
        
        // 状況判定
        if ($size > 60) {
            echo "🚨 警告: テーブルサイズが60MBを超えています\n";
        } elseif ($size > 40) {
            echo "⚠️ 注意: テーブルサイズが40MBを超えています\n";
        } else {
            echo "✅ 正常: テーブルサイズは安全範囲内です\n";
        }
        
    } catch (Exception $e) {
        echo "統計情報取得エラー: " . $e->getMessage() . "\n";
    }
}

/**
 * 段階的クリーンアップ
 */
function performGradualCleanup() {
    echo "🔄 段階的クリーンアップ開始\n";
    echo "段階1: 7日より古いデータを削除\n";
    echo "段階2: 5日より古いデータを削除\n";
    echo "段階3: 3日より古いデータを削除\n\n";
    
    $startTime = microtime(true);
    
    try {
        $searchLogService = new SearchLogService();
        $db = $searchLogService->getDatabase();
        
        $stages = [
            ['days' => 7, 'name' => '段階1: 7日より古いデータ'],
            ['days' => 5, 'name' => '段階2: 5日より古いデータ'],
            ['days' => 3, 'name' => '段階3: 3日より古いデータ']
        ];
        
        $totalDeleted = 0;
        
        foreach ($stages as $stage) {
            echo "実行中: {$stage['name']}\n";
            
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$stage['days']} days"));
            
            $deleteSql = "
                DELETE FROM global_search_history 
                WHERE searched_at < ?
                LIMIT 5000
            ";
            
            $stmt = $db->prepare($deleteSql);
            $stmt->execute([$cutoffDate]);
            $deleted = $stmt->rowCount();
            $totalDeleted += $deleted;
            
            echo "削除: " . number_format($deleted) . " 件\n";
            
            // 実行時間チェック
            if (microtime(true) - $startTime > 25) {
                echo "⏰ 実行時間制限に近づいたため、処理を中断\n";
                break;
            }
        }
        
        $executionTime = round(microtime(true) - $startTime, 2);
        
        echo "\n✅ 段階的クリーンアップ完了\n";
        echo "総削除数: " . number_format($totalDeleted) . " 件\n";
        echo "実行時間: {$executionTime} 秒\n";
        
        showQuickStats($db);
        
    } catch (Exception $e) {
        echo "❌ エラー: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * メイン処理
 */
function main() {
    global $argv;
    
    $action = $argv[1] ?? 'emergency';
    
    echo "=== 緊急クリーンアップツール ===\n";
    echo "高負荷環境（1日12.4MB増加）対応版\n\n";
    
    switch ($action) {
        case 'emergency':
            performEmergencyCleanup();
            break;
        case 'gradual':
            performGradualCleanup();
            break;
        case 'stats':
            try {
                $searchLogService = new SearchLogService();
                $db = $searchLogService->getDatabase();
                showQuickStats($db);
            } catch (Exception $e) {
                echo "❌ エラー: " . $e->getMessage() . "\n";
            }
            break;
        default:
            echo "使用方法:\n";
            echo "php scripts/emergency_cleanup.php [action]\n\n";
            echo "アクション:\n";
            echo "  emergency  - 緊急クリーンアップ（3日保持）\n";
            echo "  gradual    - 段階的クリーンアップ\n";
            echo "  stats      - 統計情報表示\n";
            break;
    }
}

// スクリプトが直接実行された場合のみメイン処理を実行
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    main();
}
