<?php
/**
 * 検索行動分析ダッシュボード
 * 5. 検索深度分析
 * 6. 検索遷移パターン分析
 * 7. 時間帯別行動分析
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
    // 5. 検索深度分析
    // セッション別の検索深度分析
    $stmt = $pdo->prepare("
        SELECT 
            user_session_id,
            COUNT(*) as search_count,
            COUNT(DISTINCT search_type) as search_types_count,
            COUNT(DISTINCT query) as unique_queries,
            MIN(searched_at) as session_start,
            MAX(searched_at) as session_end,
            TIMESTAMPDIFF(MINUTE, MIN(searched_at), MAX(searched_at)) as session_duration_minutes
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND user_session_id IS NOT NULL
        GROUP BY user_session_id
        ORDER BY search_count DESC
    ");
    $stmt->execute([$days]);
    $sessionData = $stmt->fetchAll();
    
    // 検索深度の分布
    $searchDepthDistribution = [
        '1回' => 0,
        '2-3回' => 0,
        '4-5回' => 0,
        '6-10回' => 0,
        '11-20回' => 0,
        '21回以上' => 0
    ];
    
    $depthStats = [
        'total_sessions' => count($sessionData),
        'avg_searches_per_session' => 0,
        'avg_unique_queries_per_session' => 0,
        'avg_session_duration' => 0,
        'high_depth_users' => 0,  // 6回以上検索するユーザー
        'deep_exploration_users' => 0  // 複数検索タイプを使用するユーザー
    ];
    
    if (!empty($sessionData)) {
        $totalSearches = array_sum(array_column($sessionData, 'search_count'));
        $totalUniqueQueries = array_sum(array_column($sessionData, 'unique_queries'));
        $totalDuration = array_sum(array_column($sessionData, 'session_duration_minutes'));
        
        $depthStats['avg_searches_per_session'] = round($totalSearches / count($sessionData), 2);
        $depthStats['avg_unique_queries_per_session'] = round($totalUniqueQueries / count($sessionData), 2);
        $depthStats['avg_session_duration'] = round($totalDuration / count($sessionData), 2);
        
        foreach ($sessionData as $session) {
            $count = $session['search_count'];
            $typesCount = $session['search_types_count'];
            
            // 検索深度分布
            if ($count == 1) {
                $searchDepthDistribution['1回']++;
            } elseif ($count <= 3) {
                $searchDepthDistribution['2-3回']++;
            } elseif ($count <= 5) {
                $searchDepthDistribution['4-5回']++;
            } elseif ($count <= 10) {
                $searchDepthDistribution['6-10回']++;
            } elseif ($count <= 20) {
                $searchDepthDistribution['11-20回']++;
            } else {
                $searchDepthDistribution['21回以上']++;
            }
            
            // 高深度ユーザー
            if ($count >= 6) {
                $depthStats['high_depth_users']++;
            }
            
            // 深い探索ユーザー
            if ($typesCount >= 2) {
                $depthStats['deep_exploration_users']++;
            }
        }
    }
    
    $stats['search_depth'] = $searchDepthDistribution;
    $stats['depth_stats'] = $depthStats;
    
    // 6. 検索遷移パターン分析
    // セッション内の検索遷移パターンを分析
    $stmt = $pdo->prepare("
        SELECT 
            user_session_id,
            search_type,
            searched_at,
            ROW_NUMBER() OVER (PARTITION BY user_session_id ORDER BY searched_at) as search_order
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND user_session_id IS NOT NULL
        ORDER BY user_session_id, searched_at
    ");
    $stmt->execute([$days]);
    $transitionData = $stmt->fetchAll();
    
    // 遷移パターンの分析
    $transitionPatterns = [];
    $currentSession = null;
    $sessionTransitions = [];
    
    foreach ($transitionData as $row) {
        if ($currentSession !== $row['user_session_id']) {
            // 新しいセッション
            if ($currentSession !== null && count($sessionTransitions) > 1) {
                // 前のセッションの遷移パターンを記録
                $pattern = implode(' → ', $sessionTransitions);
                $transitionPatterns[$pattern] = ($transitionPatterns[$pattern] ?? 0) + 1;
            }
            $currentSession = $row['user_session_id'];
            $sessionTransitions = [];
        }
        
        $sessionTransitions[] = $row['search_type'];
    }
    
    // 最後のセッションの処理
    if ($currentSession !== null && count($sessionTransitions) > 1) {
        $pattern = implode(' → ', $sessionTransitions);
        $transitionPatterns[$pattern] = ($transitionPatterns[$pattern] ?? 0) + 1;
    }
    
    // 遷移パターンを頻度順にソート
    arsort($transitionPatterns);
    $stats['transition_patterns'] = array_slice($transitionPatterns, 0, 20, true);
    
    // 検索タイプ別の遷移分析
    $typeTransitions = [
        'architect' => ['to_architect' => 0, 'to_building' => 0, 'to_prefecture' => 0, 'to_text' => 0],
        'building' => ['to_architect' => 0, 'to_building' => 0, 'to_prefecture' => 0, 'to_text' => 0],
        'prefecture' => ['to_architect' => 0, 'to_building' => 0, 'to_prefecture' => 0, 'to_text' => 0],
        'text' => ['to_architect' => 0, 'to_building' => 0, 'to_prefecture' => 0, 'to_text' => 0]
    ];
    
    $currentSession = null;
    $previousType = null;
    
    foreach ($transitionData as $row) {
        if ($currentSession !== $row['user_session_id']) {
            $currentSession = $row['user_session_id'];
            $previousType = null;
        }
        
        if ($previousType !== null && $previousType !== $row['search_type']) {
            $typeTransitions[$previousType]['to_' . $row['search_type']]++;
        }
        
        $previousType = $row['search_type'];
    }
    
    $stats['type_transitions'] = $typeTransitions;
    
    // 7. 時間帯別行動分析
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(searched_at) as hour,
            COUNT(*) as total_searches,
            COUNT(DISTINCT COALESCE(user_id, user_session_id)) as unique_users,
            COUNT(DISTINCT user_session_id) as unique_sessions,
            COUNT(CASE WHEN search_type = 'architect' THEN 1 END) as architect_searches,
            COUNT(CASE WHEN search_type = 'building' THEN 1 END) as building_searches,
            COUNT(CASE WHEN search_type = 'prefecture' THEN 1 END) as prefecture_searches,
            COUNT(CASE WHEN search_type = 'text' THEN 1 END) as text_searches
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY HOUR(searched_at)
        ORDER BY hour
    ");
    $stmt->execute([$days]);
    $hourlyData = $stmt->fetchAll();
    
    // 時間帯別の統計
    $hourlyStats = [
        'peak_hour' => 0,
        'peak_searches' => 0,
        'quiet_hour' => 0,
        'quiet_searches' => 999999,
        'business_hours_searches' => 0,  // 9-17時
        'evening_searches' => 0,         // 18-22時
        'night_searches' => 0,           // 23-5時
        'morning_searches' => 0          // 6-8時
    ];
    
    $hourlyChartData = [];
    for ($i = 0; $i < 24; $i++) {
        $hourlyChartData[$i] = [
            'hour' => $i,
            'searches' => 0,
            'users' => 0,
            'sessions' => 0,
            'architect' => 0,
            'building' => 0,
            'prefecture' => 0,
            'text' => 0
        ];
    }
    
    foreach ($hourlyData as $hour) {
        $hourNum = (int)$hour['hour'];
        $hourlyChartData[$hourNum] = [
            'hour' => $hourNum,
            'searches' => $hour['total_searches'],
            'users' => $hour['unique_users'],
            'sessions' => $hour['unique_sessions'],
            'architect' => $hour['architect_searches'],
            'building' => $hour['building_searches'],
            'prefecture' => $hour['prefecture_searches'],
            'text' => $hour['text_searches']
        ];
        
        // ピーク時間の特定
        if ($hour['total_searches'] > $hourlyStats['peak_searches']) {
            $hourlyStats['peak_hour'] = $hourNum;
            $hourlyStats['peak_searches'] = $hour['total_searches'];
        }
        
        // 静かな時間の特定
        if ($hour['total_searches'] < $hourlyStats['quiet_searches']) {
            $hourlyStats['quiet_hour'] = $hourNum;
            $hourlyStats['quiet_searches'] = $hour['total_searches'];
        }
        
        // 時間帯別集計
        if ($hourNum >= 9 && $hourNum <= 17) {
            $hourlyStats['business_hours_searches'] += $hour['total_searches'];
        } elseif ($hourNum >= 18 && $hourNum <= 22) {
            $hourlyStats['evening_searches'] += $hour['total_searches'];
        } elseif ($hourNum >= 23 || $hourNum <= 5) {
            $hourlyStats['night_searches'] += $hour['total_searches'];
        } elseif ($hourNum >= 6 && $hourNum <= 8) {
            $hourlyStats['morning_searches'] += $hour['total_searches'];
        }
    }
    
    $stats['hourly_data'] = $hourlyChartData;
    $stats['hourly_stats'] = $hourlyStats;
    
    // 平日と休日の時間帯別比較
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(searched_at) as hour,
            DAYOFWEEK(searched_at) as day_of_week,
            COUNT(*) as searches
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY HOUR(searched_at), DAYOFWEEK(searched_at)
        ORDER BY hour, day_of_week
    ");
    $stmt->execute([$days]);
    $weekdayHourlyData = $stmt->fetchAll();
    
    $weekdayHourlyChart = [];
    $weekendHourlyChart = [];
    
    for ($i = 0; $i < 24; $i++) {
        $weekdayHourlyChart[$i] = 0;
        $weekendHourlyChart[$i] = 0;
    }
    
    foreach ($weekdayHourlyData as $data) {
        $hour = (int)$data['hour'];
        $dayOfWeek = (int)$data['day_of_week']; // 1=日曜日, 7=土曜日
        
        if ($dayOfWeek >= 2 && $dayOfWeek <= 6) { // 月曜日〜金曜日
            $weekdayHourlyChart[$hour] += $data['searches'];
        } else { // 土曜日・日曜日
            $weekendHourlyChart[$hour] += $data['searches'];
        }
    }
    
    $stats['weekday_hourly'] = $weekdayHourlyChart;
    $stats['weekend_hourly'] = $weekendHourlyChart;
    
} catch (PDOException $e) {
    $error = "データ取得エラー: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>検索行動分析 - PocketNavi管理画面</title>
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
                <h1>検索行動分析ダッシュボード</h1>
                <p class="text-muted">検索深度分析、検索遷移パターン分析、時間帯別行動分析</p>
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

        <!-- 検索深度サマリー -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">平均検索数/セッション</h5>
                        <h3 class="text-primary"><?php echo $stats['depth_stats']['avg_searches_per_session']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">高深度ユーザー</h5>
                        <h3 class="text-success"><?php echo number_format($stats['depth_stats']['high_depth_users']); ?></h3>
                        <small>6回以上検索</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">深い探索ユーザー</h5>
                        <h3 class="text-info"><?php echo number_format($stats['depth_stats']['deep_exploration_users']); ?></h3>
                        <small>複数検索タイプ</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">平均セッション時間</h5>
                        <h3 class="text-warning"><?php echo $stats['depth_stats']['avg_session_duration']; ?>分</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- 検索深度分析 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">検索深度分布</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="searchDepthChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">時間帯別検索数</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="hourlySearchesChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 時間帯別詳細分析 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">平日 vs 休日（時間帯別）</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="weekdayWeekendChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">時間帯別検索タイプ分布</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="hourlySearchTypesChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 時間帯別統計 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">時間帯別統計サマリー</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-primary"><?php echo $stats['hourly_stats']['peak_hour']; ?>時</h4>
                                    <small>ピーク時間（<?php echo number_format($stats['hourly_stats']['peak_searches']); ?>回）</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-muted"><?php echo $stats['hourly_stats']['quiet_hour']; ?>時</h4>
                                    <small>静かな時間（<?php echo number_format($stats['hourly_stats']['quiet_searches']); ?>回）</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-success"><?php echo number_format($stats['hourly_stats']['business_hours_searches']); ?></h4>
                                    <small>ビジネス時間（9-17時）</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-info"><?php echo number_format($stats['hourly_stats']['evening_searches']); ?></h4>
                                    <small>夕方時間（18-22時）</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 検索遷移パターン -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">人気検索遷移パターン TOP10</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>順位</th>
                                        <th>遷移パターン</th>
                                        <th>出現回数</th>
                                        <th>割合</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalTransitions = array_sum($stats['transition_patterns']);
                                    $rank = 1;
                                    foreach (array_slice($stats['transition_patterns'], 0, 10, true) as $pattern => $count): 
                                        $percentage = $totalTransitions > 0 ? round(($count / $totalTransitions) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td>
                                            <?php 
                                            $patternParts = explode(' → ', $pattern);
                                            $formattedPattern = '';
                                            foreach ($patternParts as $part) {
                                                $typeNames = [
                                                    'architect' => '建築家',
                                                    'building' => '建築物',
                                                    'prefecture' => '都道府県',
                                                    'text' => 'テキスト'
                                                ];
                                                $formattedPattern .= ($formattedPattern ? ' → ' : '') . ($typeNames[$part] ?? $part);
                                            }
                                            echo $formattedPattern;
                                            ?>
                                        </td>
                                        <td><?php echo number_format($count); ?></td>
                                        <td><?php echo $percentage; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 検索タイプ間遷移マトリックス -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">検索タイプ間遷移マトリックス</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>遷移元</th>
                                        <th>建築家</th>
                                        <th>建築物</th>
                                        <th>都道府県</th>
                                        <th>テキスト</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $typeNames = [
                                        'architect' => '建築家',
                                        'building' => '建築物',
                                        'prefecture' => '都道府県',
                                        'text' => 'テキスト'
                                    ];
                                    
                                    foreach ($stats['type_transitions'] as $fromType => $transitions): 
                                    ?>
                                    <tr>
                                        <td><strong><?php echo $typeNames[$fromType]; ?></strong></td>
                                        <td><?php echo number_format($transitions['to_architect']); ?></td>
                                        <td><?php echo number_format($transitions['to_building']); ?></td>
                                        <td><?php echo number_format($transitions['to_prefecture']); ?></td>
                                        <td><?php echo number_format($transitions['to_text']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
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
                            <a href="user_analytics.php" class="btn btn-outline-info">ユーザー分析</a>
                            <a href="user_pattern_analytics.php" class="btn btn-outline-warning">ユーザーパターン分析</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 検索深度分布グラフ
        const searchDepthData = <?php echo json_encode($stats['search_depth']); ?>;
        const searchDepthCtx = document.getElementById('searchDepthChart').getContext('2d');
        new Chart(searchDepthCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(searchDepthData),
                datasets: [{
                    label: 'セッション数',
                    data: Object.values(searchDepthData),
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

        // 時間帯別検索数グラフ
        const hourlyData = <?php echo json_encode($stats['hourly_data']); ?>;
        const hourlyLabels = Object.values(hourlyData).map(item => item.hour + '時');
        const hourlySearches = Object.values(hourlyData).map(item => item.searches);

        const hourlySearchesCtx = document.getElementById('hourlySearchesChart').getContext('2d');
        new Chart(hourlySearchesCtx, {
            type: 'line',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: '検索数',
                    data: hourlySearches,
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

        // 平日 vs 休日グラフ
        const weekdayData = <?php echo json_encode($stats['weekday_hourly']); ?>;
        const weekendData = <?php echo json_encode($stats['weekend_hourly']); ?>;
        const weekdayWeekendCtx = document.getElementById('weekdayWeekendChart').getContext('2d');
        new Chart(weekdayWeekendCtx, {
            type: 'line',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: '平日',
                    data: Object.values(weekdayData),
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.1
                }, {
                    label: '休日',
                    data: Object.values(weekendData),
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

        // 時間帯別検索タイプ分布グラフ
        const hourlyArchitect = Object.values(hourlyData).map(item => item.architect);
        const hourlyBuilding = Object.values(hourlyData).map(item => item.building);
        const hourlyPrefecture = Object.values(hourlyData).map(item => item.prefecture);
        const hourlyText = Object.values(hourlyData).map(item => item.text);

        const hourlySearchTypesCtx = document.getElementById('hourlySearchTypesChart').getContext('2d');
        new Chart(hourlySearchTypesCtx, {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: '建築家',
                    data: hourlyArchitect,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)'
                }, {
                    label: '建築物',
                    data: hourlyBuilding,
                    backgroundColor: 'rgba(75, 192, 192, 0.8)'
                }, {
                    label: '都道府県',
                    data: hourlyPrefecture,
                    backgroundColor: 'rgba(255, 205, 86, 0.8)'
                }, {
                    label: 'テキスト',
                    data: hourlyText,
                    backgroundColor: 'rgba(255, 99, 132, 0.8)'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        stacked: true
                    }
                }
            }
        });
    </script>
</body>
</html>
