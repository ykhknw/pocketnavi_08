<?php
/**
 * シンプルなアクセス解析ダッシュボード
 */

// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

// セキュリティチェック
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

// パラメータ取得
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
$days = max(1, min(365, $days)); // 1-365日の範囲に制限

// 基本的な統計データを取得
$stats = [];

try {
    // 総検索数
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM global_search_history WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    $stats['total_searches'] = $stmt->fetchColumn();
    
    // ユニーククエリ数
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT query) as unique_queries FROM global_search_history WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    $stats['unique_queries'] = $stmt->fetchColumn();
    
    // 検索タイプ別統計
    $stmt = $pdo->prepare("
        SELECT search_type, COUNT(*) as count 
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
        GROUP BY search_type 
        ORDER BY count DESC
    ");
    $stmt->execute([$days]);
    $stats['search_types'] = $stmt->fetchAll();
    
    // 人気検索ワード（上位10件）
    $stmt = $pdo->prepare("
        SELECT query, search_type, COUNT(*) as count 
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
        GROUP BY query, search_type 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute([$days]);
    $stats['popular_searches'] = $stmt->fetchAll();
    
    // 日別統計（過去7日）
    $stmt = $pdo->prepare("
        SELECT DATE(searched_at) as date, COUNT(*) as count 
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
        GROUP BY DATE(searched_at) 
        ORDER BY date DESC
    ");
    $stmt->execute([$days]);
    $stats['daily_stats'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "データ取得エラー: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>シンプルアクセス解析 - PocketNavi管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">PocketNavi 管理画面</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">ログアウト</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1>シンプルアクセス解析</h1>
                <p class="text-muted">過去<?php echo $days; ?>日間の検索データ分析</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- 期間選択 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">期間選択</h5>
                        <div class="btn-group" role="group">
                            <a href="?days=1" class="btn btn-outline-primary <?php echo $days == 1 ? 'active' : ''; ?>">1日</a>
                            <a href="?days=7" class="btn btn-outline-primary <?php echo $days == 7 ? 'active' : ''; ?>">7日</a>
                            <a href="?days=30" class="btn btn-outline-primary <?php echo $days == 30 ? 'active' : ''; ?>">30日</a>
                            <a href="?days=90" class="btn btn-outline-primary <?php echo $days == 90 ? 'active' : ''; ?>">90日</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 基本統計 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">総検索数</h5>
                        <h2 class="text-primary"><?php echo number_format($stats['total_searches']); ?></h2>
                        <p class="text-muted">過去<?php echo $days; ?>日間</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">ユニーク検索語</h5>
                        <h2 class="text-success"><?php echo number_format($stats['unique_queries']); ?></h2>
                        <p class="text-muted">異なる検索語の数</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 検索タイプ別統計 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">検索タイプ別統計</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>検索タイプ</th>
                                        <th>検索数</th>
                                        <th>割合</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['search_types'] as $type): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $typeNames = [
                                                'text' => 'テキスト検索',
                                                'architect' => '建築家検索',
                                                'building' => '建築物検索',
                                                'prefecture' => '都道府県検索'
                                            ];
                                            echo $typeNames[$type['search_type']] ?? $type['search_type'];
                                            ?>
                                        </td>
                                        <td><?php echo number_format($type['count']); ?></td>
                                        <td>
                                            <?php 
                                            $percentage = $stats['total_searches'] > 0 ? ($type['count'] / $stats['total_searches']) * 100 : 0;
                                            echo number_format($percentage, 1); ?>%
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 人気検索ワード -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">人気検索ワード TOP10</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>順位</th>
                                        <th>検索語</th>
                                        <th>タイプ</th>
                                        <th>検索数</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['popular_searches'] as $index => $search): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($search['query']); ?></td>
                                        <td>
                                            <?php
                                            $typeNames = [
                                                'text' => 'テキスト',
                                                'architect' => '建築家',
                                                'building' => '建築物',
                                                'prefecture' => '都道府県'
                                            ];
                                            echo $typeNames[$search['search_type']] ?? $search['search_type'];
                                            ?>
                                        </td>
                                        <td><?php echo number_format($search['count']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 日別統計グラフ -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">日別検索数推移</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ナビゲーション -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">管理画面メニュー</h5>
                        <div class="btn-group" role="group">
                            <a href="index.php" class="btn btn-outline-primary">管理画面トップ</a>
                            <a href="analytics_dashboard.php" class="btn btn-outline-secondary">詳細解析（旧版）</a>
                            <a href="search_history_management.php" class="btn btn-outline-info">検索履歴管理</a>
                            <a href="heteml_cleanup.php" class="btn btn-outline-warning">HETEMLクリーンアップ</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 日別統計グラフ
        const dailyData = <?php echo json_encode($stats['daily_stats']); ?>;
        const labels = dailyData.map(item => item.date).reverse();
        const data = dailyData.map(item => item.count).reverse();

        const ctx = document.getElementById('dailyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '検索数',
                    data: data,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
