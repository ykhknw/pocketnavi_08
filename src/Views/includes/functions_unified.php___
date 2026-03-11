<?php
// 統合された共通関数ファイル

/**
 * データベース接続を取得（統合版）
 */
function getDatabaseConnection() {
    try {
        $host = 'localhost';
        $dbname = '_shinkenchiku_db';
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new Exception("データベース接続エラーが発生しました。");
    }
}

/**
 * サムネイルURLを生成
 */
function generateThumbnailUrl($uid, $hasPhoto) {
    // has_photoがNULLまたは空の場合は空文字を返す
    if (empty($hasPhoto)) {
        return '';
    }
    
    // ディレクトリ指定形式: /pictures/{uid}/{has_photo}
    return '/pictures/' . urlencode($uid) . '/' . urlencode($hasPhoto);
}

/**
 * 建築物を検索する（統合版 - 新しいロジックをベースに）
 */
function searchBuildings($query, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
    // 検索ログを記録
    if (!empty($query)) {
        try {
            require_once __DIR__ . '/../../Services/SearchLogService.php';
            $searchLogService = new SearchLogService();
            $searchLogService->logSearch($query, 'text', [
                'hasPhotos' => $hasPhotos,
                'hasVideos' => $hasVideos,
                'lang' => $lang
            ]);
        } catch (Exception $e) {
            error_log("Search log error: " . $e->getMessage());
        }
    }
    
    $db = getDB();
    $offset = ($page - 1) * $limit;
    
    // テーブル名の定義
    $buildings_table = 'buildings_table_3';
    $building_architects_table = 'building_architects';
    $architect_compositions_table = 'architect_compositions_2';
    $individual_architects_table = 'individual_architects_3';
    
    // キーワードを分割（全角・半角スペースで分割）
    $temp = str_replace('　', ' ', $query);
    $keywords = array_filter(explode(' ', trim($temp)));
    
    // WHERE句の構築
    $whereClauses = [];
    $params = [];
    
    // 住宅のみのデータを除外（共通フィルター）
    $whereClauses[] = "(b.buildingTypes IS NULL OR b.buildingTypes = '' OR b.buildingTypes != '住宅')";
    
    // 横断検索の処理
    if (!empty($keywords)) {
        // 各キーワードに対してOR条件を構築し、全体をANDで結合
        $keywordConditions = [];
        foreach ($keywords as $keyword) {
            $escapedKeyword = '%' . $keyword . '%';
            $fieldConditions = [
                "b.title LIKE ?",
                "b.titleEn LIKE ?",
                "b.buildingTypes LIKE ?",
                "b.buildingTypesEn LIKE ?",
                "b.location LIKE ?",
                "b.locationEn_from_datasheetChunkEn LIKE ?",
                "ia.name_ja LIKE ?",
                "ia.name_en LIKE ?"
            ];
            $keywordConditions[] = '(' . implode(' OR ', $fieldConditions) . ')';
            
            // パラメータを8回追加（各フィールド用）
            for ($i = 0; $i < 8; $i++) {
                $params[] = $escapedKeyword;
            }
        }
        
        if (!empty($keywordConditions)) {
            $whereClauses[] = '(' . implode(' AND ', $keywordConditions) . ')';
        }
    }
    
    // メディアフィルター
    if ($hasPhotos) {
        $whereClauses[] = "b.has_photo IS NOT NULL AND b.has_photo != ''";
    }
    
    if ($hasVideos) {
        $whereClauses[] = "b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
    }
    
    // WHERE句の構築
    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }
    
    // カウントクエリ
    $countSql = "
        SELECT COUNT(DISTINCT b.building_id) as total
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
    ";
    
    // データ取得クエリ
    $sql = "
        SELECT b.building_id,
               b.uid,
               b.title,
               b.titleEn,
               b.slug,
               b.lat,
               b.lng,
               b.location,
               b.locationEn_from_datasheetChunkEn as locationEn,
               b.completionYears,
               b.buildingTypes,
               b.buildingTypesEn,
               b.prefectures,
               b.prefecturesEn,
               b.has_photo,
               b.thumbnailUrl,
               b.youtubeUrl,
               b.created_at,
               b.updated_at,
               0 as likes,
               GROUP_CONCAT(
                   DISTINCT ia.name_ja 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectJa,
               GROUP_CONCAT(
                   DISTINCT ia.name_en 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectEn,
               GROUP_CONCAT(
                   DISTINCT ba.architect_id 
                   ORDER BY ba.architect_order 
                   SEPARATOR ','
               ) AS architectIds,
               GROUP_CONCAT(
                   DISTINCT ia.slug 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ','
               ) AS architectSlugs
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
        GROUP BY b.building_id
        ORDER BY b.has_photo DESC, b.building_id DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    try {
        // カウント実行
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // データ取得実行
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        // データ変換
        $buildings = [];
        foreach ($rows as $row) {
            $buildings[] = transformBuildingData($row, $lang);
        }
        
        $totalPages = ceil($total / $limit);
        
        return [
            'buildings' => $buildings,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ];
        
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    }
}

/**
 * 現在地検索用の関数
 */
function searchBuildingsByLocation($userLat, $userLng, $radiusKm = 5, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
    $db = getDB();
    $offset = ($page - 1) * $limit;
    
    // テーブル名の定義
    $buildings_table = 'buildings_table_3';
    $building_architects_table = 'building_architects';
    $architect_compositions_table = 'architect_compositions_2';
    $individual_architects_table = 'individual_architects_3';
    
    // WHERE句の構築
    $whereClauses = [];
    $params = [];
    
    // 住宅のみのデータを除外（共通フィルター）
    $whereClauses[] = "(b.buildingTypes IS NULL OR b.buildingTypes = '' OR b.buildingTypes != '住宅')";
    
    // 位置情報による検索（Haversine公式を使用）
    $whereClauses[] = "(6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(b.lat)) * COS(RADIANS(b.lng) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(b.lat)))) < ?";
    $params[] = $userLat;
    $params[] = $userLng;
    $params[] = $userLat;
    $params[] = $radiusKm;
    
    // 座標が有効なデータのみ
    $whereClauses[] = "b.lat IS NOT NULL AND b.lng IS NOT NULL AND b.lat != 0 AND b.lng != 0";
    
    // locationカラムが空でないもののみ
    $whereClauses[] = "b.location IS NOT NULL AND b.location != ''";
    
    // 写真フィルター
    if ($hasPhotos) {
        $whereClauses[] = "b.has_photo IS NOT NULL AND b.has_photo != ''";
    }
    
    if ($hasVideos) {
        $whereClauses[] = "b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
    }
    
    // WHERE句の構築
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    
    // カウントクエリ
    $countSql = "
        SELECT COUNT(DISTINCT b.building_id) as total
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
    ";
    
    // データ取得クエリ
    $sql = "
        SELECT b.building_id,
               b.uid,
               b.title,
               b.titleEn,
               b.slug,
               b.lat,
               b.lng,
               b.location,
               b.locationEn_from_datasheetChunkEn as locationEn,
               b.completionYears,
               b.buildingTypes,
               b.buildingTypesEn,
               b.prefectures,
               b.prefecturesEn,
               b.has_photo,
               b.thumbnailUrl,
               b.youtubeUrl,
               b.created_at,
               b.updated_at,
               0 as likes,
               (6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(b.lat)) * COS(RADIANS(b.lng) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(b.lat)))) as distance,
               GROUP_CONCAT(
                   DISTINCT ia.name_ja 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectJa,
               GROUP_CONCAT(
                   DISTINCT ia.name_en 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectEn,
               GROUP_CONCAT(
                   DISTINCT ba.architect_id 
                   ORDER BY ba.architect_order 
                   SEPARATOR ','
               ) AS architectIds,
               GROUP_CONCAT(
                   DISTINCT ia.slug 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ','
               ) AS architectSlugs
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
        GROUP BY b.building_id
        ORDER BY distance ASC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    try {
        // カウント実行
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // データ取得実行（距離計算用のパラメータを追加）
        $distanceParams = array_merge($params, [$userLat, $userLng, $userLat]);
        $stmt = $db->prepare($sql);
        $stmt->execute($distanceParams);
        $rows = $stmt->fetchAll();
        
        // データ変換
        $buildings = [];
        foreach ($rows as $row) {
            $buildings[] = transformBuildingData($row, $lang);
        }
        
        $totalPages = ceil($total / $limit);
        
        return [
            'buildings' => $buildings,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ];
        
    } catch (Exception $e) {
        error_log("Location search error: " . $e->getMessage());
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    }
}

/**
 * 建築物データを変換（統合版）
 */
function transformBuildingData($row, $lang = 'ja') {
    // 建築家情報の処理
    $architects = [];
    if (!empty($row['architectJa'])) {
        $namesJa = explode(' / ', $row['architectJa']);
        $namesEn = !empty($row['architectEn']) ? explode(' / ', $row['architectEn']) : [];
        $architectIds = !empty($row['architectIds']) ? explode(',', $row['architectIds']) : [];
        $architectSlugs = !empty($row['architectSlugs']) ? explode(',', $row['architectSlugs']) : [];
        
        for ($i = 0; $i < count($namesJa); $i++) {
            $architects[] = [
                'architect_id' => isset($architectIds[$i]) ? intval($architectIds[$i]) : 0,
                'architectJa' => trim($namesJa[$i]),
                'architectEn' => isset($namesEn[$i]) ? trim($namesEn[$i]) : trim($namesJa[$i]),
                'slug' => isset($architectSlugs[$i]) ? trim($architectSlugs[$i]) : ''
            ];
        }
    }
    
    // 建物用途の配列変換
    $buildingTypes = !empty($row['buildingTypes']) ? 
        array_filter(explode('/', $row['buildingTypes']), function($type) {
            return !empty(trim($type));
        }) : [];
    
    $buildingTypesEn = !empty($row['buildingTypesEn']) ? 
        array_filter(explode('/', $row['buildingTypesEn']), function($type) {
            return !empty(trim($type));
        }) : [];
    
    // 英語データの処理
    $titleEn = $row['titleEn'] ?? $row['title'] ?? '';
    $locationEn = '';
    if (!empty($row['locationEn_from_datasheetChunkEn'])) {
        $locationEn = $row['locationEn_from_datasheetChunkEn'];
    } elseif (!empty($row['locationEn'])) {
        $locationEn = $row['locationEn'];
    } else {
        $locationEn = $row['location'] ?? '';
    }
    
    return [
        'building_id' => intval($row['building_id']),
        'uid' => $row['uid'] ?? '',
        'title' => $row['title'] ?? '',
        'titleEn' => $titleEn,
        'slug' => $row['slug'] ?? '',
        'lat' => floatval($row['lat'] ?? 0),
        'lng' => floatval($row['lng'] ?? 0),
        'location' => $row['location'] ?? '',
        'locationEn' => $locationEn,
        'completionYears' => intval($row['completionYears'] ?? 0),
        'buildingTypes' => $buildingTypes,
        'buildingTypesEn' => $buildingTypesEn,
        'prefectures' => $row['prefectures'] ?? '',
        'prefecturesEn' => $row['prefecturesEn'] ?? '',
        'has_photo' => $row['has_photo'] ?? '',
        'thumbnailUrl' => generateThumbnailUrl($row['uid'] ?? '', $row['has_photo'] ?? ''),
        'youtubeUrl' => $row['youtubeUrl'] ?? '',
        'architects' => $architects,
        // SEO用に元の文字列データも保持
        'architectJa' => $row['architectJa'] ?? '',
        'architectEn' => $row['architectEn'] ?? '',
        'likes' => intval($row['likes'] ?? 0),
        'distance' => isset($row['distance']) ? round(floatval($row['distance']), 2) : null,
        'created_at' => $row['created_at'] ?? '',
        'updated_at' => $row['updated_at'] ?? ''
    ];
}

/**
 * スラッグで建築物を取得
 */
function getBuildingBySlug($slug, $lang = 'ja') {
    $db = getDB();
    
    $buildings_table = 'buildings_table_3';
    $building_architects_table = 'building_architects';
    $architect_compositions_table = 'architect_compositions_2';
    $individual_architects_table = 'individual_architects_3';
    
    $sql = "
        SELECT b.building_id,
               b.uid,
               b.title,
               b.titleEn,
               b.slug,
               b.lat,
               b.lng,
               b.location,
               b.locationEn_from_datasheetChunkEn as locationEn,
               b.completionYears,
               b.buildingTypes,
               b.buildingTypesEn,
               b.prefectures,
               b.prefecturesEn,
               b.has_photo,
               b.thumbnailUrl,
               b.youtubeUrl,
               b.created_at,
               b.updated_at,
               0 as likes,
               GROUP_CONCAT(
                   DISTINCT ia.name_ja 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectJa,
               GROUP_CONCAT(
                   DISTINCT ia.name_en 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectEn,
               GROUP_CONCAT(
                   DISTINCT ba.architect_id 
                   ORDER BY ba.architect_order 
                   SEPARATOR ','
               ) AS architectIds,
               GROUP_CONCAT(
                   DISTINCT ia.slug 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ','
               ) AS architectSlugs
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        WHERE b.slug = :slug
        GROUP BY b.building_id
        LIMIT 1
    ";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        $row = $stmt->fetch();
        
        if ($row) {
            return transformBuildingData($row, $lang);
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Get building by slug error: " . $e->getMessage());
        return null;
    }
}

/**
 * スラッグで建築家を取得
 */
function getArchitectBySlug($slug, $lang = 'ja') {
    $db = getDB();
    
    $individual_architects_table = 'individual_architects_3';
    
    $sql = "
        SELECT individual_architect_id, name_ja, name_en, individual_website, website_title
        FROM $individual_architects_table 
        WHERE slug = :slug
        LIMIT 1
    ";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        $row = $stmt->fetch();
        
        if ($row) {
            return [
                'individual_architect_id' => intval($row['individual_architect_id']),
                'name_ja' => $row['name_ja'] ?? '',
                'name_en' => $row['name_en'] ?? '',
                'individual_website' => $row['individual_website'] ?? '',
                'website_title' => $row['website_title'] ?? ''
            ];
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Get architect by slug error: " . $e->getMessage());
        return null;
    }
}

/**
 * 人気検索を取得
 */
function getPopularSearches($lang = 'ja') {
    try {
        require_once __DIR__ . '/../../Services/SearchLogService.php';
        $searchLogService = new SearchLogService();
        $searches = $searchLogService->getPopularSearchesForSidebar(20);
        
        // 既存の形式に合わせて変換
        $result = [];
        foreach ($searches as $search) {
            $result[] = [
                'query' => $search['query'],
                'count' => $search['total_searches']
            ];
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Get popular searches error: " . $e->getMessage());
        // フォールバック: 固定データ
        return [
            ['query' => '安藤忠雄', 'count' => 45],
            ['query' => '美術館', 'count' => 38],
            ['query' => '東京', 'count' => 32],
            ['query' => '現代建築', 'count' => 28]
        ];
    }
}

/**
 * 翻訳関数
 */
function t($key, $lang = 'ja') {
    $translations = [
        'ja' => [
            'searchPlaceholder' => '建築物名、建築家、場所で検索...',
            'search' => '検索',
            'currentLocation' => '現在地を検索',
            'detailedSearch' => '詳細検索',
            'withPhotos' => '写真あり',
            'withVideos' => '動画あり',
            'clearFilters' => 'クリア',
            'loading' => '読み込み中...',
            'searchAround' => '周辺を検索',
            'getDirections' => '道順を取得',
            'viewOnGoogleMap' => 'Googleマップで表示',
            'buildingDetails' => '建築物詳細',
            'backToList' => '一覧に戻る',
            'architect' => '建築家',
            'location' => '所在地',
            'prefecture' => '都道府県',
            'buildingTypes' => '建物用途',
            'completionYear' => '完成年',
            'photos' => '写真',
            'videos' => '動画',
            'popularSearches' => '人気の検索',
            'noBuildingsFound' => '建築物が見つかりませんでした。',
            'loadingMap' => '地図を読み込み中...',
            'currentLocation' => '現在地を検索'
        ],
        'en' => [
            'searchPlaceholder' => 'Search by building name, architect, location...',
            'search' => 'Search',
            'currentLocation' => 'Search Current Location',
            'detailedSearch' => 'Detailed Search',
            'withPhotos' => 'With Photos',
            'withVideos' => 'With Videos',
            'clearFilters' => 'Clear',
            'loading' => 'Loading...',
            'searchAround' => 'Search Around',
            'getDirections' => 'Get Directions',
            'viewOnGoogleMap' => 'View on Google Maps',
            'buildingDetails' => 'Building Details',
            'backToList' => 'Back to List',
            'architect' => 'Architect',
            'location' => 'Location',
            'prefecture' => 'Prefecture',
            'buildingTypes' => 'Building Types',
            'completionYear' => 'Completion Year',
            'photos' => 'Photos',
            'videos' => 'Videos',
            'popularSearches' => 'Popular Searches',
            'noBuildingsFound' => 'No buildings found.',
            'loadingMap' => 'Loading map...',
            'currentLocation' => 'Search Current Location'
        ]
    ];
    
    return $translations[$lang][$key] ?? $key;
}

/**
 * デバッグ用：データベースの状況を確認
 */
function debugDatabase() {
    $db = getDB();
    
    try {
        // 建築物の総数
        $stmt = $db->query("SELECT COUNT(*) as total FROM buildings_table_3");
        $buildingCount = $stmt->fetch()['total'];
        
        // 座標がある建築物の数
        $stmt = $db->query("SELECT COUNT(*) as total FROM buildings_table_3 WHERE lat IS NOT NULL AND lng IS NOT NULL");
        $buildingWithCoords = $stmt->fetch()['total'];
        
        // 東京を含む建築物の数
        $stmt = $db->query("SELECT COUNT(*) as total FROM buildings_table_3 WHERE location LIKE '%東京%' OR prefectures LIKE '%東京%'");
        $tokyoBuildings = $stmt->fetch()['total'];
        
        // サンプルデータの確認
        $stmt = $db->query("SELECT building_id, title, location, prefectures, lat, lng FROM buildings_table_3 LIMIT 5");
        $sampleData = $stmt->fetchAll();
        
        return [
            'buildingCount' => $buildingCount,
            'buildingWithCoords' => $buildingWithCoords,
            'tokyoBuildings' => $tokyoBuildings,
            'sampleData' => $sampleData
        ];
        
    } catch (Exception $e) {
        error_log("Debug database error: " . $e->getMessage());
        return null;
    }
}
?>
