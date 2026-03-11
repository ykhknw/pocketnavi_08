<?php
/**
 * ユーザー分析ダッシュボード
 * 1. 日別ユニークユーザー数推移
 * 2. ユーザーセッション分析
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
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$days = max(1, min(365, $days)); // 1-365日の範囲に制限

$stats = [];

try {
    // 1. 日別ユニークユーザー数推移
    $stmt = $pdo->prepare("
        SELECT 
            DATE(searched_at) as date,
            COUNT(DISTINCT COALESCE(user_id, user_session_id)) as unique_users,
            COUNT(DISTINCT user_session_id) as unique_sessions,
            COUNT(*) as total_searches
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
        GROUP BY DATE(searched_at) 
        ORDER BY date DESC
    ");
    $stmt->execute([$days]);
    $stats['daily_users'] = $stmt->fetchAll();
    
    // 2. ユーザーセッション分析
    // セッション別の統計
    $stmt = $pdo->prepare("
        SELECT 
            user_session_id,
            COUNT(*) as search_count,
            MIN(searched_at) as session_start,
            MAX(searched_at) as session_end,
            TIMESTAMPDIFF(MINUTE, MIN(searched_at), MAX(searched_at)) as session_duration_minutes,
            COUNT(DISTINCT search_type) as search_types_count,
            GROUP_CONCAT(DISTINCT search_type ORDER BY search_type) as search_types
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND user_session_id IS NOT NULL
        GROUP BY user_session_id
        ORDER BY search_count DESC
    ");
    $stmt->execute([$days]);
    $sessionData = $stmt->fetchAll();
    
    // セッション分析の統計
    $stats['session_stats'] = [
        'total_sessions' => count($sessionData),
        'avg_searches_per_session' => 0,
        'avg_session_duration' => 0,
        'sessions_by_search_count' => [],
        'sessions_by_duration' => []
    ];
    
    if (!empty($sessionData)) {
        $totalSearches = array_sum(array_column($sessionData, 'search_count'));
        $totalDuration = array_sum(array_column($sessionData, 'session_duration_minutes'));
        
        $stats['session_stats']['avg_searches_per_session'] = round($totalSearches / count($sessionData), 2);
        $stats['session_stats']['avg_session_duration'] = round($totalDuration / count($sessionData), 2);
        
        // 検索回数別セッション分布
        $searchCountDistribution = [];
        foreach ($sessionData as $session) {
            $count = $session['search_count'];
            if ($count == 1) {
                $searchCountDistribution['1回'] = ($searchCountDistribution['1回'] ?? 0) + 1;
            } elseif ($count <= 3) {
                $searchCountDistribution['2-3回'] = ($searchCountDistribution['2-3回'] ?? 0) + 1;
            } elseif ($count <= 5) {
                $searchCountDistribution['4-5回'] = ($searchCountDistribution['4-5回'] ?? 0) + 1;
            } elseif ($count <= 10) {
                $searchCountDistribution['6-10回'] = ($searchCountDistribution['6-10回'] ?? 0) + 1;
            } else {
                $searchCountDistribution['11回以上'] = ($searchCountDistribution['11回以上'] ?? 0) + 1;
            }
        }
        $stats['session_stats']['sessions_by_search_count'] = $searchCountDistribution;
        
        // 滞在時間別セッション分布
        $durationDistribution = [];
        foreach ($sessionData as $session) {
            $duration = $session['session_duration_minutes'];
            if ($duration == 0) {
                $durationDistribution['0分（即座）'] = ($durationDistribution['0分（即座）'] ?? 0) + 1;
            } elseif ($duration <= 5) {
                $durationDistribution['1-5分'] = ($durationDistribution['1-5分'] ?? 0) + 1;
            } elseif ($duration <= 15) {
                $durationDistribution['6-15分'] = ($durationDistribution['6-15分'] ?? 0) + 1;
            } elseif ($duration <= 30) {
                $durationDistribution['16-30分'] = ($durationDistribution['16-30分'] ?? 0) + 1;
            } else {
                $durationDistribution['30分以上'] = ($durationDistribution['30分以上'] ?? 0) + 1;
            }
        }
        $stats['session_stats']['sessions_by_duration'] = $durationDistribution;
    }
    
    // 3. 週間・月間の成長率計算
    $currentWeekUsers = 0;
    $previousWeekUsers = 0;
    $currentMonthUsers = 0;
    $previousMonthUsers = 0;
    
    foreach ($stats['daily_users'] as $day) {
        $date = new DateTime($day['date']);
        $now = new DateTime();
        $daysDiff = $now->diff($date)->days;
        
        if ($daysDiff <= 7) {
            $currentWeekUsers += $day['unique_users'];
        } elseif ($daysDiff <= 14) {
            $previousWeekUsers += $day['unique_users'];
        }
        
        if ($daysDiff <= 30) {
            $currentMonthUsers += $day['unique_users'];
        } elseif ($daysDiff <= 60) {
            $previousMonthUsers += $day['unique_users'];
        }
    }
    
    $stats['growth_rates'] = [
        'weekly' => $previousWeekUsers > 0 ? round((($currentWeekUsers - $previousWeekUsers) / $previousWeekUsers) * 100, 1) : 0,
        'monthly' => $previousMonthUsers > 0 ? round((($currentMonthUsers - $previousMonthUsers) / $previousMonthUsers) * 100, 1) : 0
    ];
    
    // 4. 平日と休日の比較
    $weekdayUsers = 0;
    $weekendUsers = 0;
    $weekdaySearches = 0;
    $weekendSearches = 0;
    
    foreach ($stats['daily_users'] as $day) {
        $date = new DateTime($day['date']);
        $dayOfWeek = $date->format('N'); // 1=月曜日, 7=日曜日
        
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            $weekdayUsers += $day['unique_users'];
            $weekdaySearches += $day['total_searches'];
        } else {
            $weekendUsers += $day['unique_users'];
            $weekendSearches += $day['total_searches'];
        }
    }
    
    $stats['weekday_weekend'] = [
        'weekday_users' => $weekdayUsers,
        'weekend_users' => $weekendUsers,
        'weekday_searches' => $weekdaySearches,
        'weekend_searches' => $weekendSearches
    ];
    
} catch (PDOException $e) {
    $error = "データ取得エラー: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー分析 - PocketNavi管理画面</title>
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
                <h1>ユーザー分析ダッシュボード</h1>
                <p class="text-muted">日別ユニークユーザー数推移とユーザーセッション分析</p>
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
                            <a href="?days=7" class="btn btn-outline-primary <?php echo $days == 7 ? 'active' : ''; ?>">7日</a>
                            <a href="?days=30" class="btn btn-outline-primary <?php echo $days == 30 ? 'active' : ''; ?>">30日</a>
                            <a href="?days=90" class="btn btn-outline-primary <?php echo $days == 90 ? 'active' : ''; ?>">90日</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 成長率と基本統計 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">週間成長率</h5>
                        <h3 class="<?php echo $stats['growth_rates']['weekly'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $stats['growth_rates']['weekly']; ?>%
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">月間成長率</h5>
                        <h3 class="<?php echo $stats['growth_rates']['monthly'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $stats['growth_rates']['monthly']; ?>%
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">総セッション数</h5>
                        <h3 class="text-primary"><?php echo number_format($stats['session_stats']['total_sessions']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">平均検索/セッション</h5>
                        <h3 class="text-info"><?php echo $stats['session_stats']['avg_searches_per_session']; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- 日別ユニークユーザー数推移 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">日別ユニークユーザー数推移</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyUsersChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 平日と休日の比較 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">平日 vs 休日（ユーザー数）</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="weekdayWeekendUsersChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">平日 vs 休日（検索数）</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="weekdayWeekendSearchesChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- セッション分析 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">検索回数別セッション分布</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="searchCountChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">滞在時間別セッション分布</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="durationChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- セッション統計テーブル -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">セッション統計サマリー</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>指標</th>
                                        <th>値</th>
                                        <th>説明</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>総セッション数</td>
                                        <td><?php echo number_format($stats['session_stats']['total_sessions']); ?></td>
                                        <td>過去<?php echo $days; ?>日間の総セッション数</td>
                                    </tr>
                                    <tr>
                                        <td>平均検索数/セッション</td>
                                        <td><?php echo $stats['session_stats']['avg_searches_per_session']; ?></td>
                                        <td>1セッションあたりの平均検索回数</td>
                                    </tr>
                                    <tr>
                                        <td>平均滞在時間</td>
                                        <td><?php echo $stats['session_stats']['avg_session_duration']; ?>分</td>
                                        <td>セッション開始から終了までの平均時間</td>
                                    </tr>
                                    <tr>
                                        <td>平日ユーザー数</td>
                                        <td><?php echo number_format($stats['weekday_weekend']['weekday_users']); ?></td>
                                        <td>月曜日〜金曜日のユニークユーザー数</td>
                                    </tr>
                                    <tr>
                                        <td>休日ユーザー数</td>
                                        <td><?php echo number_format($stats['weekday_weekend']['weekend_users']); ?></td>
                                        <td>土曜日〜日曜日のユニークユーザー数</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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
                            <a href="simple_analytics.php" class="btn btn-outline-success">シンプル解析</a>
                            <a href="analytics_dashboard.php" class="btn btn-outline-secondary">詳細解析</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 日別ユニークユーザー数推移グラフ
        const dailyData = <?php echo json_encode($stats['daily_users']); ?>;
        const labels = dailyData.map(item => item.date).reverse();
        const uniqueUsers = dailyData.map(item => item.unique_users).reverse();
        const uniqueSessions = dailyData.map(item => item.unique_sessions).reverse();
        const totalSearches = dailyData.map(item => item.total_searches).reverse();

        const dailyUsersCtx = document.getElementById('dailyUsersChart').getContext('2d');
        new Chart(dailyUsersCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ユニークユーザー数',
                    data: uniqueUsers,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }, {
                    label: 'ユニークセッション数',
                    data: uniqueSessions,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
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

        // 平日と休日の比較グラフ（ユーザー数）
        const weekdayWeekendUsersCtx = document.getElementById('weekdayWeekendUsersChart').getContext('2d');
        new Chart(weekdayWeekendUsersCtx, {
            type: 'doughnut',
            data: {
                labels: ['平日', '休日'],
                datasets: [{
                    data: [<?php echo $stats['weekday_weekend']['weekday_users']; ?>, <?php echo $stats['weekday_weekend']['weekend_users']; ?>],
                    backgroundColor: ['rgb(54, 162, 235)', 'rgb(255, 205, 86)']
                }]
            },
            options: {
                responsive: true
            }
        });

        // 平日と休日の比較グラフ（検索数）
        const weekdayWeekendSearchesCtx = document.getElementById('weekdayWeekendSearchesChart').getContext('2d');
        new Chart(weekdayWeekendSearchesCtx, {
            type: 'doughnut',
            data: {
                labels: ['平日', '休日'],
                datasets: [{
                    data: [<?php echo $stats['weekday_weekend']['weekday_searches']; ?>, <?php echo $stats['weekday_weekend']['weekend_searches']; ?>],
                    backgroundColor: ['rgb(153, 102, 255)', 'rgb(255, 159, 64)']
                }]
            },
            options: {
                responsive: true
            }
        });

        // 検索回数別セッション分布
        const searchCountData = <?php echo json_encode($stats['session_stats']['sessions_by_search_count']); ?>;
        const searchCountCtx = document.getElementById('searchCountChart').getContext('2d');
        new Chart(searchCountCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(searchCountData),
                datasets: [{
                    label: 'セッション数',
                    data: Object.values(searchCountData),
                    backgroundColor: 'rgba(75, 192, 192, 0.8)'
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

        // 滞在時間別セッション分布
        const durationData = <?php echo json_encode($stats['session_stats']['sessions_by_duration']); ?>;
        const durationCtx = document.getElementById('durationChart').getContext('2d');
        new Chart(durationCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(durationData),
                datasets: [{
                    label: 'セッション数',
                    data: Object.values(durationData),
                    backgroundColor: 'rgba(255, 99, 132, 0.8)'
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
