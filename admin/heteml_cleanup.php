<?php
/**
 * HETEML環境用検索履歴管理画面
 * 
 * HETEMLの制約に最適化された管理画面
 */

// エラー表示を有効にする（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// セキュリティチェック（セッションベースに変更）
session_start();

// セッションベースの認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// HETEML環境の制約を設定
ini_set('max_execution_time', 30);
ini_set('memory_limit', '128M');

// データベース接続
try {
    $host = 'mysql320.phy.heteml.lan';
    $db_name = '_shinkenchiku_02';
    $username = '_shinkenchiku_02';
    $password = 'ipgdfahuqbg3';
    
    $pdo = new PDO("mysql:host={$host};dbname={$db_name};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// 必要なファイルを読み込み
require_once __DIR__ . '/../src/Services/SearchLogService.php';
require_once __DIR__ . '/../scripts/heteml_cleanup_config.php';

$config = require __DIR__ . '/../scripts/heteml_cleanup_config.php';
$hetemlConfig = $config['heteml'];

$searchLogService = new SearchLogService($pdo);
$message = '';
$error = '';
$stats = null;

// アクション処理
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action) {
    try {
        switch ($action) {
            case 'stats':
                $stats = getHetemlStats($searchLogService, $config);
                break;
                
            case 'cleanup':
                $retentionDays = (int)($_POST['retention_days'] ?? 60);
                $archive = isset($_POST['archive']);
                
                $result = performHetemlCleanup($searchLogService, $retentionDays, $archive, $hetemlConfig);
                
                if ($result['error']) {
                    $error = 'クリーンアップエラー: ' . $result['error'];
                } else {
                    $message = sprintf(
                        'クリーンアップ完了: %d件削除, %d件アーカイブ, 実行時間: %.2f秒',
                        $result['deleted_count'],
                        $result['archived_count'],
                        $result['execution_time']
                    );
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'エラー: ' . $e->getMessage();
    }
}

// 統計情報が取得されていない場合は取得
if (!$stats && !$error) {
    $stats = getHetemlStats($searchLogService, $config);
}

/**
 * HETEML用統計情報取得
 */
function getHetemlStats($searchLogService, $config) {
    try {
        $db = $searchLogService->getDatabase();
        
        // 軽量な統計情報のみ取得
        $statsSql = "
            SELECT 
                COUNT(*) as total_records,
                COUNT(DISTINCT query) as unique_queries,
                MIN(searched_at) as oldest_record,
                MAX(searched_at) as newest_record,
                COUNT(CASE WHEN searched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as records_last_week,
                COUNT(CASE WHEN searched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as records_last_month
            FROM global_search_history
        ";
        
        $stmt = $db->query($statsSql);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // テーブルサイズ情報
        $sizeSql = "
            SELECT 
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                table_rows
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'global_search_history'
        ";
        
        $stmt = $db->query($sizeSql);
        $sizeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // HETEML用の推奨事項を生成
        $recommendations = generateHetemlRecommendations($stats, $sizeInfo, $config['alerts']);
        
        return [
            'stats' => $stats,
            'size_info' => $sizeInfo,
            'recommendations' => $recommendations,
            'heteml_limits' => $config['heteml']
        ];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * HETEML用推奨事項生成
 */
function generateHetemlRecommendations($stats, $sizeInfo, $alerts) {
    $recommendations = [];
    
    if (empty($stats)) {
        return $recommendations;
    }
    
    $totalRecords = $stats['total_records'] ?? 0;
    $sizeMB = $sizeInfo['size_mb'] ?? 0;
    
    // テーブルサイズチェック
    if ($sizeMB > $alerts['table_size_critical']) {
        $recommendations[] = [
            'type' => 'critical',
            'message' => "テーブルサイズが{$sizeMB}MBで制限に近づいています。緊急クリーンアップが必要です。",
            'action' => 'immediate_cleanup'
        ];
    } elseif ($sizeMB > $alerts['table_size_warning']) {
        $recommendations[] = [
            'type' => 'warning',
            'message' => "テーブルサイズが{$sizeMB}MBです。クリーンアップを検討してください。",
            'action' => 'schedule_cleanup'
        ];
    }
    
    // レコード数チェック
    if ($totalRecords > $alerts['record_count_critical']) {
        $recommendations[] = [
            'type' => 'critical',
            'message' => "レコード数が{$totalRecords}件で制限に近づいています。",
            'action' => 'immediate_cleanup'
        ];
    } elseif ($totalRecords > $alerts['record_count_warning']) {
        $recommendations[] = [
            'type' => 'warning',
            'message' => "レコード数が{$totalRecords}件です。",
            'action' => 'schedule_cleanup'
        ];
    }
    
    return $recommendations;
}

/**
 * HETEML用の軽量クリーンアップ
 */
function performHetemlCleanup($searchLogService, $retentionDays, $archive, $hetemlConfig) {
    $startTime = microtime(true);
    $deletedCount = 0;
    $archivedCount = 0;
    
    try {
        $db = $searchLogService->getDatabase();
        $db->beginTransaction();
        
        // バッチサイズで処理
        $batchSize = $hetemlConfig['batch_size'];
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        
        // アーカイブ処理（軽量化）
        if ($archive) {
            $archivedCount = performLightweightArchive($db, $cutoffDate, $batchSize, $hetemlConfig);
        }
        
        // 削除処理（バッチ処理）
        $deletedCount = performBatchDelete($db, $cutoffDate, $batchSize);
        
        $db->commit();
        
        $executionTime = round(microtime(true) - $startTime, 2);
        
        return [
            'deleted_count' => $deletedCount,
            'archived_count' => $archivedCount,
            'execution_time' => $executionTime,
            'error' => null
        ];
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        
        return [
            'deleted_count' => 0,
            'archived_count' => 0,
            'execution_time' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * 軽量アーカイブ処理
 */
function performLightweightArchive($db, $cutoffDate, $batchSize, $hetemlConfig) {
    // アーカイブテーブルを作成
    $createArchiveTableSql = "
        CREATE TABLE IF NOT EXISTS `global_search_history_archive` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `original_id` BIGINT NOT NULL,
            `query` VARCHAR(255) NOT NULL,
            `search_type` VARCHAR(20) NOT NULL,
            `search_count` INT NOT NULL,
            `last_searched` TIMESTAMP NOT NULL,
            `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_query_type` (`query`, `search_type`),
            INDEX `idx_archived_at` (`archived_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($createArchiveTableSql);
    
    // 人気検索ワードを集計してアーカイブ（軽量化）
    $archiveSql = "
        INSERT INTO global_search_history_archive 
        (original_id, query, search_type, search_count, last_searched)
        SELECT 
            MAX(id) as original_id,
            query,
            search_type,
            COUNT(*) as search_count,
            MAX(searched_at) as last_searched
        FROM global_search_history
        WHERE searched_at < ?
        GROUP BY query, search_type
        HAVING COUNT(*) >= ?
        LIMIT ?
    ";
    
    $stmt = $db->prepare($archiveSql);
    $stmt->execute([
        $cutoffDate,
        $hetemlConfig['archive_threshold'],
        $batchSize
    ]);
    
    return $stmt->rowCount();
}

/**
 * バッチ削除処理
 */
function performBatchDelete($db, $cutoffDate, $batchSize) {
    $totalDeleted = 0;
    $maxIterations = 5; // HETEML用に制限
    
    for ($i = 0; $i < $maxIterations; $i++) {
        // 実行時間チェック
        if (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] > 25) {
            break;
        }
        
        $deleteSql = "
            DELETE FROM global_search_history 
            WHERE searched_at < ?
            LIMIT ?
        ";
        
        $stmt = $db->prepare($deleteSql);
        $stmt->execute([$cutoffDate, $batchSize]);
        $deleted = $stmt->rowCount();
        $totalDeleted += $deleted;
        
        if ($deleted < $batchSize) {
            break;
        }
    }
    
    return $totalDeleted;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HETEML環境 検索履歴管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .heteml-card {
            border-left: 4px solid #28a745;
        }
        .warning-card {
            border-left: 4px solid #ffc107;
        }
        .critical-card {
            border-left: 4px solid #dc3545;
        }
        .recommendation {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .recommendation.critical {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .recommendation.warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
        }
        .heteml-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">🏢 HETEML環境 検索履歴管理</h1>
        
        <!-- HETEML制約情報 -->
        <div class="heteml-info">
            <h6><strong>HETEML環境の制約</strong></h6>
            <ul class="mb-0">
                <li>実行時間制限: 30秒</li>
                <li>メモリ制限: 128MB</li>
                <li>バッチサイズ: 1,000件</li>
                <li>推奨保持期間: 60日</li>
            </ul>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- 統計情報 -->
        <?php if ($stats && !isset($stats['error'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card heteml-card">
                        <div class="card-header">
                            <h5 class="mb-0">📊 HETEML環境 データベース統計情報</h5>
                        </div>
                        <div class="card-body">
                            <!-- テーブルサイズ -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h4 class="text-primary"><?= htmlspecialchars($stats['size_info']['size_mb'] ?? 0) ?> MB</h4>
                                            <small>テーブルサイズ</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h4 class="text-info"><?= number_format($stats['stats']['total_records'] ?? 0) ?></h4>
                                            <small>総レコード数</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 詳細統計 -->
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h5 class="text-success"><?= number_format($stats['stats']['unique_queries'] ?? 0) ?></h5>
                                        <small>ユニーク検索語</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h5 class="text-warning"><?= number_format($stats['stats']['records_last_week'] ?? 0) ?></h5>
                                        <small>過去1週間</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h5 class="text-info"><?= number_format($stats['stats']['records_last_month'] ?? 0) ?></h5>
                                        <small>過去1ヶ月</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h5 class="text-secondary"><?= htmlspecialchars($stats['stats']['oldest_record'] ?? 'N/A') ?></h5>
                                        <small>最古のレコード</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 推奨事項 -->
            <?php if (!empty($stats['recommendations'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card <?= in_array('critical', array_column($stats['recommendations'], 'type')) ? 'critical-card' : 'warning-card' ?>">
                            <div class="card-header">
                                <h5 class="mb-0">⚠️ HETEML環境 推奨事項</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($stats['recommendations'] as $recommendation): ?>
                                    <div class="recommendation <?= $recommendation['type'] ?>">
                                        <strong><?= $recommendation['type'] === 'critical' ? '🚨' : '⚠️' ?></strong>
                                        <?= htmlspecialchars($recommendation['message']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- クリーンアップ操作 -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">🧹 HETEML用データクリーンアップ</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="key" value="<?= htmlspecialchars($adminKey) ?>">
                            <input type="hidden" name="action" value="cleanup">
                            
                            <div class="mb-3">
                                <label for="retention_days" class="form-label">データ保持期間（日数）</label>
                                <select class="form-select" name="retention_days" id="retention_days">
                                    <option value="30">30日（推奨: 開発環境）</option>
                                    <option value="60" selected>60日（推奨: HETEML環境）</option>
                                    <option value="90">90日（本番環境）</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="archive" id="archive" checked>
                                    <label class="form-check-label" for="archive">
                                        重要なデータをアーカイブしてから削除
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning" 
                                    onclick="return confirm('HETEML環境でクリーンアップを実行しますか？この操作は取り消せません。')">
                                クリーンアップ実行
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📋 HETEML環境 運用情報</h5>
                    </div>
                    <div class="card-body">
                        <h6>推奨されるcron設定:</h6>
                        <code class="small">
                            0 2 * * * /usr/local/bin/php /home/your-account/public_html/scripts/heteml_cleanup_search_history.php 60 --archive
                        </code>
                        
                        <h6 class="mt-3">外部サービス連携:</h6>
                        <p class="small text-muted">
                            HETEMLのcron機能が制限的すぎる場合は、UptimeRobotやGitHub Actionsを使用して定期実行できます。
                        </p>
                        
                        <h6>手動実行URL:</h6>
                        <code class="small">
                            <?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/admin/heteml_cleanup.php?key=<?= htmlspecialchars($adminKey) ?>&action=cleanup
                        </code>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
