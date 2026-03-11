<?php
/**
 * 検索履歴管理画面
 * 
 * 注意: 本番環境では適切な認証機能を追加してください
 */

// エラー表示を有効にする（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// セキュリティチェック（セッションベース）
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

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

$searchLogService = new SearchLogService($pdo);
$message = '';
$error = '';

// アクション処理
if ($_POST['action'] ?? false) {
    try {
        switch ($_POST['action']) {
            case 'cleanup':
                $retentionDays = (int)($_POST['retention_days'] ?? 90);
                $archive = isset($_POST['archive']);
                
                $result = $searchLogService->cleanupOldSearchHistory($retentionDays, $archive);
                
                if ($result['error']) {
                    $error = 'クリーンアップエラー: ' . $result['error'];
                } else {
                    $message = sprintf(
                        'クリーンアップ完了: %d件削除, %d件アーカイブ',
                        $result['deleted_count'],
                        $result['archived_count']
                    );
                }
                break;
                
            case 'stats':
                // 統計情報は自動的に取得される
                break;
        }
    } catch (Exception $e) {
        $error = 'エラー: ' . $e->getMessage();
    }
}

// 統計情報を取得
$stats = $searchLogService->getDatabaseStats();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>検索履歴管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stats-card {
            border-left: 4px solid #007bff;
        }
        .warning-card {
            border-left: 4px solid #ffc107;
        }
        .danger-card {
            border-left: 4px solid #dc3545;
        }
        .recommendation {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .recommendation.warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
        }
        .recommendation.info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">🔍 検索履歴管理</h1>
        
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
        <div class="row mb-4">
            <div class="col-12">
                <div class="card stats-card">
                    <div class="card-header">
                        <h5 class="mb-0">📊 データベース統計情報</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($stats['error'])): ?>
                            <div class="alert alert-danger">
                                エラー: <?= htmlspecialchars($stats['error']) ?>
                            </div>
                        <?php else: ?>
                            <!-- テーブルサイズ -->
                            <h6>テーブルサイズ</h6>
                            <div class="row mb-3">
                                <?php foreach ($stats['table_stats'] as $table): ?>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title"><?= htmlspecialchars($table['table_name']) ?></h6>
                                                <p class="card-text">
                                                    <strong><?= htmlspecialchars($table['Size (MB)']) ?> MB</strong><br>
                                                    <?= number_format($table['table_rows']) ?> レコード
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- 検索履歴統計 -->
                            <?php if (!empty($stats['history_stats'])): ?>
                                <h6>検索履歴統計</h6>
                                <div class="row">
                                    <?php $history = $stats['history_stats']; ?>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-primary"><?= number_format($history['total_records']) ?></h4>
                                            <small>総レコード数</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-info"><?= number_format($history['unique_queries']) ?></h4>
                                            <small>ユニーク検索語</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-success"><?= number_format($history['records_last_week']) ?></h4>
                                            <small>過去1週間</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-warning"><?= number_format($history['records_last_month']) ?></h4>
                                            <small>過去1ヶ月</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        最古のレコード: <?= htmlspecialchars($history['oldest_record']) ?><br>
                                        最新のレコード: <?= htmlspecialchars($history['newest_record']) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 推奨事項 -->
        <?php if (!empty($stats['recommendations'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card warning-card">
                        <div class="card-header">
                            <h5 class="mb-0">⚠️ 推奨事項</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($stats['recommendations'] as $recommendation): ?>
                                <div class="recommendation <?= $recommendation['type'] ?>">
                                    <strong><?= $recommendation['type'] === 'warning' ? '⚠️' : 'ℹ️' ?></strong>
                                    <?= htmlspecialchars($recommendation['message']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- クリーンアップ操作 -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">🧹 データクリーンアップ</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="cleanup">
                            
                            <div class="mb-3">
                                <label for="retention_days" class="form-label">データ保持期間（日数）</label>
                                <select class="form-select" name="retention_days" id="retention_days">
                                    <option value="30">30日</option>
                                    <option value="60">60日</option>
                                    <option value="90" selected>90日</option>
                                    <option value="180">180日</option>
                                    <option value="365">365日</option>
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
                                    onclick="return confirm('本当にクリーンアップを実行しますか？この操作は取り消せません。')">
                                クリーンアップ実行
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📋 操作履歴</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">クリーンアップ操作の履歴は、サーバーのログファイルで確認できます。</p>
                        
                        <h6>推奨される定期実行設定:</h6>
                        <ul class="small">
                            <li><strong>Linux/Mac:</strong> cronジョブで毎週日曜日午前2時</li>
                            <li><strong>Windows:</strong> タスクスケジューラーで毎週日曜日午前2時</li>
                        </ul>
                        
                        <h6>手動実行コマンド:</h6>
                        <code class="small">
                            php scripts/cleanup_search_history.php 90 --archive
                        </code>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
