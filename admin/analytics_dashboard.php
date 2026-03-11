<?php
/**
 * SEO対策用アクセス解析ダッシュボード
 * 
 * global_search_historyテーブルを基にした分析機能
 */

// エラー表示を有効にする（デバッグ用）
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

class AnalyticsDashboard {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * 日別アクセス統計を取得
     */
    public function getDailyStats($days = 30) {
        $sql = "
            SELECT 
                DATE(searched_at) as date,
                COUNT(*) as total_searches,
                COUNT(DISTINCT user_id) as unique_sessions,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(CASE WHEN search_type = 'building' THEN 1 END) as building_searches,
                COUNT(CASE WHEN search_type = 'architect' THEN 1 END) as architect_searches,
                COUNT(CASE WHEN search_type = 'prefecture' THEN 1 END) as prefecture_searches,
                COUNT(CASE WHEN search_type = 'text' THEN 1 END) as text_searches
            FROM global_search_history
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(searched_at)
            ORDER BY date DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 人気検索ワードランキング
     */
    public function getPopularSearches($limit = 20, $days = 30) {
        $sql = "
            SELECT 
                query,
                search_type,
                COUNT(*) as search_count,
                COUNT(DISTINCT user_id) as unique_sessions,
                COUNT(DISTINCT ip_address) as unique_ips,
                MAX(searched_at) as last_searched
            FROM global_search_history
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY query, search_type
            ORDER BY search_count DESC, unique_sessions DESC
            LIMIT " . intval($limit) . "
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 時間別アクセス統計
     */
    public function getHourlyStats($days = 7) {
        $sql = "
            SELECT 
                HOUR(searched_at) as hour,
                COUNT(*) as search_count,
                COUNT(DISTINCT user_id) as unique_sessions
            FROM global_search_history
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY HOUR(searched_at)
            ORDER BY hour
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 言語別統計
     */
    public function getLanguageStats($days = 30) {
        $sql = "
            SELECT 
                JSON_EXTRACT(filters, '$.lang') as language,
                COUNT(*) as search_count,
                COUNT(DISTINCT user_id) as unique_sessions
            FROM global_search_history
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND JSON_EXTRACT(filters, '$.lang') IS NOT NULL
            GROUP BY JSON_EXTRACT(filters, '$.lang')
            ORDER BY search_count DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 地域別統計（IPアドレスベース）
     */
    public function getRegionalStats($days = 30) {
        $sql = "
            SELECT 
                SUBSTRING_INDEX(ip_address, '.', 1) as ip_class,
                COUNT(*) as search_count,
                COUNT(DISTINCT user_id) as unique_sessions,
                COUNT(DISTINCT ip_address) as unique_ips
            FROM global_search_history
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND ip_address IS NOT NULL
            GROUP BY SUBSTRING_INDEX(ip_address, '.', 1)
            ORDER BY search_count DESC
            LIMIT 20
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 検索タイプ別トレンド
     */
    public function getSearchTypeTrends($days = 30) {
        $sql = "
            SELECT 
                DATE(searched_at) as date,
                search_type,
                COUNT(*) as search_count
            FROM global_search_history
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(searched_at), search_type
            ORDER BY date DESC, search_count DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 総合統計
     */
    public function getOverallStats($days = 30) {
        // カラムは既にコンストラクタでチェック済み
        
        $sql = "
            SELECT 
                COUNT(*) as total_searches,
                COUNT(DISTINCT user_id) as total_sessions,
                COUNT(DISTINCT ip_address) as total_ips,
                COUNT(DISTINCT query) as unique_queries,
                AVG(daily_searches) as avg_daily_searches
            FROM (
                SELECT 
                    COUNT(*) as daily_searches
                FROM global_search_history
                WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(searched_at)
            ) as daily_stats
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// パラメータ取得
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$days = max(1, min(365, $days)); // 1-365日の範囲に制限

$analytics = new AnalyticsDashboard($pdo);

// データ取得
$dailyStats = $analytics->getDailyStats($days);
$popularSearches = $analytics->getPopularSearches(20, $days);
$hourlyStats = $analytics->getHourlyStats(7);
$languageStats = $analytics->getLanguageStats($days);
$regionalStats = $analytics->getRegionalStats($days);
$searchTypeTrends = $analytics->getSearchTypeTrends($days);
$overallStats = $analytics->getOverallStats($days);

// グラフ用データの準備
$chartData = [
    'daily' => $dailyStats,
    'hourly' => $hourlyStats,
    'searchTypes' => $searchTypeTrends,
    'languages' => $languageStats
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アクセス解析ダッシュボード - PocketNavi Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }
        
        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .trend-up {
            color: #28a745;
        }
        
        .trend-down {
            color: #dc3545;
        }
        
        .search-type-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- ヘッダー -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0">
                        <i data-lucide="bar-chart-3" class="me-2" style="width: 24px; height: 24px;"></i>
                        アクセス解析ダッシュボード
                    </h1>
                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i data-lucide="arrow-left" class="me-1" style="width: 16px; height: 16px;"></i>
                            管理画面に戻る
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- フィルター -->
        <div class="filter-section">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3">
                    <label for="days" class="form-label">分析期間</label>
                    <select name="days" id="days" class="form-select">
                        <option value="7" <?= $days == 7 ? 'selected' : '' ?>>過去7日間</option>
                        <option value="30" <?= $days == 30 ? 'selected' : '' ?>>過去30日間</option>
                        <option value="90" <?= $days == 90 ? 'selected' : '' ?>>過去90日間</option>
                        <option value="365" <?= $days == 365 ? 'selected' : '' ?>>過去1年間</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="filter" class="me-1" style="width: 16px; height: 16px;"></i>
                        適用
                    </button>
                </div>
            </form>
        </div>
        
        <!-- 総合統計 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?= number_format($overallStats['total_searches']) ?></h3>
                    <p>総検索数</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?= number_format($overallStats['total_sessions']) ?></h3>
                    <p>ユニークセッション</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?= number_format($overallStats['total_ips']) ?></h3>
                    <p>ユニークIP</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?= number_format($overallStats['unique_queries']) ?></h3>
                    <p>ユニーククエリ</p>
                </div>
            </div>
        </div>
        
        <!-- グラフセクション -->
        <div class="row">
            <!-- 日別アクセス推移 -->
            <div class="col-lg-8">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i data-lucide="trending-up" class="me-2" style="width: 20px; height: 20px;"></i>
                        日別アクセス推移
                    </h5>
                    <canvas id="dailyChart" height="100"></canvas>
                </div>
            </div>
            
            <!-- 検索タイプ別分布 -->
            <div class="col-lg-4">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i data-lucide="pie-chart" class="me-2" style="width: 20px; height: 20px;"></i>
                        検索タイプ別分布
                    </h5>
                    <canvas id="searchTypeChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- 時間別アクセス -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i data-lucide="clock" class="me-2" style="width: 20px; height: 20px;"></i>
                        時間別アクセス（過去7日間）
                    </h5>
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
            
            <!-- 言語別分布 -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i data-lucide="globe" class="me-2" style="width: 20px; height: 20px;"></i>
                        言語別分布
                    </h5>
                    <canvas id="languageChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- 人気検索ワード -->
        <div class="row">
            <div class="col-12">
                <div class="table-container">
                    <h5 class="mb-3">
                        <i data-lucide="search" class="me-2" style="width: 20px; height: 20px;"></i>
                        人気検索ワード TOP 20
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>順位</th>
                                    <th>検索ワード</th>
                                    <th>タイプ</th>
                                    <th>検索数</th>
                                    <th>ユニークセッション</th>
                                    <th>ユニークIP</th>
                                    <th>最終検索</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($popularSearches as $index => $search): ?>
                                <tr>
                                    <td><span class="badge bg-primary"><?= $index + 1 ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($search['query']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge search-type-badge bg-<?= $search['search_type'] === 'building' ? 'success' : ($search['search_type'] === 'architect' ? 'info' : ($search['search_type'] === 'prefecture' ? 'warning' : 'secondary')) ?>">
                                            <?= $search['search_type'] === 'building' ? '建築物' : ($search['search_type'] === 'architect' ? '建築家' : ($search['search_type'] === 'prefecture' ? '都道府県' : 'テキスト')) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($search['search_count']) ?></td>
                                    <td><?= number_format($search['unique_sessions']) ?></td>
                                    <td><?= number_format($search['unique_ips']) ?></td>
                                    <td><?= date('m/d H:i', strtotime($search['last_searched'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 地域別統計 -->
        <div class="row">
            <div class="col-12">
                <div class="table-container">
                    <h5 class="mb-3">
                        <i data-lucide="map-pin" class="me-2" style="width: 20px; height: 20px;"></i>
                        地域別アクセス統計（IPクラス別）
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>IPクラス</th>
                                    <th>検索数</th>
                                    <th>ユニークセッション</th>
                                    <th>ユニークIP</th>
                                    <th>占有率</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalSearches = array_sum(array_column($regionalStats, 'search_count'));
                                foreach ($regionalStats as $region): 
                                    $percentage = $totalSearches > 0 ? ($region['search_count'] / $totalSearches) * 100 : 0;
                                ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($region['ip_class']) ?>.x.x.x</code></td>
                                    <td><?= number_format($region['search_count']) ?></td>
                                    <td><?= number_format($region['unique_sessions']) ?></td>
                                    <td><?= number_format($region['unique_ips']) ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" style="width: <?= $percentage ?>%">
                                                <?= number_format($percentage, 1) ?>%
                                            </div>
                                        </div>
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
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Lucide Icons初期化
        lucide.createIcons();
        
        // グラフデータ
        const chartData = <?= json_encode($chartData) ?>;
        
        // 日別アクセス推移グラフ
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: chartData.daily.map(d => d.date).reverse(),
                datasets: [
                    {
                        label: '総検索数',
                        data: chartData.daily.map(d => d.total_searches).reverse(),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'ユニークセッション',
                        data: chartData.daily.map(d => d.unique_sessions).reverse(),
                        borderColor: '#764ba2',
                        backgroundColor: 'rgba(118, 75, 162, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // 検索タイプ別分布グラフ
        const searchTypeCtx = document.getElementById('searchTypeChart').getContext('2d');
        const searchTypeData = {
            building: chartData.daily.reduce((sum, d) => sum + parseInt(d.building_searches), 0),
            architect: chartData.daily.reduce((sum, d) => sum + parseInt(d.architect_searches), 0),
            prefecture: chartData.daily.reduce((sum, d) => sum + parseInt(d.prefecture_searches), 0),
            text: chartData.daily.reduce((sum, d) => sum + parseInt(d.text_searches), 0)
        };
        
        new Chart(searchTypeCtx, {
            type: 'doughnut',
            data: {
                labels: ['建築物', '建築家', '都道府県', 'テキスト'],
                datasets: [{
                    data: [searchTypeData.building, searchTypeData.architect, searchTypeData.prefecture, searchTypeData.text],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
        
        // 時間別アクセスグラフ
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: chartData.hourly.map(h => h.hour + ':00'),
                datasets: [{
                    label: '検索数',
                    data: chartData.hourly.map(h => h.search_count),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // 言語別分布グラフ
        const languageCtx = document.getElementById('languageChart').getContext('2d');
        new Chart(languageCtx, {
            type: 'pie',
            data: {
                labels: chartData.languages.map(l => l.language === 'ja' ? '日本語' : '英語'),
                datasets: [{
                    data: chartData.languages.map(l => l.search_count),
                    backgroundColor: ['#dc3545', '#007bff']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    </script>
</body>
</html>
