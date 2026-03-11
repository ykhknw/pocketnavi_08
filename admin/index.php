<?php
/**
 * PocketNavi 管理画面メインページ
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

//    $host = 'localhost';
//    $db_name = '_shinkenchiku_02';
//    $username = 'root';
//    $password = '';
    
    $pdo = new PDO("mysql:host={$host};dbname={$db_name};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// 簡単な統計情報を取得
$stats = [];
try {
    // 総検索数
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM global_search_history");
    $stats['total_searches'] = $stmt->fetch()['total'];
    
    // 今日の検索数
    $stmt = $pdo->query("SELECT COUNT(*) as today FROM global_search_history WHERE DATE(searched_at) = CURDATE()");
    $stats['today_searches'] = $stmt->fetch()['today'];
    
    // ユニークセッション数（過去7日）
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_session_id) as sessions FROM global_search_history WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['weekly_sessions'] = $stmt->fetch()['sessions'];
    
    // テーブルサイズ
    $stmt = $pdo->query("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'global_search_history'");
    $stats['table_size_mb'] = $stmt->fetch()['size_mb'] ?? 0;
    
} catch (Exception $e) {
    $stats = [
        'total_searches' => 0,
        'today_searches' => 0,
        'weekly_sessions' => 0,
        'table_size_mb' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketNavi 管理画面</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
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
        
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .feature-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            color: inherit;
            text-decoration: none;
        }
        
        .feature-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-bottom: 1rem;
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
                        <i data-lucide="settings" class="me-2" style="width: 24px; height: 24px;"></i>
                        PocketNavi 管理画面
                    </h1>
                    <div class="d-flex gap-2">
                        <span class="text-muted">管理者: <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></span>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm">
                            <i data-lucide="log-out" class="me-1" style="width: 16px; height: 16px;"></i>
                            ログアウト
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 統計カード -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?= number_format($stats['total_searches']) ?></h3>
                    <p>総検索数</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?= number_format($stats['today_searches']) ?></h3>
                    <p>今日の検索数</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?= number_format($stats['weekly_sessions']) ?></h3>
                    <p>週間セッション数</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?= number_format($stats['table_size_mb'], 1) ?>MB</h3>
                    <p>テーブルサイズ</p>
                </div>
            </div>
        </div>
        
        <!-- 機能メニュー -->
        <div class="row">
            <!-- アクセス解析 -->
            <div class="col-lg-4 col-md-6">
                <a href="simple_analytics.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="bar-chart-3" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>シンプルアクセス解析</h5>
                    <p class="text-muted mb-0">基本的な検索統計と人気検索ワードを表示。軽量で高速。</p>
                </a>
            </div>
            
            <!-- ユーザー分析 -->
            <div class="col-lg-4 col-md-6">
                <a href="user_analytics.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="users" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>ユーザー分析</h5>
                    <p class="text-muted mb-0">日別ユニークユーザー数推移とセッション分析。ユーザー行動を深く理解できます。</p>
                </a>
            </div>
            
            <!-- ユーザーパターン分析 -->
            <div class="col-lg-4 col-md-6">
                <a href="user_pattern_analytics.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="user-check" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>ユーザーパターン分析</h5>
                    <p class="text-muted mb-0">検索行動パターンによるユーザー分類と地域別分析。ターゲットユーザーの特定に活用できます。</p>
                </a>
            </div>
            
            <!-- 検索行動分析 -->
            <div class="col-lg-4 col-md-6">
                <a href="search_behavior_analytics.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="search" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>検索行動分析</h5>
                    <p class="text-muted mb-0">検索深度、遷移パターン、時間帯別行動の詳細分析。ユーザーの検索行動を深く理解できます。</p>
                </a>
            </div>
            
            <!-- セッション分析 -->
            <div class="col-lg-4 col-md-6">
                <a href="session_analytics.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="clock" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>セッション分析</h5>
                    <p class="text-muted mb-0">滞在時間推定、検索間隔分析、ユーザーセグメント分析。ユーザーの行動パターンを詳細に分析できます。</p>
                </a>
            </div>
            
            <!-- 高度分析 -->
            <div class="col-lg-4 col-md-6">
                <a href="advanced_analytics.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="shield-alert" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>高度分析</h5>
                    <p class="text-muted mb-0">異常検知、検索語関連性分析、離脱ポイント分析。高度な分析機能でユーザー行動を深く理解できます。</p>
                </a>
            </div>
            
            <!-- 詳細アクセス解析 -->
            <div class="col-lg-4 col-md-6">
                <a href="analytics_dashboard.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="trending-up" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>詳細アクセス解析</h5>
                    <p class="text-muted mb-0">高度な分析機能と詳細なグラフ表示。SEO対策に活用できます。</p>
                </a>
            </div>
            
            <!-- 検索履歴管理 -->
            <div class="col-lg-4 col-md-6">
                <a href="search_history_management.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="database" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>検索履歴管理</h5>
                    <p class="text-muted mb-0">データベースのクリーンアップとアーカイブ機能。</p>
                </a>
            </div>
            
            <!-- HETEML最適化 -->
            <div class="col-lg-4 col-md-6">
                <a href="heteml_cleanup.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="server" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>HETEML最適化</h5>
                    <p class="text-muted mb-0">HETEML環境に特化したデータ管理と最適化。</p>
                </a>
            </div>
            
            <!-- 人気検索管理 -->
            <div class="col-lg-4 col-md-6">
                <a href="popular_searches_management.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="trending-up" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>人気検索管理</h5>
                    <p class="text-muted mb-0">人気検索ワードの管理と表示設定。</p>
                </a>
            </div>
            
            <!-- システム設定 -->
            <div class="col-lg-4 col-md-6">
                <a href="system_settings.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="settings" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>システム設定</h5>
                    <p class="text-muted mb-0">アプリケーションの各種設定とメンテナンス。</p>
                </a>
            </div>
            
            <!-- ログ管理 -->
            <div class="col-lg-4 col-md-6">
                <a href="log_management.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="file-text" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>ログ管理</h5>
                    <p class="text-muted mb-0">システムログの確認と管理。</p>
                </a>
            </div>
            
            <!-- 検索キャッシュ管理 -->
            <div class="col-lg-4 col-md-6">
                <a href="simple_cache_management.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="zap" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>検索キャッシュ管理</h5>
                    <p class="text-muted mb-0">検索結果キャッシュの状態確認と管理。パフォーマンス向上のためのキャッシュシステム。</p>
                </a>
            </div>
            
            <!-- CSRFトークンデバッグ -->
            <div class="col-lg-4 col-md-6">
                <a href="csrf_debug.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="shield-check" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>CSRFトークンデバッグ</h5>
                    <p class="text-muted mb-0">CSRFトークンの状態確認とデバッグ。セキュリティ機能の動作確認。</p>
                </a>
            </div>
            
            <!-- SameSite Cookieデバッグ -->
            <div class="col-lg-4 col-md-6">
                <a href="samesite_cookie_debug.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="cookie" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>SameSite Cookieデバッグ</h5>
                    <p class="text-muted mb-0">SameSite Cookieの状態確認とデバッグ。Cookie設定の動作確認。</p>
                </a>
            </div>
            
            <!-- 人気検索ワードキャッシュ管理 -->
            <div class="col-lg-4 col-md-6">
                <a href="cache_management.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="star" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>人気検索ワードキャッシュ管理</h5>
                    <p class="text-muted mb-0">人気の検索ワードのキャッシュ管理画面。検索統計とキャッシュ状態の確認。</p>
                </a>
            </div>
            
            <!-- キャッシュテスト -->
            <div class="col-lg-4 col-md-6">
                <a href="../index_refactored_cache_test.php" class="feature-card d-block" target="_blank">
                    <div class="feature-icon">
                        <i data-lucide="play-circle" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>キャッシュテスト版</h5>
                    <p class="text-muted mb-0">キャッシュ機能付きの検索システムをテスト。パフォーマンス比較が可能。</p>
                </a>
            </div>
            
            <!-- 建築物コラム編集 -->
            <div class="col-lg-4 col-md-6">
                <a href="edit_building_column.php" class="feature-card d-block">
                    <div class="feature-icon">
                        <i data-lucide="file-text" style="width: 24px; height: 24px;"></i>
                    </div>
                    <h5>建築物コラム編集</h5>
                    <p class="text-muted mb-0">建築物詳細ページに表示されるコラム本文と小見出しを編集。Markdown形式で入力可能。</p>
                </a>
            </div>
        </div>
        
        <!-- 最近のアクティビティ -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="feature-card">
                    <h5 class="mb-3">
                        <i data-lucide="activity" class="me-2" style="width: 20px; height: 20px;"></i>
                        最近のアクティビティ
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>時刻</th>
                                    <th>検索ワード</th>
                                    <th>タイプ</th>
                                    <th>IPアドレス</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $pdo->query("
                                        SELECT query, search_type, ip_address, searched_at 
                                        FROM global_search_history 
                                        ORDER BY searched_at DESC 
                                        LIMIT 10
                                    ");
                                    $recentSearches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($recentSearches as $search):
                                ?>
                                <tr>
                                    <td><?= date('m/d H:i', strtotime($search['searched_at'])) ?></td>
                                    <td><?= htmlspecialchars($search['query']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $search['search_type'] === 'building' ? 'success' : ($search['search_type'] === 'architect' ? 'info' : ($search['search_type'] === 'prefecture' ? 'warning' : 'secondary')) ?>">
                                            <?= $search['search_type'] === 'building' ? '建築物' : ($search['search_type'] === 'architect' ? '建築家' : ($search['search_type'] === 'prefecture' ? '都道府県' : 'テキスト')) ?>
                                        </span>
                                    </td>
                                    <td><code><?= htmlspecialchars($search['ip_address']) ?></code></td>
                                </tr>
                                <?php 
                                    endforeach;
                                } catch (Exception $e) {
                                    echo '<tr><td colspan="4" class="text-center text-muted">データの取得に失敗しました</td></tr>';
                                }
                                ?>
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
    </script>
</body>
</html>

