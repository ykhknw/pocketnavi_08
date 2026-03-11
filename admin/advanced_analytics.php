<?php
/**
 * 高度分析ダッシュボード
 * 11. ユーザー行動の異常検知
 * 12. 検索語の関連性分析
 * 13. ユーザー離脱ポイント分析
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
    // 11. ユーザー行動の異常検知
    // 異常な検索パターンの検出
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(user_id, user_session_id) as user_identifier,
            COUNT(*) as total_searches,
            COUNT(DISTINCT DATE(searched_at)) as active_days,
            COUNT(DISTINCT search_type) as search_types_count,
            COUNT(DISTINCT query) as unique_queries,
            MIN(searched_at) as first_search,
            MAX(searched_at) as last_search,
            TIMESTAMPDIFF(SECOND, MIN(searched_at), MAX(searched_at)) as total_span_seconds,
            COUNT(*) / (TIMESTAMPDIFF(SECOND, MIN(searched_at), MAX(searched_at)) + 1) as searches_per_second
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY COALESCE(user_id, user_session_id)
        HAVING total_searches >= 2
        ORDER BY total_searches DESC
    ");
    $stmt->execute([$days]);
    $userData = $stmt->fetchAll();
    
    // 異常検知の分析
    $anomalies = [
        'high_frequency_bots' => [],      // 高頻度検索（ボット疑い）
        'rapid_fire_searchers' => [],     // 高速連続検索
        'low_diversity_searchers' => [],  // 低多様性検索
        'suspicious_patterns' => []       // その他の異常パターン
    ];
    
    $anomalyStats = [
        'total_users' => count($userData),
        'anomaly_count' => 0,
        'bot_suspected' => 0,
        'rapid_fire' => 0,
        'low_diversity' => 0
    ];
    
    foreach ($userData as $user) {
        $isAnomaly = false;
        $anomalyReasons = [];
        
        // 高頻度検索の検出（1秒間に1回以上の検索）
        if ($user['searches_per_second'] > 1) {
            $anomalies['high_frequency_bots'][] = $user;
            $anomalyReasons[] = '高頻度検索（ボット疑い）';
            $anomalyStats['bot_suspected']++;
            $isAnomaly = true;
        }
        
        // 高速連続検索の検出（短時間で大量検索）
        if ($user['total_searches'] > 50 && $user['total_span_seconds'] < 3600) {
            $anomalies['rapid_fire_searchers'][] = $user;
            $anomalyReasons[] = '高速連続検索';
            $anomalyStats['rapid_fire']++;
            $isAnomaly = true;
        }
        
        // 低多様性検索の検出（同じクエリを繰り返し）
        $diversityRatio = $user['unique_queries'] / $user['total_searches'];
        if ($diversityRatio < 0.1 && $user['total_searches'] > 10) {
            $anomalies['low_diversity_searchers'][] = $user;
            $anomalyReasons[] = '低多様性検索';
            $anomalyStats['low_diversity']++;
            $isAnomaly = true;
        }
        
        // その他の異常パターン
        if ($isAnomaly) {
            $user['anomaly_reasons'] = implode(', ', $anomalyReasons);
            $anomalies['suspicious_patterns'][] = $user;
            $anomalyStats['anomaly_count']++;
        }
    }
    
    $stats['anomalies'] = $anomalies;
    $stats['anomaly_stats'] = $anomalyStats;
    
    // 12. 検索語の関連性分析
    // セッション内での検索語の関連性を分析
    $stmt = $pdo->prepare("
        SELECT 
            user_session_id,
            query,
            search_type,
            searched_at,
            ROW_NUMBER() OVER (PARTITION BY user_session_id ORDER BY searched_at) as search_order
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND user_session_id IS NOT NULL
        AND LENGTH(query) > 2
        ORDER BY user_session_id, searched_at
    ");
    $stmt->execute([$days]);
    $queryData = $stmt->fetchAll();
    
    // 検索語の関連性分析
    $queryAssociations = [];
    $queryCooccurrence = [];
    $currentSession = null;
    $sessionQueries = [];
    
    foreach ($queryData as $row) {
        if ($currentSession !== $row['user_session_id']) {
            // 新しいセッション
            if ($currentSession !== null && count($sessionQueries) > 1) {
                // 前のセッションの検索語関連性を記録
                for ($i = 0; $i < count($sessionQueries) - 1; $i++) {
                    for ($j = $i + 1; $j < count($sessionQueries); $j++) {
                        $query1 = $sessionQueries[$i]['query'];
                        $query2 = $sessionQueries[$j]['query'];
                        
                        if ($query1 !== $query2) {
                            $key = $query1 < $query2 ? "$query1|$query2" : "$query2|$query1";
                            $queryCooccurrence[$key] = ($queryCooccurrence[$key] ?? 0) + 1;
                        }
                    }
                }
            }
            $currentSession = $row['user_session_id'];
            $sessionQueries = [];
        }
        
        $sessionQueries[] = $row;
    }
    
    // 最後のセッションの処理
    if ($currentSession !== null && count($sessionQueries) > 1) {
        for ($i = 0; $i < count($sessionQueries) - 1; $i++) {
            for ($j = $i + 1; $j < count($sessionQueries); $j++) {
                $query1 = $sessionQueries[$i]['query'];
                $query2 = $sessionQueries[$j]['query'];
                
                if ($query1 !== $query2) {
                    $key = $query1 < $query2 ? "$query1|$query2" : "$query2|$query1";
                    $queryCooccurrence[$key] = ($queryCooccurrence[$key] ?? 0) + 1;
                }
            }
        }
    }
    
    // 関連性の高い検索語ペアを抽出
    arsort($queryCooccurrence);
    $topAssociations = array_slice($queryCooccurrence, 0, 50, true);
    
    // 検索語の関連性統計
    $associationStats = [
        'total_pairs' => count($queryCooccurrence),
        'high_association_pairs' => 0,  // 3回以上共起
        'medium_association_pairs' => 0, // 2回共起
        'low_association_pairs' => 0    // 1回共起
    ];
    
    foreach ($queryCooccurrence as $count) {
        if ($count >= 3) {
            $associationStats['high_association_pairs']++;
        } elseif ($count == 2) {
            $associationStats['medium_association_pairs']++;
        } else {
            $associationStats['low_association_pairs']++;
        }
    }
    
    $stats['query_associations'] = $topAssociations;
    $stats['association_stats'] = $associationStats;
    
    // 13. ユーザー離脱ポイント分析
    // セッションの最後の検索を離脱ポイントとして分析
    $stmt = $pdo->prepare("
        SELECT 
            user_session_id,
            query,
            search_type,
            searched_at,
            ROW_NUMBER() OVER (PARTITION BY user_session_id ORDER BY searched_at DESC) as reverse_order
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND user_session_id IS NOT NULL
        ORDER BY user_session_id, searched_at
    ");
    $stmt->execute([$days]);
    $sessionData = $stmt->fetchAll();
    
    // 離脱ポイントの分析
    $exitPoints = [
        'by_search_type' => [],
        'by_query' => [],
        'by_session_length' => []
    ];
    
    $exitStats = [
        'total_sessions' => 0,
        'single_search_sessions' => 0,
        'multi_search_sessions' => 0,
        'avg_session_length' => 0
    ];
    
    $currentSession = null;
    $sessionQueries = [];
    $sessionLengths = [];
    
    foreach ($sessionData as $row) {
        if ($currentSession !== $row['user_session_id']) {
            // 新しいセッション
            if ($currentSession !== null && !empty($sessionQueries)) {
                $sessionLength = count($sessionQueries);
                $sessionLengths[] = $sessionLength;
                $exitStats['total_sessions']++;
                
                if ($sessionLength == 1) {
                    $exitStats['single_search_sessions']++;
                } else {
                    $exitStats['multi_search_sessions']++;
                }
                
                // 最後の検索（離脱ポイント）を記録
                $lastQuery = $sessionQueries[0]; // 逆順なので最初が最後
                
                // 検索タイプ別離脱
                $searchType = $lastQuery['search_type'];
                $exitPoints['by_search_type'][$searchType] = ($exitPoints['by_search_type'][$searchType] ?? 0) + 1;
                
                // クエリ別離脱（上位20件のみ）
                $query = $lastQuery['query'];
                $exitPoints['by_query'][$query] = ($exitPoints['by_query'][$query] ?? 0) + 1;
                
                // セッション長別離脱
                if ($sessionLength == 1) {
                    $exitPoints['by_session_length']['1回検索'] = ($exitPoints['by_session_length']['1回検索'] ?? 0) + 1;
                } elseif ($sessionLength <= 3) {
                    $exitPoints['by_session_length']['2-3回検索'] = ($exitPoints['by_session_length']['2-3回検索'] ?? 0) + 1;
                } elseif ($sessionLength <= 5) {
                    $exitPoints['by_session_length']['4-5回検索'] = ($exitPoints['by_session_length']['4-5回検索'] ?? 0) + 1;
                } else {
                    $exitPoints['by_session_length']['6回以上検索'] = ($exitPoints['by_session_length']['6回以上検索'] ?? 0) + 1;
                }
            }
            $currentSession = $row['user_session_id'];
            $sessionQueries = [];
        }
        
        $sessionQueries[] = $row;
    }
    
    // 最後のセッションの処理
    if ($currentSession !== null && !empty($sessionQueries)) {
        $sessionLength = count($sessionQueries);
        $sessionLengths[] = $sessionLength;
        $exitStats['total_sessions']++;
        
        if ($sessionLength == 1) {
            $exitStats['single_search_sessions']++;
        } else {
            $exitStats['multi_search_sessions']++;
        }
        
        $lastQuery = $sessionQueries[0];
        $searchType = $lastQuery['search_type'];
        $exitPoints['by_search_type'][$searchType] = ($exitPoints['by_search_type'][$searchType] ?? 0) + 1;
        
        $query = $lastQuery['query'];
        $exitPoints['by_query'][$query] = ($exitPoints['by_query'][$query] ?? 0) + 1;
        
        if ($sessionLength == 1) {
            $exitPoints['by_session_length']['1回検索'] = ($exitPoints['by_session_length']['1回検索'] ?? 0) + 1;
        } elseif ($sessionLength <= 3) {
            $exitPoints['by_session_length']['2-3回検索'] = ($exitPoints['by_session_length']['2-3回検索'] ?? 0) + 1;
        } elseif ($sessionLength <= 5) {
            $exitPoints['by_session_length']['4-5回検索'] = ($exitPoints['by_session_length']['4-5回検索'] ?? 0) + 1;
        } else {
            $exitPoints['by_session_length']['6回以上検索'] = ($exitPoints['by_session_length']['6回以上検索'] ?? 0) + 1;
        }
    }
    
    // 離脱率の計算
    if (!empty($sessionLengths)) {
        $exitStats['avg_session_length'] = round(array_sum($sessionLengths) / count($sessionLengths), 2);
    }
    
    // クエリ別離脱を頻度順にソート
    arsort($exitPoints['by_query']);
    $exitPoints['by_query'] = array_slice($exitPoints['by_query'], 0, 20, true);
    
    $stats['exit_points'] = $exitPoints;
    $stats['exit_stats'] = $exitStats;
    
    // 離脱率の高い検索タイプの特定
    $stmt = $pdo->prepare("
        SELECT 
            search_type,
            COUNT(*) as total_searches,
            COUNT(DISTINCT user_session_id) as total_sessions
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND user_session_id IS NOT NULL
        GROUP BY search_type
    ");
    $stmt->execute([$days]);
    $searchTypeStats = $stmt->fetchAll();
    
    $exitRates = [];
    foreach ($searchTypeStats as $stat) {
        $searchType = $stat['search_type'];
        $totalSearches = $stat['total_searches'];
        $totalSessions = $stat['total_sessions'];
        $exitCount = $exitPoints['by_search_type'][$searchType] ?? 0;
        
        $exitRates[$searchType] = [
            'total_searches' => $totalSearches,
            'total_sessions' => $totalSessions,
            'exit_count' => $exitCount,
            'exit_rate' => $totalSessions > 0 ? round(($exitCount / $totalSessions) * 100, 1) : 0
        ];
    }
    
    $stats['exit_rates'] = $exitRates;
    
} catch (PDOException $e) {
    $error = "データ取得エラー: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>高度分析 - PocketNavi管理画面</title>
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
                <h1>高度分析ダッシュボード</h1>
                <p class="text-muted">ユーザー行動の異常検知、検索語の関連性分析、ユーザー離脱ポイント分析</p>
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

        <!-- 異常検知サマリー -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">異常ユーザー数</h5>
                        <h3 class="text-danger"><?php echo number_format($stats['anomaly_stats']['anomaly_count']); ?></h3>
                        <small>総ユーザー: <?php echo number_format($stats['anomaly_stats']['total_users']); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">ボット疑い</h5>
                        <h3 class="text-warning"><?php echo number_format($stats['anomaly_stats']['bot_suspected']); ?></h3>
                        <small>高頻度検索</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">高速連続検索</h5>
                        <h3 class="text-info"><?php echo number_format($stats['anomaly_stats']['rapid_fire']); ?></h3>
                        <small>短時間大量検索</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">低多様性検索</h5>
                        <h3 class="text-secondary"><?php echo number_format($stats['anomaly_stats']['low_diversity']); ?></h3>
                        <small>同じクエリ繰り返し</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 異常検知詳細 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">異常検知詳細</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="anomalyAccordion">
                            <!-- ボット疑いユーザー -->
                            <?php if (!empty($stats['anomalies']['high_frequency_bots'])): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingBots">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBots">
                                        ボット疑いユーザー (<?php echo count($stats['anomalies']['high_frequency_bots']); ?>人)
                                    </button>
                                </h2>
                                <div id="collapseBots" class="accordion-collapse collapse" data-bs-parent="#anomalyAccordion">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>ユーザーID</th>
                                                        <th>総検索数</th>
                                                        <th>検索/秒</th>
                                                        <th>アクティブ日数</th>
                                                        <th>初回検索</th>
                                                        <th>最終検索</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($stats['anomalies']['high_frequency_bots'], 0, 10) as $user): ?>
                                                    <tr>
                                                        <td><?php echo substr($user['user_identifier'], 0, 20) . '...'; ?></td>
                                                        <td><?php echo $user['total_searches']; ?></td>
                                                        <td><?php echo round($user['searches_per_second'], 3); ?></td>
                                                        <td><?php echo $user['active_days']; ?></td>
                                                        <td><?php echo date('m/d H:i', strtotime($user['first_search'])); ?></td>
                                                        <td><?php echo date('m/d H:i', strtotime($user['last_search'])); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- 高速連続検索ユーザー -->
                            <?php if (!empty($stats['anomalies']['rapid_fire_searchers'])): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingRapid">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRapid">
                                        高速連続検索ユーザー (<?php echo count($stats['anomalies']['rapid_fire_searchers']); ?>人)
                                    </button>
                                </h2>
                                <div id="collapseRapid" class="accordion-collapse collapse" data-bs-parent="#anomalyAccordion">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>ユーザーID</th>
                                                        <th>総検索数</th>
                                                        <th>時間スパン</th>
                                                        <th>検索タイプ数</th>
                                                        <th>ユニーククエリ数</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($stats['anomalies']['rapid_fire_searchers'], 0, 10) as $user): ?>
                                                    <tr>
                                                        <td><?php echo substr($user['user_identifier'], 0, 20) . '...'; ?></td>
                                                        <td><?php echo $user['total_searches']; ?></td>
                                                        <td><?php echo round($user['total_span_seconds'] / 60, 1); ?>分</td>
                                                        <td><?php echo $user['search_types_count']; ?></td>
                                                        <td><?php echo $user['unique_queries']; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 検索語関連性分析 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">検索語関連性統計</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="associationChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">人気検索語ペア TOP10</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>順位</th>
                                        <th>検索語ペア</th>
                                        <th>共起回数</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    foreach (array_slice($stats['query_associations'], 0, 10, true) as $pair => $count): 
                                        $queries = explode('|', $pair);
                                    ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td>
                                            <small>
                                                <?php echo htmlspecialchars($queries[0]); ?><br>
                                                <strong>↕</strong><br>
                                                <?php echo htmlspecialchars($queries[1]); ?>
                                            </small>
                                        </td>
                                        <td><?php echo $count; ?>回</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 離脱ポイント分析 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">検索タイプ別離脱率</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="exitRateChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">セッション長別離脱分布</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="exitSessionChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 離脱ポイント詳細 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">離脱ポイント詳細</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>検索タイプ別離脱統計</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>検索タイプ</th>
                                                <th>離脱数</th>
                                                <th>総セッション数</th>
                                                <th>離脱率</th>
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
                                            
                                            foreach ($stats['exit_rates'] as $type => $data): 
                                            ?>
                                            <tr>
                                                <td><?php echo $typeNames[$type] ?? $type; ?></td>
                                                <td><?php echo number_format($data['exit_count']); ?></td>
                                                <td><?php echo number_format($data['total_sessions']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $data['exit_rate'] > 50 ? 'bg-danger' : ($data['exit_rate'] > 30 ? 'bg-warning' : 'bg-success'); ?>">
                                                        <?php echo $data['exit_rate']; ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>離脱率の高い検索語 TOP10</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>検索語</th>
                                                <th>離脱数</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $rank = 1;
                                            foreach (array_slice($stats['exit_points']['by_query'], 0, 10, true) as $query => $count): 
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($query); ?></td>
                                                <td><?php echo $count; ?></td>
                                            </tr>
                                            <?php 
                                            $rank++;
                                            endforeach; 
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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
                            <a href="session_analytics.php" class="btn btn-outline-dark">セッション分析</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 検索語関連性統計グラフ
        const associationData = <?php echo json_encode($stats['association_stats']); ?>;
        const associationCtx = document.getElementById('associationChart').getContext('2d');
        new Chart(associationCtx, {
            type: 'doughnut',
            data: {
                labels: ['高関連性（3回以上）', '中関連性（2回）', '低関連性（1回）'],
                datasets: [{
                    data: [
                        associationData.high_association_pairs,
                        associationData.medium_association_pairs,
                        associationData.low_association_pairs
                    ],
                    backgroundColor: ['rgb(255, 99, 132)', 'rgb(54, 162, 235)', 'rgb(255, 205, 86)']
                }]
            },
            options: {
                responsive: true
            }
        });

        // 検索タイプ別離脱率グラフ
        const exitRates = <?php echo json_encode($stats['exit_rates']); ?>;
        const exitRateLabels = [];
        const exitRateData = [];
        const exitRateColors = [];

        Object.keys(exitRates).forEach(type => {
            const typeNames = {
                'architect': '建築家',
                'building': '建築物',
                'prefecture': '都道府県',
                'text': 'テキスト'
            };
            exitRateLabels.push(typeNames[type] || type);
            exitRateData.push(exitRates[type].exit_rate);
            
            // 離脱率に応じて色を設定
            if (exitRates[type].exit_rate > 50) {
                exitRateColors.push('rgb(255, 99, 132)');
            } else if (exitRates[type].exit_rate > 30) {
                exitRateColors.push('rgb(255, 205, 86)');
            } else {
                exitRateColors.push('rgb(75, 192, 192)');
            }
        });

        const exitRateCtx = document.getElementById('exitRateChart').getContext('2d');
        new Chart(exitRateCtx, {
            type: 'bar',
            data: {
                labels: exitRateLabels,
                datasets: [{
                    label: '離脱率 (%)',
                    data: exitRateData,
                    backgroundColor: exitRateColors
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        // セッション長別離脱分布グラフ
        const exitSessionData = <?php echo json_encode($stats['exit_points']['by_session_length']); ?>;
        const exitSessionCtx = document.getElementById('exitSessionChart').getContext('2d');
        new Chart(exitSessionCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(exitSessionData),
                datasets: [{
                    data: Object.values(exitSessionData),
                    backgroundColor: ['rgb(255, 99, 132)', 'rgb(54, 162, 235)', 'rgb(75, 192, 192)', 'rgb(255, 205, 86)']
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>
</body>
</html>
