<?php
/**
 * ユーザーパターン分析ダッシュボード
 * 3. 検索行動パターンによるユーザー分類
 * 4. 地域別ユーザー分析
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
    // 3. 検索行動パターンによるユーザー分類
    // ユーザー別の検索タイプ分析
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(user_id, user_session_id) as user_identifier,
            COUNT(*) as total_searches,
            COUNT(DISTINCT search_type) as search_types_count,
            GROUP_CONCAT(DISTINCT search_type ORDER BY search_type) as search_types,
            COUNT(CASE WHEN search_type = 'architect' THEN 1 END) as architect_searches,
            COUNT(CASE WHEN search_type = 'building' THEN 1 END) as building_searches,
            COUNT(CASE WHEN search_type = 'prefecture' THEN 1 END) as prefecture_searches,
            COUNT(CASE WHEN search_type = 'text' THEN 1 END) as text_searches,
            MIN(searched_at) as first_search,
            MAX(searched_at) as last_search
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY COALESCE(user_id, user_session_id)
        HAVING total_searches >= 2
        ORDER BY total_searches DESC
    ");
    $stmt->execute([$days]);
    $userData = $stmt->fetchAll();
    
    // ユーザー分類の統計
    $userCategories = [
        'architect_specialist' => 0,      // 建築家専門（建築家検索が80%以上）
        'building_specialist' => 0,       // 建築物専門（建築物検索が80%以上）
        'prefecture_specialist' => 0,     // 都道府県専門（都道府県検索が80%以上）
        'text_specialist' => 0,           // テキスト専門（テキスト検索が80%以上）
        'multi_category' => 0,            // 複数カテゴリ（複数の検索タイプを使用）
        'balanced' => 0                   // バランス型（全検索タイプを均等に使用）
    ];
    
    $categoryDetails = [
        'architect_specialist' => [],
        'building_specialist' => [],
        'prefecture_specialist' => [],
        'text_specialist' => [],
        'multi_category' => [],
        'balanced' => []
    ];
    
    foreach ($userData as $user) {
        $total = $user['total_searches'];
        $architectRatio = $user['architect_searches'] / $total;
        $buildingRatio = $user['building_searches'] / $total;
        $prefectureRatio = $user['prefecture_searches'] / $total;
        $textRatio = $user['text_searches'] / $total;
        
        // ユーザー分類
        if ($architectRatio >= 0.8) {
            $userCategories['architect_specialist']++;
            $categoryDetails['architect_specialist'][] = $user;
        } elseif ($buildingRatio >= 0.8) {
            $userCategories['building_specialist']++;
            $categoryDetails['building_specialist'][] = $user;
        } elseif ($prefectureRatio >= 0.8) {
            $userCategories['prefecture_specialist']++;
            $categoryDetails['prefecture_specialist'][] = $user;
        } elseif ($textRatio >= 0.8) {
            $userCategories['text_specialist']++;
            $categoryDetails['text_specialist'][] = $user;
        } elseif ($user['search_types_count'] >= 3) {
            $userCategories['multi_category']++;
            $categoryDetails['multi_category'][] = $user;
        } else {
            $userCategories['balanced']++;
            $categoryDetails['balanced'][] = $user;
        }
    }
    
    $stats['user_categories'] = $userCategories;
    $stats['category_details'] = $categoryDetails;
    
    // 4. 地域別ユーザー分析
    // IPアドレスの最初のオクテットから地域を推定
    $stmt = $pdo->prepare("
        SELECT 
            SUBSTRING_INDEX(ip_address, '.', 1) as ip_class,
            COUNT(DISTINCT COALESCE(user_id, user_session_id)) as unique_users,
            COUNT(*) as total_searches,
            COUNT(DISTINCT search_type) as search_types_count,
            COUNT(CASE WHEN search_type = 'architect' THEN 1 END) as architect_searches,
            COUNT(CASE WHEN search_type = 'building' THEN 1 END) as building_searches,
            COUNT(CASE WHEN search_type = 'prefecture' THEN 1 END) as prefecture_searches,
            COUNT(CASE WHEN search_type = 'text' THEN 1 END) as text_searches
        FROM global_search_history 
        WHERE searched_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND ip_address IS NOT NULL
        AND ip_address != '::1'
        AND ip_address != '127.0.0.1'
        GROUP BY SUBSTRING_INDEX(ip_address, '.', 1)
        ORDER BY unique_users DESC
        LIMIT 20
    ");
    $stmt->execute([$days]);
    $regionalData = $stmt->fetchAll();
    
    // 地域名のマッピング（主要なIPクラス）
    $regionNames = [
        '1' => 'アメリカ（APNIC）',
        '2' => 'イギリス',
        '3' => 'アメリカ（GE）',
        '4' => 'アメリカ（BB）',
        '5' => 'イギリス',
        '6' => 'アメリカ（ARMY）',
        '7' => 'アメリカ（ARMY）',
        '8' => 'アメリカ（BB）',
        '9' => 'IBM',
        '10' => 'プライベート',
        '11' => 'アメリカ（DOD）',
        '12' => 'アメリカ（AT&T）',
        '13' => 'アメリカ（XEROX）',
        '14' => '日本（JPNIC）',
        '15' => 'アメリカ（HP）',
        '16' => 'アメリカ（DOD）',
        '17' => 'アメリカ（Apple）',
        '18' => 'アメリカ（MIT）',
        '19' => 'アメリカ（Ford）',
        '20' => 'アメリカ（Computer Sciences）',
        '21' => 'アメリカ（DOD）',
        '22' => 'アメリカ（DOD）',
        '23' => 'アメリカ（DOD）',
        '24' => 'アメリカ（Cable & Wireless）',
        '25' => 'イギリス（MOD）',
        '26' => 'アメリカ（DOD）',
        '27' => 'オーストラリア',
        '28' => 'アメリカ（DOD）',
        '29' => 'アメリカ（DOD）',
        '30' => 'アメリカ（DOD）',
        '31' => 'イギリス（MOD）',
        '32' => 'アメリカ（Norsk Informasjonsteknologi）',
        '33' => 'アメリカ（DOD）',
        '34' => 'アメリカ（Halliburton）',
               '35' => 'アメリカ（Merit）',
        '36' => 'アメリカ（Stanford）',
        '37' => 'アメリカ（DOD）',
        '38' => 'アメリカ（Performance Systems）',
        '39' => '日本（JPNIC）',
        '40' => 'アメリカ（Eli Lilly）',
        '41' => 'アフリカ（AfriNIC）',
        '42' => 'アジア太平洋（APNIC）',
        '43' => 'アジア太平洋（APNIC）',
        '44' => 'アマゾン',
        '45' => 'アメリカ（DOD）',
        '46' => 'ロシア（RIPE NCC）',
        '47' => 'アメリカ（Bell-North）',
        '48' => 'アメリカ（Prudential）',
        '49' => 'アジア太平洋（APNIC）',
        '50' => 'アメリカ（DOD）',
        '51' => 'イギリス（Government）',
        '52' => 'アメリカ（DuPont）',
        '53' => 'アメリカ（Cap Debis）',
        '54' => 'アメリカ（Merck）',
        '55' => 'アメリカ（DOD）',
        '56' => 'アメリカ（US Postal Service）',
        '57' => 'アメリカ（SITA）',
        '58' => 'アジア太平洋（APNIC）',
        '59' => 'アジア太平洋（APNIC）',
        '60' => 'アジア太平洋（APNIC）',
        '61' => 'アジア太平洋（APNIC）',
        '62' => 'ヨーロッパ（RIPE NCC）',
        '63' => 'アメリカ（DOD）',
        '64' => 'アメリカ（DOD）',
        '65' => 'アメリカ（DOD）',
        '66' => 'アメリカ（DOD）',
        '67' => 'アメリカ（DOD）',
        '68' => 'アメリカ（DOD）',
        '69' => 'アメリカ（DOD）',
        '70' => 'アメリカ（DOD）',
        '71' => 'アメリカ（DOD）',
        '72' => 'アメリカ（DOD）',
        '73' => 'アメリカ（DOD）',
        '74' => 'アメリカ（DOD）',
        '75' => 'アメリカ（DOD）',
        '76' => 'アメリカ（DOD）',
        '77' => 'ヨーロッパ（RIPE NCC）',
        '78' => 'ヨーロッパ（RIPE NCC）',
        '79' => 'ヨーロッパ（RIPE NCC）',
        '80' => 'ヨーロッパ（RIPE NCC）',
        '81' => 'ヨーロッパ（RIPE NCC）',
        '82' => 'ヨーロッパ（RIPE NCC）',
        '83' => 'ヨーロッパ（RIPE NCC）',
        '84' => 'ヨーロッパ（RIPE NCC）',
        '85' => 'ヨーロッパ（RIPE NCC）',
        '86' => 'ヨーロッパ（RIPE NCC）',
        '87' => 'ヨーロッパ（RIPE NCC）',
        '88' => 'ヨーロッパ（RIPE NCC）',
        '89' => 'ヨーロッパ（RIPE NCC）',
        '90' => 'ヨーロッパ（RIPE NCC）',
        '91' => 'ヨーロッパ（RIPE NCC）',
        '92' => 'ヨーロッパ（RIPE NCC）',
        '93' => 'ヨーロッパ（RIPE NCC）',
        '94' => 'ヨーロッパ（RIPE NCC）',
        '95' => 'ヨーロッパ（RIPE NCC）',
        '96' => 'ヨーロッパ（RIPE NCC）',
        '97' => 'ヨーロッパ（RIPE NCC）',
        '98' => 'ヨーロッパ（RIPE NCC）',
        '99' => 'ヨーロッパ（RIPE NCC）',
        '100' => 'アメリカ（DOD）',
        '101' => 'アジア太平洋（APNIC）',
        '102' => 'アフリカ（AfriNIC）',
        '103' => 'アジア太平洋（APNIC）',
        '104' => 'アメリカ（DOD）',
        '105' => 'アメリカ（DOD）',
        '106' => 'アジア太平洋（APNIC）',
        '107' => 'アメリカ（DOD）',
        '108' => 'アメリカ（DOD）',
        '109' => 'ヨーロッパ（RIPE NCC）',
        '110' => 'アジア太平洋（APNIC）',
        '111' => 'アジア太平洋（APNIC）',
        '112' => 'アジア太平洋（APNIC）',
        '113' => 'アジア太平洋（APNIC）',
        '114' => 'アジア太平洋（APNIC）',
        '115' => 'アジア太平洋（APNIC）',
        '116' => 'アジア太平洋（APNIC）',
        '117' => 'アジア太平洋（APNIC）',
        '118' => 'アジア太平洋（APNIC）',
        '119' => 'アジア太平洋（APNIC）',
        '120' => 'アジア太平洋（APNIC）',
        '121' => 'アジア太平洋（APNIC）',
        '122' => 'アジア太平洋（APNIC）',
        '123' => 'アジア太平洋（APNIC）',
        '124' => 'アジア太平洋（APNIC）',
        '125' => 'アジア太平洋（APNIC）',
        '126' => 'アジア太平洋（APNIC）',
        '127' => 'ローカルホスト',
        '128' => 'アメリカ（DOD）',
        '129' => 'アメリカ（DOD）',
        '130' => 'アメリカ（DOD）',
        '131' => 'アメリカ（DOD）',
        '132' => 'アメリカ（DOD）',
        '133' => '日本（JPNIC）',
        '134' => 'アメリカ（DOD）',
        '135' => 'アメリカ（DOD）',
        '136' => 'アメリカ（DOD）',
        '137' => 'アメリカ（DOD）',
        '138' => 'アメリカ（DOD）',
        '139' => 'アメリカ（DOD）',
        '140' => 'アメリカ（DOD）',
        '141' => 'ヨーロッパ（RIPE NCC）',
        '142' => 'カナダ',
        '143' => 'アメリカ（DOD）',
        '144' => 'アメリカ（DOD）',
        '145' => 'ヨーロッパ（RIPE NCC）',
        '146' => 'アメリカ（DOD）',
        '147' => 'アメリカ（DOD）',
        '148' => 'アメリカ（DOD）',
        '149' => 'アメリカ（DOD）',
        '150' => 'アメリカ（DOD）',
        '151' => 'ヨーロッパ（RIPE NCC）',
        '152' => 'アメリカ（DOD）',
        '153' => 'アジア太平洋（APNIC）',
        '154' => 'アフリカ（AfriNIC）',
        '155' => 'アメリカ（DOD）',
        '156' => 'アメリカ（DOD）',
        '157' => 'アメリカ（DOD）',
        '158' => 'アメリカ（DOD）',
        '159' => 'アメリカ（DOD）',
        '160' => 'アメリカ（DOD）',
        '161' => 'アメリカ（DOD）',
        '162' => 'アメリカ（DOD）',
        '163' => 'アメリカ（DOD）',
        '164' => 'アメリカ（DOD）',
        '165' => 'アメリカ（DOD）',
        '166' => 'アメリカ（DOD）',
        '167' => 'アメリカ（DOD）',
        '168' => 'アメリカ（DOD）',
        '169' => 'アメリカ（DOD）',
        '170' => 'アメリカ（DOD）',
        '171' => 'アメリカ（DOD）',
        '172' => 'プライベート',
        '173' => 'プライベート',
        '174' => 'アメリカ（DOD）',
        '175' => 'アジア太平洋（APNIC）',
        '176' => 'ヨーロッパ（RIPE NCC）',
        '177' => 'ラテンアメリカ（LACNIC）',
        '178' => 'ヨーロッパ（RIPE NCC）',
        '179' => 'ラテンアメリカ（LACNIC）',
        '180' => 'ラテンアメリカ（LACNIC）',
        '181' => 'ラテンアメリカ（LACNIC）',
        '182' => 'アジア太平洋（APNIC）',
        '183' => 'アジア太平洋（APNIC）',
        '184' => 'アメリカ（DOD）',
        '185' => 'ヨーロッパ（RIPE NCC）',
        '186' => 'ラテンアメリカ（LACNIC）',
        '187' => 'ラテンアメリカ（LACNIC）',
        '188' => 'ヨーロッパ（RIPE NCC）',
        '189' => 'ラテンアメリカ（LACNIC）',
        '190' => 'ラテンアメリカ（LACNIC）',
        '191' => 'ラテンアメリカ（LACNIC）',
        '192' => 'プライベート',
        '193' => 'ヨーロッパ（RIPE NCC）',
        '194' => 'ヨーロッパ（RIPE NCC）',
        '195' => 'ヨーロッパ（RIPE NCC）',
        '196' => 'アフリカ（AfriNIC）',
        '197' => 'アフリカ（AfriNIC）',
        '198' => 'アメリカ（DOD）',
        '199' => 'アメリカ（DOD）',
        '200' => 'ラテンアメリカ（LACNIC）',
        '201' => 'ラテンアメリカ（LACNIC）',
        '202' => 'アジア太平洋（APNIC）',
        '203' => 'アジア太平洋（APNIC）',
        '204' => 'アメリカ（DOD）',
        '205' => 'アメリカ（DOD）',
        '206' => 'アメリカ（DOD）',
        '207' => 'アメリカ（DOD）',
        '208' => 'アメリカ（DOD）',
        '209' => 'アメリカ（DOD）',
        '210' => 'アジア太平洋（APNIC）',
        '211' => 'アジア太平洋（APNIC）',
        '212' => 'ヨーロッパ（RIPE NCC）',
        '213' => 'ヨーロッパ（RIPE NCC）',
        '214' => 'アメリカ（DOD）',
        '215' => 'アメリカ（DOD）',
        '216' => 'アメリカ（DOD）',
        '217' => 'ヨーロッパ（RIPE NCC）',
        '218' => 'アジア太平洋（APNIC）',
        '219' => 'アジア太平洋（APNIC）',
        '220' => 'アジア太平洋（APNIC）',
        '221' => 'アジア太平洋（APNIC）',
        '222' => 'アジア太平洋（APNIC）',
        '223' => 'アジア太平洋（APNIC）',
        '224' => 'アジア太平洋（APNIC）',
        '225' => 'アジア太平洋（APNIC）',
        '226' => 'アメリカ（DOD）',
        '227' => 'アメリカ（DOD）',
        '228' => 'アメリカ（DOD）',
        '229' => 'アメリカ（DOD）',
        '230' => 'アメリカ（DOD）',
        '231' => 'アメリカ（DOD）',
        '232' => 'アメリカ（DOD）',
        '233' => 'アメリカ（DOD）',
        '234' => 'アメリカ（DOD）',
        '235' => 'アメリカ（DOD）',
        '236' => 'アメリカ（DOD）',
        '237' => 'アメリカ（DOD）',
        '238' => 'アメリカ（DOD）',
        '239' => 'アメリカ（DOD）',
        '240' => 'アメリカ（DOD）',
        '241' => 'アフリカ（AfriNIC）',
        '242' => 'アフリカ（AfriNIC）',
        '243' => 'アフリカ（AfriNIC）',
        '244' => 'アフリカ（AfriNIC）',
        '245' => 'アフリカ（AfriNIC）',
        '246' => 'アフリカ（AfriNIC）',
        '247' => 'アフリカ（AfriNIC）',
        '248' => 'アフリカ（AfriNIC）',
        '249' => 'アフリカ（AfriNIC）',
        '250' => 'アフリカ（AfriNIC）',
        '251' => 'アフリカ（AfriNIC）',
        '252' => 'アメリカ（DOD）',
        '253' => 'アメリカ（DOD）',
        '254' => 'アメリカ（DOD）',
        '255' => 'アメリカ（DOD）'
    ];
    
    // 地域データに地域名を追加
    foreach ($regionalData as &$region) {
        $ipClass = $region['ip_class'];
        $region['region_name'] = $regionNames[$ipClass] ?? "IPクラス {$ipClass}";
        
        // 検索タイプ別の割合を計算
        $total = $region['total_searches'];
        if ($total > 0) {
            $region['architect_ratio'] = round(($region['architect_searches'] / $total) * 100, 1);
            $region['building_ratio'] = round(($region['building_searches'] / $total) * 100, 1);
            $region['prefecture_ratio'] = round(($region['prefecture_searches'] / $total) * 100, 1);
            $region['text_ratio'] = round(($region['text_searches'] / $total) * 100, 1);
        }
    }
    
    $stats['regional_data'] = $regionalData;
    
    // 地域別の検索傾向分析
    $regionalTrends = [
        'architect_focused' => [],    // 建築家検索が多い地域
        'building_focused' => [],     // 建築物検索が多い地域
        'prefecture_focused' => [],   // 都道府県検索が多い地域
        'text_focused' => []          // テキスト検索が多い地域
    ];
    
    foreach ($regionalData as $region) {
        if ($region['architect_ratio'] >= 40) {
            $regionalTrends['architect_focused'][] = $region;
        }
        if ($region['building_ratio'] >= 40) {
            $regionalTrends['building_focused'][] = $region;
        }
        if ($region['prefecture_ratio'] >= 40) {
            $regionalTrends['prefecture_focused'][] = $region;
        }
        if ($region['text_ratio'] >= 40) {
            $regionalTrends['text_focused'][] = $region;
        }
    }
    
    $stats['regional_trends'] = $regionalTrends;
    
} catch (PDOException $e) {
    $error = "データ取得エラー: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザーパターン分析 - PocketNavi管理画面</title>
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
                <h1>ユーザーパターン分析ダッシュボード</h1>
                <p class="text-muted">検索行動パターンによるユーザー分類と地域別ユーザー分析</p>
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

        <!-- ユーザー分類サマリー -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">ユーザー分類サマリー</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-primary"><?php echo $stats['user_categories']['architect_specialist']; ?></h4>
                                    <small>建築家専門</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-success"><?php echo $stats['user_categories']['building_specialist']; ?></h4>
                                    <small>建築物専門</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-info"><?php echo $stats['user_categories']['prefecture_specialist']; ?></h4>
                                    <small>都道府県専門</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-warning"><?php echo $stats['user_categories']['text_specialist']; ?></h4>
                                    <small>テキスト専門</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-danger"><?php echo $stats['user_categories']['multi_category']; ?></h4>
                                    <small>複数カテゴリ</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <h4 class="text-secondary"><?php echo $stats['user_categories']['balanced']; ?></h4>
                                    <small>バランス型</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ユーザー分類グラフ -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">ユーザー分類分布</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="userCategoryChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">地域別ユーザー数 TOP10</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="regionalUsersChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 地域別検索傾向 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">地域別検索傾向分析</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5>建築家重視地域</h5>
                                        <h3><?php echo count($stats['regional_trends']['architect_focused']); ?></h3>
                                        <small>建築家検索40%以上</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5>建築物重視地域</h5>
                                        <h3><?php echo count($stats['regional_trends']['building_focused']); ?></h3>
                                        <small>建築物検索40%以上</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h5>都道府県重視地域</h5>
                                        <h3><?php echo count($stats['regional_trends']['prefecture_focused']); ?></h3>
                                        <small>都道府県検索40%以上</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h5>テキスト重視地域</h5>
                                        <h3><?php echo count($stats['regional_trends']['text_focused']); ?></h3>
                                        <small>テキスト検索40%以上</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 地域別詳細データ -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">地域別詳細データ</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>地域</th>
                                        <th>ユーザー数</th>
                                        <th>総検索数</th>
                                        <th>建築家</th>
                                        <th>建築物</th>
                                        <th>都道府県</th>
                                        <th>テキスト</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($stats['regional_data'], 0, 15) as $region): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($region['region_name']); ?></td>
                                        <td><?php echo number_format($region['unique_users']); ?></td>
                                        <td><?php echo number_format($region['total_searches']); ?></td>
                                        <td><?php echo $region['architect_ratio']; ?>%</td>
                                        <td><?php echo $region['building_ratio']; ?>%</td>
                                        <td><?php echo $region['prefecture_ratio']; ?>%</td>
                                        <td><?php echo $region['text_ratio']; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ユーザー分類詳細 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">ユーザー分類詳細（上位ユーザー）</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="userCategoryAccordion">
                            <?php 
                            $categoryNames = [
                                'architect_specialist' => '建築家専門ユーザー',
                                'building_specialist' => '建築物専門ユーザー',
                                'prefecture_specialist' => '都道府県専門ユーザー',
                                'text_specialist' => 'テキスト専門ユーザー',
                                'multi_category' => '複数カテゴリユーザー',
                                'balanced' => 'バランス型ユーザー'
                            ];
                            
                            foreach ($categoryNames as $categoryKey => $categoryName): 
                                if (!empty($stats['category_details'][$categoryKey])):
                            ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $categoryKey; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $categoryKey; ?>">
                                        <?php echo $categoryName; ?> (<?php echo count($stats['category_details'][$categoryKey]); ?>人)
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $categoryKey; ?>" class="accordion-collapse collapse" data-bs-parent="#userCategoryAccordion">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>検索数</th>
                                                        <th>検索タイプ</th>
                                                        <th>建築家</th>
                                                        <th>建築物</th>
                                                        <th>都道府県</th>
                                                        <th>テキスト</th>
                                                        <th>初回検索</th>
                                                        <th>最終検索</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($stats['category_details'][$categoryKey], 0, 10) as $user): ?>
                                                    <tr>
                                                        <td><?php echo $user['total_searches']; ?></td>
                                                        <td><?php echo $user['search_types_count']; ?></td>
                                                        <td><?php echo $user['architect_searches']; ?></td>
                                                        <td><?php echo $user['building_searches']; ?></td>
                                                        <td><?php echo $user['prefecture_searches']; ?></td>
                                                        <td><?php echo $user['text_searches']; ?></td>
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
                            <?php 
                                endif;
                            endforeach; 
                            ?>
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
                            <a href="analytics_dashboard.php" class="btn btn-outline-secondary">詳細解析</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ユーザー分類分布グラフ
        const userCategoryData = <?php echo json_encode($stats['user_categories']); ?>;
        const userCategoryCtx = document.getElementById('userCategoryChart').getContext('2d');
        new Chart(userCategoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['建築家専門', '建築物専門', '都道府県専門', 'テキスト専門', '複数カテゴリ', 'バランス型'],
                datasets: [{
                    data: [
                        userCategoryData.architect_specialist,
                        userCategoryData.building_specialist,
                        userCategoryData.prefecture_specialist,
                        userCategoryData.text_specialist,
                        userCategoryData.multi_category,
                        userCategoryData.balanced
                    ],
                    backgroundColor: [
                        'rgb(54, 162, 235)',
                        'rgb(75, 192, 192)',
                        'rgb(255, 205, 86)',
                        'rgb(255, 99, 132)',
                        'rgb(153, 102, 255)',
                        'rgb(201, 203, 207)'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });

        // 地域別ユーザー数グラフ
        const regionalData = <?php echo json_encode(array_slice($stats['regional_data'], 0, 10)); ?>;
        const regionalLabels = regionalData.map(item => item.region_name.length > 20 ? item.region_name.substring(0, 20) + '...' : item.region_name);
        const regionalUsers = regionalData.map(item => item.unique_users);

        const regionalUsersCtx = document.getElementById('regionalUsersChart').getContext('2d');
        new Chart(regionalUsersCtx, {
            type: 'bar',
            data: {
                labels: regionalLabels,
                datasets: [{
                    label: 'ユーザー数',
                    data: regionalUsers,
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
    </script>
</body>
</html>
