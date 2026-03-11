<?php

// 必要なファイルを読み込み
//require_once __DIR__ . '/../Utils/Database.php';
require_once __DIR__ . '/../Utils/Config.php';

/**
 * 建築物検索サービス
 */
class BuildingService {
    private $db;
    private $buildings_table = 'buildings_table_4';
    private $building_architects_table = 'building_architects';
    private $architect_compositions_table = 'architect_compositions_2';
    private $individual_architects_table = 'individual_architects_3';
    
    public function __construct() {
        // データベース接続を取得
        try {
            require_once __DIR__ . '/../Utils/DatabaseConnection.php';
            $dbConnection = DatabaseConnection::getInstance();
            $this->db = $dbConnection->getConnection();
            
            if ($this->db === null) {
                throw new Exception("Database connection failed");
            }
        } catch (Exception $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * 建築物を検索する
     */
    public function search($query, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
        // キーワードを分割（全角・半角スペースで分割）
        $keywords = $this->parseKeywords($query);
        
        // WHERE句の構築
        $whereClauses = [];
        $params = [];
        
        // 共通フィルターの追加（住宅のみのデータを除外）
        $this->addCommonFilters($whereClauses);
        
        // キーワード検索条件の追加
        $this->addKeywordConditions($whereClauses, $params, $keywords);
        
        // メディアフィルターの追加
        $this->addMediaFilters($whereClauses, $hasPhotos, $hasVideos);
        
        return $this->executeSearch($whereClauses, $params, $page, $lang, $limit);
    }
    
    /**
     * 複数条件での建築物検索
     */
    public function searchWithMultipleConditions($query, $completionYears, $prefectures, $buildingTypes, $hasPhotos, $hasVideos, $page = 1, $lang = 'ja', $limit = 10) {
        // 検索ログを記録
        if (!empty($query)) {
            try {
                require_once __DIR__ . '/SearchLogService.php';
                $searchLogService = new SearchLogService();
                
                // 検索語が建築物名かどうかを判定
                $searchType = $this->determineSearchType($query);
                
                $searchLogService->logSearch($query, $searchType, [
                    'hasPhotos' => $hasPhotos,
                    'hasVideos' => $hasVideos,
                    'lang' => $lang,
                    'completionYears' => $completionYears,
                    'prefectures' => $prefectures,
                    'buildingTypes' => $buildingTypes
                ]);
            } catch (Exception $e) {
                error_log("Search log error: " . $e->getMessage());
            }
        }
        
        // WHERE句の構築
        $whereClauses = [];
        $params = [];
        
        // 共通フィルターの追加（住宅のみのデータを除外）
        $this->addCommonFilters($whereClauses);
        
        // キーワード検索条件の追加
        $keywords = $this->parseKeywords($query);
        $this->addKeywordConditions($whereClauses, $params, $keywords);
        
        // 完成年条件の追加
        $this->addCompletionYearConditions($whereClauses, $params, $completionYears);
        
        // 都道府県条件の追加
        $this->addPrefectureConditions($whereClauses, $params, $prefectures);
        
        // 建築種別条件の追加
        $this->addBuildingTypeConditions($whereClauses, $params, $buildingTypes);
        
        // メディアフィルターの追加
        $this->addMediaFilters($whereClauses, $hasPhotos, $hasVideos);
        
        return $this->executeSearch($whereClauses, $params, $page, $lang, $limit);
    }
    
    /**
     * 検索語のタイプを判定
     */
    private function determineSearchType($query) {
        try {
            // 建築物名として検索してみる
            $sql = "
                SELECT COUNT(*) as count
                FROM {$this->buildings_table}
                WHERE title = ? OR titleEn = ? OR slug = ?
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$query, $query, $query]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                return 'building';
            }
            
            // 建築家名として検索してみる
            $sql = "
                SELECT COUNT(*) as count
                FROM {$this->individual_architects_table}
                WHERE name_ja = ? OR name_en = ? OR slug = ?
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$query, $query, $query]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                return 'architect';
            }
            
            // 都道府県名として検索してみる
            $sql = "
                SELECT COUNT(*) as count
                FROM {$this->buildings_table}
                WHERE prefectures = ? OR prefecturesEn = ?
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$query, $query]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                return 'prefecture';
            }
            
            // どれにも該当しない場合はテキスト検索
            return 'text';
            
        } catch (Exception $e) {
            error_log("Determine search type error: " . $e->getMessage());
            return 'text';
        }
    }
    
    /**
     * 位置情報による建築物検索
     */
    public function searchByLocation($userLat, $userLng, $radiusKm = 5, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
        // WHERE句の構築
        $whereClauses = [];
        $params = [];
        
        // 共通フィルターの追加（住宅のみのデータを除外）
        $this->addCommonFilters($whereClauses);
        
        // 位置情報条件の追加
        $this->addLocationConditions($whereClauses, $params, $userLat, $userLng, $radiusKm);
        
        // メディアフィルターの追加
        $this->addMediaFilters($whereClauses, $hasPhotos, $hasVideos);
        
        return $this->executeLocationSearch($whereClauses, $params, $userLat, $userLng, $page, $lang, $limit);
    }
    
    /**
     * 建築家による建築物検索
     */
    public function searchByArchitectSlug($architectSlug, $page = 1, $lang = 'ja', $limit = 10) {
        // 建築家検索の場合は、特定の建築家でフィルタリングされた建築物を取得し、
        // 各建築物の建築家情報は、その建築物に紐付くすべての建築家を取得する
        return $this->executeArchitectSearch($architectSlug, $page, $lang, $limit);
    }
    
    /**
     * スラッグで建築物を取得
     */
    public function getBySlug($slug, $lang = 'ja') {
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
                   b.building_column_text,
                   b.column_title,
                   b.building_column_textEn,
                   b.column_titleEn,
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
                   ) AS architectSlugs,
                   GROUP_CONCAT(
                       CONCAT(ba.architect_order, '_', ac.order_index, '_', ia.individual_architect_id)
                       ORDER BY ba.architect_order, ac.order_index 
                       SEPARATOR ','
                   ) AS individual_architect_ids,
                   GROUP_CONCAT(
                       CONCAT(ba.architect_order, '_', ac.order_index, '_', IFNULL(ia.individual_website, ''))
                       ORDER BY ba.architect_order, ac.order_index 
                       SEPARATOR '|||'
                   ) AS individual_websites,
                   GROUP_CONCAT(
                       CONCAT(ba.architect_order, '_', ac.order_index, '_', IFNULL(ia.website_title, ''))
                       ORDER BY ba.architect_order, ac.order_index 
                       SEPARATOR '|||'
                   ) AS website_titles
            FROM {$this->buildings_table} b
            LEFT JOIN {$this->building_architects_table} ba ON b.building_id = ba.building_id
            LEFT JOIN {$this->architect_compositions_table} ac ON ba.architect_id = ac.architect_id
            LEFT JOIN {$this->individual_architects_table} ia ON ac.individual_architect_id = ia.individual_architect_id
            WHERE b.slug = ?
            GROUP BY b.building_id
        ";
        
        try {
            // デバッグ: データベース接続情報を確認
            $dbName = $this->db->query("SELECT DATABASE()")->fetchColumn();
            error_log("BuildingService::getBySlug - Connected to database: " . $dbName);
            error_log("BuildingService::getBySlug - slug: " . $slug);
            error_log("BuildingService::getBySlug - SQL: " . substr($sql, 0, 200) . "...");
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            
            if ($row) {
                error_log("BuildingService::getBySlug - Building found: " . ($row['title'] ?? 'no title'));
                error_log("BuildingService::getBySlug - Building ID: " . ($row['building_id'] ?? 'no id'));
                return transformBuildingData($row, $lang);
            }
            
            // デバッグ: slugで見つからない場合、uidでも試す
            error_log("BuildingService::getBySlug - No building found for slug: " . $slug);
            error_log("BuildingService::getBySlug - Trying to find by slug or uid in database...");
            $testStmt = $this->db->prepare("SELECT slug, uid, title FROM {$this->buildings_table} WHERE slug = ? OR uid = ? LIMIT 5");
            $testStmt->execute([$slug, $slug]);
            $testRows = $testStmt->fetchAll();
            error_log("BuildingService::getBySlug - Found " . count($testRows) . " rows with slug or uid matching: " . $slug);
            foreach ($testRows as $testRow) {
                error_log("BuildingService::getBySlug - Test row - slug: " . ($testRow['slug'] ?? 'null') . ", uid: " . ($testRow['uid'] ?? 'null') . ", title: " . ($testRow['title'] ?? 'null'));
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Get building by slug error: " . $e->getMessage());
            error_log("Get building by slug error trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    // プライベートメソッド群
    
    /**
     * 共通の検索実行ロジック
     */
    private function executeSearch($whereClauses, $params, $page, $lang, $limit) {
        // WHERE句の構築
        $whereSql = $this->buildWhereClause($whereClauses);
        
        // カウントクエリ
        $countSql = $this->buildCountQuery($whereSql);
        
        try {
            // カウント実行
            $total = $this->executeCountQuery($countSql, $params);
            
            $totalPages = ceil($total / $limit);
            
            // ページ番号の検証と調整
            if ($page > $totalPages && $totalPages > 0) {
                $page = $totalPages; // 最終ページに調整
            } elseif ($page < 1) {
                $page = 1; // 1ページ目に調整
            }
            
            $offset = ($page - 1) * $limit;
            
            // データ取得クエリ
            $sql = $this->buildSearchQuery($whereSql, $limit, $offset);
            
            // データ取得実行
            $rows = $this->executeSearchQuery($sql, $params);
            
            // データ変換
            $buildings = $this->transformBuildingData($rows, $lang);
            
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
     * 建築家検索の専用実行メソッド
     */
    private function executeArchitectSearch($architectSlug, $page, $lang, $limit) {
        // 建築家条件を構築（slug_groupを考慮）
        $whereClauses = [];
        $params = [];
        $this->addArchitectConditions($whereClauses, $params, $architectSlug);
        
        // WHERE句の構築
        $whereSql = implode(' AND ', $whereClauses);
        
        // カウントクエリ（特定の建築家でフィルタリング）
        $countSql = "
            SELECT COUNT(DISTINCT b.building_id) as total
            FROM {$this->buildings_table} b
            LEFT JOIN {$this->building_architects_table} ba ON b.building_id = ba.building_id
            LEFT JOIN {$this->architect_compositions_table} ac ON ba.architect_id = ac.architect_id
            LEFT JOIN {$this->individual_architects_table} ia ON ac.individual_architect_id = ia.individual_architect_id
            WHERE {$whereSql}
        ";
        
        try {
            // デバッグログ（開発環境時のみ、本番環境では出力しない）
            if (!Config::isProduction()) {
                error_log("Architect search count query: " . $countSql);
                error_log("Architect search count params: " . json_encode($params));
            }
            
            // カウント実行
            $total = $this->executeCountQuery($countSql, $params);
            
            $totalPages = ceil($total / $limit);
            
            // ページ番号の検証と調整
            if ($page > $totalPages && $totalPages > 0) {
                $page = $totalPages; // 最終ページに調整
            } elseif ($page < 1) {
                $page = 1; // 1ページ目に調整
            }
            
            $offset = ($page - 1) * $limit;
            
            // データ取得クエリ（特定の建築家でフィルタリングされた建築物を取得し、
            // 各建築物の建築家情報は、その建築物に紐付くすべての建築家を取得）
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
                           DISTINCT ia2.name_ja 
                           ORDER BY ba2.architect_order, ac2.order_index 
                           SEPARATOR ' / '
                       ) AS architectJa,
                       GROUP_CONCAT(
                           DISTINCT ia2.name_en 
                           ORDER BY ba2.architect_order, ac2.order_index 
                           SEPARATOR ' / '
                       ) AS architectEn,
                       GROUP_CONCAT(
                           DISTINCT ba2.architect_id 
                           ORDER BY ba2.architect_order 
                           SEPARATOR ','
                       ) AS architectIds,
                       GROUP_CONCAT(
                           DISTINCT ia2.slug 
                           ORDER BY ba2.architect_order, ac2.order_index 
                           SEPARATOR ','
                       ) AS architectSlugs
                FROM {$this->buildings_table} b
                LEFT JOIN {$this->building_architects_table} ba ON b.building_id = ba.building_id
                LEFT JOIN {$this->architect_compositions_table} ac ON ba.architect_id = ac.architect_id
                LEFT JOIN {$this->individual_architects_table} ia ON ac.individual_architect_id = ia.individual_architect_id
                LEFT JOIN {$this->building_architects_table} ba2 ON b.building_id = ba2.building_id
                LEFT JOIN {$this->architect_compositions_table} ac2 ON ba2.architect_id = ac2.architect_id
                LEFT JOIN {$this->individual_architects_table} ia2 ON ac2.individual_architect_id = ia2.individual_architect_id
                WHERE {$whereSql}
                GROUP BY b.building_id, b.uid, b.title, b.titleEn, b.slug, b.lat, b.lng, b.location, b.locationEn_from_datasheetChunkEn, b.completionYears, b.buildingTypes, b.buildingTypesEn, b.prefectures, b.prefecturesEn, b.has_photo, b.thumbnailUrl, b.youtubeUrl, b.created_at, b.updated_at
                ORDER BY b.has_photo DESC, b.building_id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
            
            // デバッグログ（開発環境時のみ、本番環境では出力しない）
            if (!Config::isProduction()) {
                error_log("Architect search data query: " . $sql);
                error_log("Architect search data params: " . json_encode($params));
            }
            
            // データ取得実行
            $rows = $this->executeSearchQuery($sql, $params);
            
            // データ変換
            $buildings = $this->transformBuildingData($rows, $lang);
            
            return [
                'buildings' => $buildings,
                'total' => $total,
                'totalPages' => $totalPages,
                'currentPage' => $page
            ];
            
        } catch (Exception $e) {
            error_log("Architect search error: " . $e->getMessage());
            return [
                'buildings' => [],
                'total' => 0,
                'totalPages' => 0,
                'currentPage' => $page
            ];
        }
    }
    
    /**
     * 位置情報検索の専用実行メソッド
     */
    private function executeLocationSearch($whereClauses, $params, $userLat, $userLng, $page, $lang, $limit) {
        // WHERE句の構築
        $whereSql = $this->buildWhereClause($whereClauses);
        
        // カウントクエリ
        $countSql = $this->buildCountQuery($whereSql);
        
        try {
            // カウント実行
            $total = $this->executeCountQuery($countSql, $params);
            
            $totalPages = ceil($total / $limit);
            
            // ページ番号の検証と調整
            if ($page > $totalPages && $totalPages > 0) {
                $page = $totalPages; // 最終ページに調整
            } elseif ($page < 1) {
                $page = 1; // 1ページ目に調整
            }
            
            $offset = ($page - 1) * $limit;
            
            // データ取得クエリ（距離順でソート）
            $sql = $this->buildLocationSearchQuery($whereSql, $limit, $offset);
            
            // パラメータの順序を修正（SELECT句用 + WHERE句用）
            $locationParams = [$userLat, $userLng, $userLat]; // SELECT句用
            $allParams = array_merge($locationParams, $params); // WHERE句用
            
            // データ取得実行
            $rows = $this->executeSearchQuery($sql, $allParams);
            
            // データ変換
            $buildings = $this->transformBuildingData($rows, $lang);
            
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
     * キーワードを分割
     */
    private function parseKeywords($query) {
        if (empty($query)) {
            return [];
        }
        
        $temp = str_replace('　', ' ', $query);
        return array_filter(explode(' ', trim($temp)));
    }
    
    /**
     * キーワード検索条件を追加
     */
    private function addKeywordConditions(&$whereClauses, &$params, $keywords) {
        if (empty($keywords)) {
            return;
        }
        
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
    
    /**
     * 完成年条件を追加
     */
    private function addCompletionYearConditions(&$whereClauses, &$params, $completionYears) {
        if (empty($completionYears)) {
            return;
        }
        
        // 文字列の場合は配列に変換
        if (is_string($completionYears)) {
            $completionYears = [$completionYears];
        }
        
        $yearConditions = [];
        foreach ($completionYears as $year) {
            $yearConditions[] = "b.completionYears LIKE ?";
            $params[] = '%' . $year . '%';
        }
        
        if (!empty($yearConditions)) {
            $whereClauses[] = '(' . implode(' OR ', $yearConditions) . ')';
        }
    }
    
    /**
     * 都道府県条件を追加
     */
    private function addPrefectureConditions(&$whereClauses, &$params, $prefectures) {
        if (empty($prefectures)) {
            return;
        }
        
        // 都道府県の英語名→日本語名マッピング
        $prefectureTranslations = [
            'Hokkaido' => '北海道',
            'Aomori' => '青森県',
            'Iwate' => '岩手県',
            'Miyagi' => '宮城県',
            'Akita' => '秋田県',
            'Yamagata' => '山形県',
            'Fukushima' => '福島県',
            'Ibaraki' => '茨城県',
            'Tochigi' => '栃木県',
            'Gunma' => '群馬県',
            'Saitama' => '埼玉県',
            'Chiba' => '千葉県',
            'Tokyo' => '東京都',
            'Kanagawa' => '神奈川県',
            'Niigata' => '新潟県',
            'Toyama' => '富山県',
            'Ishikawa' => '石川県',
            'Fukui' => '福井県',
            'Yamanashi' => '山梨県',
            'Nagano' => '長野県',
            'Gifu' => '岐阜県',
            'Shizuoka' => '静岡県',
            'Aichi' => '愛知県',
            'Mie' => '三重県',
            'Shiga' => '滋賀県',
            'Kyoto' => '京都府',
            'Osaka' => '大阪府',
            'Hyogo' => '兵庫県',
            'Nara' => '奈良県',
            'Wakayama' => '和歌山県',
            'Tottori' => '鳥取県',
            'Shimane' => '島根県',
            'Okayama' => '岡山県',
            'Hiroshima' => '広島県',
            'Yamaguchi' => '山口県',
            'Tokushima' => '徳島県',
            'Kagawa' => '香川県',
            'Ehime' => '愛媛県',
            'Kochi' => '高知県',
            'Fukuoka' => '福岡県',
            'Saga' => '佐賀県',
            'Nagasaki' => '長崎県',
            'Kumamoto' => '熊本県',
            'Oita' => '大分県',
            'Miyazaki' => '宮崎県',
            'Kagoshima' => '鹿児島県',
            'Okinawa' => '沖縄県'
        ];
        
        // 文字列の場合は配列に変換
        if (is_string($prefectures)) {
            $prefectures = [$prefectures];
        }
        
        $prefectureConditions = [];
        foreach ($prefectures as $prefecture) {
            // 英語名の場合は日本語名に変換
            $japaneseName = isset($prefectureTranslations[$prefecture]) ? $prefectureTranslations[$prefecture] : $prefecture;
            
            // 日本語名で検索
            $prefectureConditions[] = "b.prefectures LIKE ?";
            $params[] = '%' . $japaneseName . '%';
        }
        
        if (!empty($prefectureConditions)) {
            $whereClauses[] = '(' . implode(' OR ', $prefectureConditions) . ')';
        }
    }
    
    /**
     * 建築種別条件を追加
     */
    private function addBuildingTypeConditions(&$whereClauses, &$params, $buildingTypes) {
        if (empty($buildingTypes)) {
            return;
        }
        
        $typeConditions = [];
        foreach ($buildingTypes as $type) {
            $typeConditions[] = "b.buildingTypes LIKE ?";
            $params[] = '%' . $type . '%';
        }
        
        if (!empty($typeConditions)) {
            $whereClauses[] = '(' . implode(' OR ', $typeConditions) . ')';
        }
    }
    
    /**
     * 共通フィルターを追加（住宅のみのデータを除外）
     */
    private function addCommonFilters(&$whereClauses) {
        $whereClauses[] = "(b.buildingTypes IS NULL OR b.buildingTypes = '' OR b.buildingTypes != '住宅')";
    }
    
    /**
     * メディアフィルターを追加
     */
    private function addMediaFilters(&$whereClauses, $hasPhotos, $hasVideos) {
        if ($hasPhotos) {
            $whereClauses[] = "b.has_photo IS NOT NULL AND b.has_photo != ''";
        }
        
        if ($hasVideos) {
            $whereClauses[] = "b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
        }
    }
    
    /**
     * 位置情報条件を追加
     */
    private function addLocationConditions(&$whereClauses, &$params, $userLat, $userLng, $radiusKm) {
        $whereClauses[] = "b.lat IS NOT NULL AND b.lng IS NOT NULL";
        $whereClauses[] = "(
            6371 * acos(
                cos(radians(?)) * cos(radians(b.lat)) * 
                cos(radians(b.lng) - radians(?)) + 
                sin(radians(?)) * sin(radians(b.lat))
            )
        ) <= ?";
        
        $params[] = $userLat;
        $params[] = $userLng;
        $params[] = $userLat;
        $params[] = $radiusKm;
    }
    
    /**
     * 建築家条件を追加（slug_groupを考慮）
     */
    private function addArchitectConditions(&$whereClauses, &$params, $architectSlug) {
        // 指定されたslugの建築家のslug_group_idを取得
        $slugGroupId = $this->getSlugGroupId($architectSlug);
        
        if ($slugGroupId !== null) {
            // slug_group_idが設定されている場合は、同じグループの建築家も含める
            $groupSlugs = $this->getSlugsByGroupId($slugGroupId);
            if (!empty($groupSlugs)) {
                $placeholders = str_repeat('?,', count($groupSlugs) - 1) . '?';
                $whereClauses[] = "ia.slug IN ($placeholders)";
                $params = array_merge($params, $groupSlugs);
            } else {
                // グループ内のslugが見つからない場合は、元のslugのみ
                $whereClauses[] = "ia.slug = ?";
                $params[] = $architectSlug;
            }
        } else {
            // slug_group_idがNULLの場合は、従来通り指定されたslugのみ
            $whereClauses[] = "ia.slug = ?";
            $params[] = $architectSlug;
        }
    }
    
    /**
     * 指定されたslugの建築家のslug_group_idを取得
     */
    private function getSlugGroupId($architectSlug) {
        try {
            $sql = "SELECT slug_group_id FROM {$this->individual_architects_table} WHERE slug = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$architectSlug]);
            $result = $stmt->fetch();
            
            return $result ? $result['slug_group_id'] : null;
        } catch (Exception $e) {
            error_log("Error getting slug_group_id: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 指定されたgroup_idに属するすべてのslugを取得
     */
    private function getSlugsByGroupId($groupId) {
        try {
            $sql = "SELECT slug FROM slug_to_group WHERE group_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$groupId]);
            $results = $stmt->fetchAll();
            
            return array_column($results, 'slug');
        } catch (Exception $e) {
            error_log("Error getting slugs by group_id: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * WHERE句を構築
     */
    private function buildWhereClause($whereClauses) {
        if (empty($whereClauses)) {
            return '';
        }
        return 'WHERE ' . implode(' AND ', $whereClauses);
    }
    
    /**
     * カウントクエリを構築
     */
    private function buildCountQuery($whereSql) {
        // WHERE句に建築家関連の条件が含まれているかチェック
        if (strpos($whereSql, 'ia.') !== false) {
            // 建築家検索用（JOINあり）
            return "
                SELECT COUNT(DISTINCT b.building_id) as total
                FROM {$this->buildings_table} b
                LEFT JOIN {$this->building_architects_table} ba ON b.building_id = ba.building_id
                LEFT JOIN {$this->architect_compositions_table} ac ON ba.architect_id = ac.architect_id
                LEFT JOIN {$this->individual_architects_table} ia ON ac.individual_architect_id = ia.individual_architect_id
                $whereSql
            ";
        } else {
            // 通常検索用（JOINあり、建築家情報も含める）
            return "
                SELECT COUNT(DISTINCT b.building_id) as total
                FROM {$this->buildings_table} b
                LEFT JOIN {$this->building_architects_table} ba ON b.building_id = ba.building_id
                LEFT JOIN {$this->architect_compositions_table} ac ON ba.architect_id = ac.architect_id
                LEFT JOIN {$this->individual_architects_table} ia ON ac.individual_architect_id = ia.individual_architect_id
                $whereSql
            ";
        }
    }
    
    /**
     * 検索クエリを構築
     */
    private function buildSearchQuery($whereSql, $limit, $offset) {
        // WHERE句に建築家関連の条件が含まれているかチェック
        if (strpos($whereSql, 'ia.') !== false) {
            // 建築家検索用（JOINあり）
            return "
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
                FROM {$this->buildings_table} b
                LEFT JOIN {$this->building_architects_table} ba ON b.building_id = ba.building_id
                LEFT JOIN {$this->architect_compositions_table} ac ON ba.architect_id = ac.architect_id
                LEFT JOIN {$this->individual_architects_table} ia ON ac.individual_architect_id = ia.individual_architect_id
                $whereSql
                GROUP BY b.building_id, b.uid, b.title, b.titleEn, b.slug, b.lat, b.lng, b.location, b.locationEn_from_datasheetChunkEn, b.completionYears, b.buildingTypes, b.buildingTypesEn, b.prefectures, b.prefecturesEn, b.has_photo, b.thumbnailUrl, b.youtubeUrl, b.created_at, b.updated_at
                ORDER BY b.has_photo DESC, b.building_id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
        } else {
            // 通常検索用（JOINあり、建築家情報も含める）
            return "
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
                FROM {$this->buildings_table} b
                LEFT JOIN {$this->building_architects_table} ba ON b.building_id = ba.building_id
                LEFT JOIN {$this->architect_compositions_table} ac ON ba.architect_id = ac.architect_id
                LEFT JOIN {$this->individual_architects_table} ia ON ac.individual_architect_id = ia.individual_architect_id
                $whereSql
                GROUP BY b.building_id, b.uid, b.title, b.titleEn, b.slug, b.lat, b.lng, b.location, b.locationEn_from_datasheetChunkEn, b.completionYears, b.buildingTypes, b.buildingTypesEn, b.prefectures, b.prefecturesEn, b.has_photo, b.thumbnailUrl, b.youtubeUrl, b.created_at, b.updated_at
                ORDER BY b.has_photo DESC, b.building_id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
        }
    }
    
    /**
     * 位置情報検索クエリを構築
     */
    private function buildLocationSearchQuery($whereSql, $limit, $offset) {
        return "
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
                   (
                       6371 * acos(
                           cos(radians(?)) * cos(radians(b.lat)) * 
                           cos(radians(b.lng) - radians(?)) + 
                           sin(radians(?)) * sin(radians(b.lat))
                       )
                   ) AS distance,
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
            FROM {$this->buildings_table} b
            LEFT JOIN {$this->building_architects_table} ba ON b.building_id = ba.building_id
            LEFT JOIN {$this->architect_compositions_table} ac ON ba.architect_id = ac.architect_id
            LEFT JOIN {$this->individual_architects_table} ia ON ac.individual_architect_id = ia.individual_architect_id
            $whereSql
            GROUP BY b.building_id, b.uid, b.title, b.titleEn, b.slug, b.lat, b.lng, b.location, b.locationEn_from_datasheetChunkEn, b.completionYears, b.buildingTypes, b.buildingTypesEn, b.prefectures, b.prefecturesEn, b.has_photo, b.thumbnailUrl, b.youtubeUrl, b.created_at, b.updated_at
            ORDER BY distance ASC
            LIMIT {$limit} OFFSET {$offset}
        ";
    }
    
    /**
     * カウントクエリを実行
     */
    private function executeCountQuery($sql, $params) {
        if ($this->db === null) {
            throw new Exception("Database connection is null");
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        if ($result) {
            if (isset($result['total'])) {
                return $result['total'];
            } elseif (isset($result[0])) {
                return $result[0];
            } else {
                return 0;
            }
        }
        return 0;
    }
    
    /**
     * 検索クエリを実行
     */
    private function executeSearchQuery($sql, $params) {
        if ($this->db === null) {
            throw new Exception("Database connection is null");
        }
        
        try {
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("SQL execution failed: " . print_r($errorInfo, true));
            }
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("executeSearchQuery error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 建築物データを変換
     */
    private function transformBuildingData($rows, $lang) {
        if (empty($rows)) {
            return [];
        }
        
        // 複数行の場合
        if (is_array($rows) && isset($rows[0])) {
            $buildings = [];
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $buildings[] = $this->transformSingleBuildingData($row, $lang);
                }
            }
            return $buildings;
        } 
        
        // 単一行の場合
        if (is_array($rows)) {
            return [$this->transformSingleBuildingData($rows, $lang)];
        }
        
        return [];
    }
    
    /**
     * 単一建築物データを変換
     */
    private function transformSingleBuildingData($row, $lang) {
        // 建築家情報の処理
        $architects = [];
        if (!empty($row['architectJa']) && $row['architectJa'] !== '') {
            $namesJa = explode(' / ', $row['architectJa']);
            $namesEn = !empty($row['architectEn']) && $row['architectEn'] !== '' ? explode(' / ', $row['architectEn']) : [];
            $architectIds = !empty($row['architectIds']) && $row['architectIds'] !== '' ? explode(',', $row['architectIds']) : [];
            $architectSlugs = !empty($row['architectSlugs']) && $row['architectSlugs'] !== '' ? explode(',', $row['architectSlugs']) : [];
            // individual_architect_ids, individual_websites, website_titlesは
            // "architect_order_order_index_value"形式で取得されているため、最後の部分を抽出
            $individualArchitectIdsRaw = !empty($row['individual_architect_ids']) && $row['individual_architect_ids'] !== '' ? explode(',', $row['individual_architect_ids']) : [];
            $individualWebsitesRaw = !empty($row['individual_websites']) && $row['individual_websites'] !== '' ? explode('|||', $row['individual_websites']) : [];
            $websiteTitlesRaw = !empty($row['website_titles']) && $row['website_titles'] !== '' ? explode('|||', $row['website_titles']) : [];
            
            // プレフィックス（architect_order_order_index_）を除去して値を抽出
            $individualArchitectIds = [];
            foreach ($individualArchitectIdsRaw as $value) {
                $parts = explode('_', $value);
                $individualArchitectIds[] = isset($parts[2]) ? intval($parts[2]) : 0;
            }
            
            $individualWebsites = [];
            foreach ($individualWebsitesRaw as $value) {
                // 形式: "architect_order_order_index_value"
                // 最初の2つのアンダースコアで分割し、残りを取得
                $parts = explode('_', $value, 3);
                // $parts[0] = architect_order, $parts[1] = order_index, $parts[2] = value (URL)
                $individualWebsites[] = isset($parts[2]) ? trim($parts[2]) : '';
            }
            
            $websiteTitles = [];
            foreach ($websiteTitlesRaw as $value) {
                $parts = explode('_', $value, 3);
                $websiteTitles[] = isset($parts[2]) ? trim($parts[2]) : '';
            }
            
            for ($i = 0; $i < count($namesJa); $i++) {
                $architects[] = [
                    'architect_id' => isset($architectIds[$i]) ? intval($architectIds[$i]) : 0,
                    'architectJa' => trim($namesJa[$i]),
                    'architectEn' => isset($namesEn[$i]) ? trim($namesEn[$i]) : trim($namesJa[$i]),
                    'slug' => isset($architectSlugs[$i]) ? trim($architectSlugs[$i]) : '',
                    'individual_architect_id' => isset($individualArchitectIds[$i]) ? intval($individualArchitectIds[$i]) : 0,
                    'individual_website' => isset($individualWebsites[$i]) ? trim($individualWebsites[$i]) : '',
                    'website_title' => isset($websiteTitles[$i]) ? trim($websiteTitles[$i]) : ''
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
        
        // サムネイルURLの生成
        $thumbnailUrl = '';
        if (!empty($row['has_photo'])) {
            $uid = $row['uid'] ?? '';
            $photo = $row['has_photo'];
            $thumbnailUrl = "/pictures/{$uid}/{$photo}";
        }
        
        return [
            'building_id' => $row['building_id'] ?? 0,
            'uid' => $row['uid'] ?? '',
            'title' => $lang === 'ja' ? ($row['title'] ?? '') : ($row['titleEn'] ?? ''),
            'titleEn' => $row['titleEn'] ?? '',
            'slug' => $row['slug'] ?? '',
            'lat' => $row['lat'] ?? 0,
            'lng' => $row['lng'] ?? 0,
            'location' => $lang === 'ja' ? ($row['location'] ?? '') : ($row['locationEn'] ?? ''),
            'locationEn' => $row['locationEn'] ?? '',
            'completionYears' => $row['completionYears'] ?? '',
            'buildingTypes' => $buildingTypes,
            'buildingTypesEn' => $buildingTypesEn,
            'prefectures' => $lang === 'ja' ? ($row['prefectures'] ?? '') : ($row['prefecturesEn'] ?? ''),
            'prefecturesEn' => $row['prefecturesEn'] ?? '',
            'has_photo' => $row['has_photo'] ?? '',
            'thumbnailUrl' => $thumbnailUrl,
            'youtubeUrl' => $row['youtubeUrl'] ?? '',
            'architects' => $architects,
            'likes' => $row['likes'] ?? 0,
            'distance' => isset($row['distance']) && $row['distance'] !== '' ? round($row['distance'], 2) : null,
            'created_at' => $row['created_at'] ?? '',
            'updated_at' => $row['updated_at'] ?? ''
        ];
    }
}
