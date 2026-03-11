<?php
/**
 * セッション分析ダッシュボード
 * 8. セッション滞在時間推定
 * 9. 検索間隔分析
 * 10. 検索頻度によるユーザーセグメント分析
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
    // 8. セッション滞在時間推定
    // セッション別の詳細分析
    $stmt = $pdo->prepare("
        SELECT 
            user_session_id,
            COUNT(*) as search_count,
            MIN(searched_at) as session_start,
            MAX(searched_at) as session_end,
            TIMESTAMPDIFF(SECOND, MIN(searched_at), MAX(searched_at)) as session_duration_seconds,
            TIMESTAMPDIFF(MINUTE, MIN(searched_at), MAX(searched_at)) as session_duration_minutes,
            COUNT(DISTINCT search_type) as search_types_count,
            COUNT(DISTINCT query) as unique_queries,
            GROUP_CONCAT(DISTINCT search_type ORDER BY search_type) as search_types
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND user_session_id IS NOT NULL
        GROUP BY user_session_id
        HAVING search_count >= 2
        ORDER BY session_duration_seconds DESC
    ");
    $stmt->execute([$days]);
    $sessionData = $stmt->fetchAll();
    
    // 滞在時間の分布分析
    $durationDistribution = [
        '0分（即座）' => 0,
        '1-2分' => 0,
        '3-5分' => 0,
        '6-10分' => 0,
        '11-20分' => 0,
        '21-30分' => 0,
        '31-60分' => 0,
        '60分以上' => 0
    ];
    
    $durationStats = [
        'total_sessions' => count($sessionData),
        'avg_duration_minutes' => 0,
        'median_duration_minutes' => 0,
        'longest_session_minutes' => 0,
        'short_sessions' => 0,      // 5分未満
        'medium_sessions' => 0,     // 5-20分
        'long_sessions' => 0,       // 20分以上
        'instant_sessions' => 0     // 0分（即座）
    ];
    
    if (!empty($sessionData)) {
        $totalDuration = array_sum(array_column($sessionData, 'session_duration_minutes'));
        $durationStats['avg_duration_minutes'] = round($totalDuration / count($sessionData), 2);
        $durationStats['longest_session_minutes'] = max(array_column($sessionData, 'session_duration_minutes'));
        
        // 中央値の計算
        $durations = array_column($sessionData, 'session_duration_minutes');
        sort($durations);
        $middle = floor(count($durations) / 2);
        $durationStats['median_duration_minutes'] = $durations[$middle];
        
        foreach ($sessionData as $session) {
            $duration = $session['session_duration_minutes'];
            
            // 滞在時間分布
            if ($duration == 0) {
                $durationDistribution['0分（即座）']++;
                $durationStats['instant_sessions']++;
            } elseif ($duration <= 2) {
                $durationDistribution['1-2分']++;
            } elseif ($duration <= 5) {
                $durationDistribution['3-5分']++;
            } elseif ($duration <= 10) {
                $durationDistribution['6-10分']++;
            } elseif ($duration <= 20) {
                $durationDistribution['11-20分']++;
            } elseif ($duration <= 30) {
                $durationDistribution['21-30分']++;
            } elseif ($duration <= 60) {
                $durationDistribution['31-60分']++;
            } else {
                $durationDistribution['60分以上']++;
            }
            
            // セッション長分類
            if ($duration < 5) {
                $durationStats['short_sessions']++;
            } elseif ($duration <= 20) {
                $durationStats['medium_sessions']++;
            } else {
                $durationStats['long_sessions']++;
            }
        }
    }
    
    $stats['duration_distribution'] = $durationDistribution;
    $stats['duration_stats'] = $durationStats;
    
    // 9. 検索間隔分析
    // セッション内の検索間隔を分析
    $stmt = $pdo->prepare("
        SELECT 
            user_session_id,
            searched_at,
            LAG(searched_at) OVER (PARTITION BY user_session_id ORDER BY searched_at) as previous_search,
            TIMESTAMPDIFF(SECOND, LAG(searched_at) OVER (PARTITION BY user_session_id ORDER BY searched_at), searched_at) as interval_seconds
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND user_session_id IS NOT NULL
        ORDER BY user_session_id, searched_at
    ");
    $stmt->execute([$days]);
    $intervalData = $stmt->fetchAll();
    
    // 検索間隔の分析
    $intervalDistribution = [
        '0-10秒' => 0,
        '11-30秒' => 0,
        '31-60秒' => 0,
        '1-2分' => 0,
        '2-5分' => 0,
        '5-10分' => 0,
        '10分以上' => 0
    ];
    
    $intervalStats = [
        'total_intervals' => 0,
        'avg_interval_seconds' => 0,
        'median_interval_seconds' => 0,
        'quick_searchers' => 0,     // 30秒以内
        'thoughtful_searchers' => 0, // 2分以上
        'rapid_fire_searchers' => 0  // 10秒以内
    ];
    
    $intervals = [];
    foreach ($intervalData as $row) {
        if ($row['interval_seconds'] !== null) {
            $interval = $row['interval_seconds'];
            $intervals[] = $interval;
            
            // 間隔分布
            if ($interval <= 10) {
                $intervalDistribution['0-10秒']++;
            } elseif ($interval <= 30) {
                $intervalDistribution['11-30秒']++;
            } elseif ($interval <= 60) {
                $intervalDistribution['31-60秒']++;
            } elseif ($interval <= 120) {
                $intervalDistribution['1-2分']++;
            } elseif ($interval <= 300) {
                $intervalDistribution['2-5分']++;
            } elseif ($interval <= 600) {
                $intervalDistribution['5-10分']++;
            } else {
                $intervalDistribution['10分以上']++;
            }
            
            // 検索スタイル分類
            if ($interval <= 10) {
                $intervalStats['rapid_fire_searchers']++;
            } elseif ($interval <= 30) {
                $intervalStats['quick_searchers']++;
            } elseif ($interval >= 120) {
                $intervalStats['thoughtful_searchers']++;
            }
        }
    }
    
    if (!empty($intervals)) {
        $intervalStats['total_intervals'] = count($intervals);
        $intervalStats['avg_interval_seconds'] = round(array_sum($intervals) / count($intervals), 2);
        
        // 中央値の計算
        sort($intervals);
        $middle = floor(count($intervals) / 2);
        $intervalStats['median_interval_seconds'] = $intervals[$middle];
    }
    
    $stats['interval_distribution'] = $intervalDistribution;
    $stats['interval_stats'] = $intervalStats;
    
    // 10. 検索頻度によるユーザーセグメント分析
    // ユーザー別の検索頻度分析
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(user_id, user_session_id) as user_identifier,
            COUNT(*) as total_searches,
            COUNT(DISTINCT DATE(searched_at)) as active_days,
            MIN(searched_at) as first_search,
            MAX(searched_at) as last_search,
            DATEDIFF(MAX(searched_at), MIN(searched_at)) + 1 as span_days,
            COUNT(*) / (DATEDIFF(MAX(searched_at), MIN(searched_at)) + 1) as searches_per_day,
            COUNT(DISTINCT search_type) as search_types_count
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY COALESCE(user_id, user_session_id)
        HAVING total_searches >= 2
        ORDER BY total_searches DESC
    ");
    $stmt->execute([$days]);
    $userData = $stmt->fetchAll();
    
    // ユーザーセグメント分析
    $userSegments = [
        'high_frequency' => 0,      // 日1回以上
        'medium_frequency' => 0,    // 週1-3回
        'low_frequency' => 0,       // 月1回以下
        'one_time' => 0,            // 1回のみ
        'power_users' => 0,         // 日2回以上
        'regular_users' => 0,       // 週1回以上
        'casual_users' => 0         // 月1回以下
    ];
    
    $segmentDetails = [
        'high_frequency' => [],
        'medium_frequency' => [],
        'low_frequency' => [],
        'one_time' => [],
        'power_users' => [],
        'regular_users' => [],
        'casual_users' => []
    ];
    
    $segmentStats = [
        'total_users' => count($userData),
        'avg_searches_per_user' => 0,
        'avg_active_days' => 0,
        'avg_searches_per_day' => 0,
        'retention_rate' => 0  // 複数日アクセスするユーザーの割合
    ];
    
    if (!empty($userData)) {
        $totalSearches = array_sum(array_column($userData, 'total_searches'));
        $totalActiveDays = array_sum(array_column($userData, 'active_days'));
        $totalSearchesPerDay = array_sum(array_column($userData, 'searches_per_day'));
        $multiDayUsers = 0;
        
        $segmentStats['avg_searches_per_user'] = round($totalSearches / count($userData), 2);
        $segmentStats['avg_active_days'] = round($totalActiveDays / count($userData), 2);
        $segmentStats['avg_searches_per_day'] = round($totalSearchesPerDay / count($userData), 2);
        
        foreach ($userData as $user) {
            $searchesPerDay = $user['searches_per_day'];
            $activeDays = $user['active_days'];
            $totalSearches = $user['total_searches'];
            
            if ($activeDays > 1) {
                $multiDayUsers++;
            }
            
            // ユーザーセグメント分類
            if ($searchesPerDay >= 1) {
                $userSegments['high_frequency']++;
                $segmentDetails['high_frequency'][] = $user;
                
                if ($searchesPerDay >= 2) {
                    $userSegments['power_users']++;
                    $segmentDetails['power_users'][] = $user;
                }
            } elseif ($searchesPerDay >= 0.14) { // 週1回以上（1/7）
                $userSegments['medium_frequency']++;
                $segmentDetails['medium_frequency'][] = $user;
                $userSegments['regular_users']++;
                $segmentDetails['regular_users'][] = $user;
            } elseif ($searchesPerDay >= 0.033) { // 月1回以上（1/30）
                $userSegments['low_frequency']++;
                $segmentDetails['low_frequency'][] = $user;
                $userSegments['casual_users']++;
                $segmentDetails['casual_users'][] = $user;
            } else {
                $userSegments['one_time']++;
                $segmentDetails['one_time'][] = $user;
            }
        }
        
        $segmentStats['retention_rate'] = round(($multiDayUsers / count($userData)) * 100, 1);
    }
    
    $stats['user_segments'] = $userSegments;
    $stats['segment_details'] = $segmentDetails;
    $stats['segment_stats'] = $segmentStats;
    
    // セグメント別の検索内容分析
    $segmentContentAnalysis = [];
    foreach (['power_users', 'regular_users', 'casual_users'] as $segment) {
        if (!empty($segmentDetails[$segment])) {
            $userIds = array_column($segmentDetails[$segment], 'user_identifier');
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            
            $stmt = $pdo->prepare("
                SELECT 
                    search_type,
                    COUNT(*) as count
                FROM global_search_history 
                WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND COALESCE(user_id, user_session_id) IN ($placeholders)
                GROUP BY search_type
                ORDER BY count DESC
            ");
            $params = array_merge([$days], $userIds);
            $stmt->execute($params);
            $segmentContentAnalysis[$segment] = $stmt->fetchAll();
        }
    }
    
    $stats['segment_content_analysis'] = $segmentContentAnalysis;
    
} catch (PDOException $e) {
    $error = "データ取得エラー: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>セッション分析 - PocketNavi管理画面</title>
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
                <h1>セッション分析ダッシュボード</h1>
                <p class="text-muted">セッション滞在時間推定、検索間隔分析、検索頻度によるユーザーセグメント分析</p>
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

        <!-- 滞在時間サマリー -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">平均滞在時間</h5>
                        <h3 class="text-primary"><?php echo $stats['duration_stats']['avg_duration_minutes']; ?>分</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">中央値滞在時間</h5>
                        <h3 class="text-info"><?php echo $stats['duration_stats']['median_duration_minutes']; ?>分</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">最長セッション</h5>
                        <h3 class="text-success"><?php echo $stats['duration_stats']['longest_session_minutes']; ?>分</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">即座離脱</h5>
                        <h3 class="text-warning"><?php echo number_format($stats['duration_stats']['instant_sessions']); ?></h3>
                        <small>0分セッション</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 滞在時間分析 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">滞在時間分布</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="durationChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">セッション長分類</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="sessionLengthChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 検索間隔分析 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">検索間隔分布</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="intervalChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">検索スタイル分類</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="searchStyleChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 検索間隔統計 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">検索間隔統計サマリー</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-primary"><?php echo $stats['interval_stats']['avg_interval_seconds']; ?>秒</h4>
                                    <small>平均検索間隔</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-info"><?php echo $stats['interval_stats']['median_interval_seconds']; ?>秒</h4>
                                    <small>中央値検索間隔</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-success"><?php echo number_format($stats['interval_stats']['rapid_fire_searchers']); ?></h4>
                                    <small>高速検索ユーザー（10秒以内）</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-warning"><?php echo number_format($stats['interval_stats']['thoughtful_searchers']); ?></h4>
                                    <small>慎重検索ユーザー（2分以上）</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ユーザーセグメント分析 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">ユーザーセグメント分析</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-danger"><?php echo number_format($stats['user_segments']['power_users']); ?></h4>
                                    <small>パワーユーザー<br>（日2回以上）</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-primary"><?php echo number_format($stats['user_segments']['high_frequency']); ?></h4>
                                    <small>高頻度ユーザー<br>（日1回以上）</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-success"><?php echo number_format($stats['user_segments']['regular_users']); ?></h4>
                                    <small>定期ユーザー<br>（週1回以上）</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-info"><?php echo number_format($stats['user_segments']['medium_frequency']); ?></h4>
                                    <small>中頻度ユーザー<br>（週1-3回）</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-warning"><?php echo number_format($stats['user_segments']['casual_users']); ?></h4>
                                    <small>カジュアルユーザー<br>（月1回以下）</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-secondary"><?php echo number_format($stats['user_segments']['one_time']); ?></h4>
                                    <small>ワンタイムユーザー<br>（1回のみ）</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ユーザーセグメントグラフ -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">ユーザーセグメント分布</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="userSegmentChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">セグメント別検索内容</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="segmentContentChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- セグメント統計 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">セグメント統計サマリー</h5>
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
                                        <td>総ユーザー数</td>
                                        <td><?php echo number_format($stats['segment_stats']['total_users']); ?></td>
                                        <td>過去<?php echo $days; ?>日間のアクティブユーザー</td>
                                    </tr>
                                    <tr>
                                        <td>平均検索数/ユーザー</td>
                                        <td><?php echo $stats['segment_stats']['avg_searches_per_user']; ?></td>
                                        <td>1ユーザーあたりの平均検索回数</td>
                                    </tr>
                                    <tr>
                                        <td>平均アクティブ日数</td>
                                        <td><?php echo $stats['segment_stats']['avg_active_days']; ?>日</td>
                                        <td>1ユーザーあたりの平均アクティブ日数</td>
                                    </tr>
                                    <tr>
                                        <td>平均検索数/日</td>
                                        <td><?php echo $stats['segment_stats']['avg_searches_per_day']; ?></td>
                                        <td>1ユーザーあたりの1日平均検索数</td>
                                    </tr>
                                    <tr>
                                        <td>リテンション率</td>
                                        <td><?php echo $stats['segment_stats']['retention_rate']; ?>%</td>
                                        <td>複数日アクセスするユーザーの割合</td>
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
                            <a href="user_analytics.php" class="btn btn-outline-info">ユーザー分析</a>
                            <a href="user_pattern_analytics.php" class="btn btn-outline-warning">ユーザーパターン分析</a>
                            <a href="search_behavior_analytics.php" class="btn btn-outline-secondary">検索行動分析</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 滞在時間分布グラフ
        const durationData = <?php echo json_encode($stats['duration_distribution']); ?>;
        const durationCtx = document.getElementById('durationChart').getContext('2d');
        new Chart(durationCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(durationData),
                datasets: [{
                    label: 'セッション数',
                    data: Object.values(durationData),
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

        // セッション長分類グラフ
        const sessionLengthData = {
            '短時間': <?php echo $stats['duration_stats']['short_sessions']; ?>,
            '中時間': <?php echo $stats['duration_stats']['medium_sessions']; ?>,
            '長時間': <?php echo $stats['duration_stats']['long_sessions']; ?>
        };
        const sessionLengthCtx = document.getElementById('sessionLengthChart').getContext('2d');
        new Chart(sessionLengthCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(sessionLengthData),
                datasets: [{
                    data: Object.values(sessionLengthData),
                    backgroundColor: ['rgb(255, 99, 132)', 'rgb(54, 162, 235)', 'rgb(255, 205, 86)']
                }]
            },
            options: {
                responsive: true
            }
        });

        // 検索間隔分布グラフ
        const intervalData = <?php echo json_encode($stats['interval_distribution']); ?>;
        const intervalCtx = document.getElementById('intervalChart').getContext('2d');
        new Chart(intervalCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(intervalData),
                datasets: [{
                    label: '間隔数',
                    data: Object.values(intervalData),
                    backgroundColor: 'rgba(153, 102, 255, 0.8)'
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

        // 検索スタイル分類グラフ
        const searchStyleData = {
            '高速検索': <?php echo $stats['interval_stats']['rapid_fire_searchers']; ?>,
            'クイック検索': <?php echo $stats['interval_stats']['quick_searchers']; ?>,
            '慎重検索': <?php echo $stats['interval_stats']['thoughtful_searchers']; ?>
        };
        const searchStyleCtx = document.getElementById('searchStyleChart').getContext('2d');
        new Chart(searchStyleCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(searchStyleData),
                datasets: [{
                    data: Object.values(searchStyleData),
                    backgroundColor: ['rgb(255, 99, 132)', 'rgb(75, 192, 192)', 'rgb(255, 205, 86)']
                }]
            },
            options: {
                responsive: true
            }
        });

        // ユーザーセグメント分布グラフ
        const userSegmentData = <?php echo json_encode($stats['user_segments']); ?>;
        const userSegmentCtx = document.getElementById('userSegmentChart').getContext('2d');
        new Chart(userSegmentCtx, {
            type: 'doughnut',
            data: {
                labels: ['パワーユーザー', '高頻度', '定期', '中頻度', 'カジュアル', 'ワンタイム'],
                datasets: [{
                    data: [
                        userSegmentData.power_users,
                        userSegmentData.high_frequency,
                        userSegmentData.regular_users,
                        userSegmentData.medium_frequency,
                        userSegmentData.casual_users,
                        userSegmentData.one_time
                    ],
                    backgroundColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(75, 192, 192)',
                        'rgb(255, 205, 86)',
                        'rgb(153, 102, 255)',
                        'rgb(201, 203, 207)'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });

        // セグメント別検索内容グラフ
        const segmentContentData = <?php echo json_encode($stats['segment_content_analysis']); ?>;
        const segmentContentCtx = document.getElementById('segmentContentChart').getContext('2d');
        
        // パワーユーザーの検索タイプ分布
        const powerUserData = segmentContentData.power_users || [];
        const regularUserData = segmentContentData.regular_users || [];
        const casualUserData = segmentContentData.casual_users || [];
        
        const typeNames = {
            'architect': '建築家',
            'building': '建築物',
            'prefecture': '都道府県',
            'text': 'テキスト'
        };
        
        const powerUserTypes = powerUserData.map(item => typeNames[item.search_type] || item.search_type);
        const powerUserCounts = powerUserData.map(item => item.count);
        
        new Chart(segmentContentCtx, {
            type: 'bar',
            data: {
                labels: powerUserTypes,
                datasets: [{
                    label: 'パワーユーザー',
                    data: powerUserCounts,
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
