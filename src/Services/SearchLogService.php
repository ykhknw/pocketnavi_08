<?php

// require_once __DIR__ . '/../Utils/Database.php'; // getDB()はconfig/database.phpで定義済み

/**
 * 検索ログ記録サービス
 */
class SearchLogService {
    private $db;
    
    public function __construct($database = null) {
        if ($database !== null) {
            $this->db = $database;
        } else {
            // フォールバック: getDB()関数が存在する場合
            if (function_exists('getDB')) {
                $this->db = getDB();
            } else {
                throw new Exception("Database connection not provided and getDB() function not available");
            }
        }
        
        if ($this->db === null) {
            throw new Exception("Database connection failed");
        }
    }
    
    /**
     * データベース接続を取得
     */
    public function getDatabase() {
        return $this->db;
    }
    
    /**
     * 週間トレンド検索を更新（必要に応じて）
     */
    public function updateWeeklyTrendingIfNeeded() {
        try {
            // 最後の更新日時をチェック
            $stmt = $this->db->query("SELECT last_update FROM trending_cache WHERE cache_key = 'weekly_trending'");
            $lastUpdate = $stmt->fetch();
            
            $oneWeekAgo = date('Y-m-d H:i:s', strtotime('-1 week'));
            
            // 1週間以上経過している場合のみ更新
            if (!$lastUpdate || $lastUpdate['last_update'] < $oneWeekAgo) {
                $this->updateWeeklyTrendingSearches();
                
                // 更新日時を記録
                $this->db->exec("
                    INSERT INTO trending_cache (cache_key, last_update, data) 
                    VALUES ('weekly_trending', NOW(), '{}')
                    ON DUPLICATE KEY UPDATE last_update = NOW()
                ");
                
                error_log("Weekly trending searches updated");
            }
        } catch (Exception $e) {
            error_log("Update weekly trending error: " . $e->getMessage());
        }
    }
    
    /**
     * 週間トレンド検索を計算・更新
     */
    private function updateWeeklyTrendingSearches() {
        try {
            // 過去5日間の人気検索を計算
            $sql = "
                SELECT 
                    query,
                    search_type,
                    COUNT(*) as total_searches,
                    COUNT(DISTINCT COALESCE(user_id, user_session_id, ip_address)) as unique_users,
                    MAX(searched_at) as last_searched
                FROM global_search_history
                WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)
                GROUP BY query, search_type
                HAVING COUNT(*) >= 2
                ORDER BY 
                    total_searches DESC, 
                    unique_users DESC,
                    last_searched DESC
                LIMIT 3
            ";
            
            $stmt = $this->db->query($sql);
            $trendingSearches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // トレンド検索テーブルを更新
            $this->db->exec("DELETE FROM weekly_trending_searches");
            
            foreach ($trendingSearches as $search) {
                $insertSql = "
                    INSERT INTO weekly_trending_searches 
                    (query, search_type, total_searches, unique_users, last_searched, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ";
                $this->db->prepare($insertSql)->execute([
                    $search['query'],
                    $search['search_type'],
                    $search['total_searches'],
                    $search['unique_users'],
                    $search['last_searched']
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Update weekly trending searches error: " . $e->getMessage());
        }
    }
    
    /**
     * 週間トレンド検索を取得
     */
    public function getWeeklyTrendingSearches() {
        try {
            // 必要に応じて更新
            $this->updateWeeklyTrendingIfNeeded();
            
            // トレンド検索を取得
            $sql = "
                SELECT query, search_type, total_searches, unique_users, last_searched
                FROM weekly_trending_searches
                ORDER BY total_searches DESC, unique_users DESC
                LIMIT 3
            ";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get weekly trending searches error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 検索ログを記録
     */
    public function logSearch($query, $searchType = 'text', $filters = [], $userId = null) {
        // 重複防止チェック
        if ($this->isDuplicateSearch($query, $searchType)) {
            return false;
        }
        
        $sessionId = $this->getSessionId();
        $ipAddress = $this->getClientIpAddress();
        
        // 言語情報を取得
        $lang = $filters['lang'] ?? 'ja';
        
        // 検索タイプに応じて追加情報を取得（英語表示対応）
        $additionalData = $this->getAdditionalSearchData($query, $searchType, $lang);
        $filters = array_merge($filters, $additionalData);
        
        $sql = "
            INSERT INTO global_search_history 
            (query, search_type, user_id, user_session_id, ip_address, filters) 
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $query,
                $searchType,
                $userId,
                $sessionId,
                $ipAddress,
                json_encode($filters)
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Search log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 検索タイプに応じて追加データを取得（英語表示対応）
     */
    private function getAdditionalSearchData($query, $searchType, $lang = 'ja') {
        $additionalData = [];
        
        try {
            switch ($searchType) {
                case 'architect':
                    $architectData = $this->getArchitectDataByQuery($query);
                    if ($architectData) {
                        $additionalData = [
                            'architect_id' => $architectData['individual_architect_id'],
                            'architect_slug' => $architectData['slug'],
                            'architect_name_ja' => $architectData['name_ja'],
                            'architect_name_en' => $architectData['name_en']
                        ];
                        
                        // 日本語検索の場合、英語表示用データを追加
                        if ($lang === 'ja') {
                            $additionalData['title_en'] = $architectData['name_en'];
                        }
                    }
                    break;
                    
                case 'prefecture':
                    $prefectureData = $this->getPrefectureDataByQuery($query);
                    if ($prefectureData) {
                        $additionalData = [
                            'prefecture_ja' => $prefectureData['prefectures'],
                            'prefecture_en' => $prefectureData['prefecturesEn']
                        ];
                        
                        // 日本語検索の場合、英語表示用データを追加
                        if ($lang === 'ja') {
                            $additionalData['title_en'] = $prefectureData['prefecturesEn'];
                        }
                    }
                    break;
                    
                case 'building':
                    $buildingData = $this->getBuildingDataByQuery($query);
                    if ($buildingData) {
                        $additionalData = [
                            'building_id' => $buildingData['building_id'],
                            'building_slug' => $buildingData['slug'],
                            'building_title_ja' => $buildingData['title'],
                            'building_title_en' => $buildingData['titleEn']
                        ];
                        
                        // 日本語検索の場合、英語表示用データを追加
                        if ($lang === 'ja') {
                            $additionalData['title_en'] = $buildingData['titleEn'];
                        }
                    }
                    break;
                    
                case 'text':
                    // テキスト検索の場合は英語表示用データを追加しない
                    break;
            }
        } catch (Exception $e) {
            error_log("Get additional search data error: " . $e->getMessage());
        }
        
        return $additionalData;
    }
    
    /**
     * 建築家クエリから建築家データを取得
     */
    private function getArchitectDataByQuery($query) {
        $sql = "
            SELECT individual_architect_id, name_ja, name_en, slug
            FROM individual_architects_3
            WHERE name_ja = ? OR name_en = ? OR slug = ?
            LIMIT 1
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$query, $query, $query]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get architect data by query error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 都道府県クエリから都道府県データを取得
     */
    private function getPrefectureDataByQuery($query) {
        // 都道府県名の英語→日本語変換配列
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
        
        // 日本語名から英語名を逆引き
        $englishToJapanese = array_flip($prefectureTranslations);
        
        // クエリが日本語名か英語名かを判定
        $prefectureEn = $query;
        $prefectureJa = $query;
        
        if (isset($prefectureTranslations[$query])) {
            // 英語名の場合
            $prefectureJa = $prefectureTranslations[$query];
        } elseif (isset($englishToJapanese[$query])) {
            // 日本語名の場合
            $prefectureEn = $englishToJapanese[$query];
        }
        
        return [
            'prefectures' => $prefectureJa,
            'prefecturesEn' => $prefectureEn
        ];
    }
    
    /**
     * 建築物クエリから建築物データを取得
     */
    private function getBuildingDataByQuery($query) {
        $sql = "
            SELECT building_id, title, titleEn, slug
            FROM buildings_table_3
            WHERE title = ? OR titleEn = ? OR slug = ?
            LIMIT 1
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$query, $query, $query]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get building data by query error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ページ閲覧ログを記録（建築家、建築物、都道府県ページ）
     */
    public function logPageView($pageType, $identifier, $title = '', $additionalData = []) {
        // 重複防止チェック（同一セッションで5分以内の同一ページ閲覧を制限）
        if ($this->isDuplicatePageView($pageType, $identifier)) {
            return false;
        }
        
        $sessionId = $this->getSessionId();
        $ipAddress = $this->getClientIpAddress();
        
        // ページタイプに応じて検索タイプを決定
        $searchType = $this->getSearchTypeFromPageType($pageType);
        
        // クエリ文字列を生成
        $query = $this->generateQueryFromPageType($pageType, $identifier, $title);
        
        // 言語情報を取得
        $lang = $additionalData['lang'] ?? 'ja';
        
        // フィルター情報を構築
        $filters = array_merge([
            'pageType' => $pageType,
            'identifier' => $identifier,
            'title' => $title
        ], $additionalData);
        
        // 英語表示用データを追加（日本語検索の場合のみ）
        if ($lang === 'ja' && in_array($pageType, ['building', 'architect', 'prefecture'])) {
            $englishData = $this->getAdditionalSearchData($query, $searchType, $lang);
            $filters = array_merge($filters, $englishData);
        }
        
        $sql = "
            INSERT INTO global_search_history 
            (query, search_type, user_id, user_session_id, ip_address, filters) 
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $query,
                $searchType,
                null, // ページ閲覧はユーザーIDなし
                $sessionId,
                $ipAddress,
                json_encode($filters)
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Page view log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ページ閲覧の重複チェック
     */
    private function isDuplicatePageView($pageType, $identifier) {
        $sessionId = $this->getSessionId();
        $ipAddress = $this->getClientIpAddress();
        
        $sql = "
            SELECT COUNT(*) as count 
            FROM global_search_history 
            WHERE JSON_EXTRACT(filters, '$.pageType') = ? 
            AND JSON_EXTRACT(filters, '$.identifier') = ?
            AND (user_session_id = ? OR ip_address = ?)
            AND searched_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$pageType, $identifier, $sessionId, $ipAddress]);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
            
        } catch (Exception $e) {
            error_log("Duplicate page view check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ページタイプから検索タイプを取得
     */
    private function getSearchTypeFromPageType($pageType) {
        switch ($pageType) {
            case 'architect':
                return 'architect';
            case 'building':
                return 'building';
            case 'prefecture':
                return 'prefecture';
            default:
                return 'text';
        }
    }
    
    /**
     * ページタイプからクエリ文字列を生成
     */
    private function generateQueryFromPageType($pageType, $identifier, $title) {
        switch ($pageType) {
            case 'architect':
                return $title ?: $identifier;
            case 'building':
                return $title ?: $identifier;
            case 'prefecture':
                return $title ?: $identifier;
            default:
                return $identifier;
        }
    }
    
    /**
     * 重複検索チェック（5分以内の同一検索を防止）
     */
    private function isDuplicateSearch($query, $searchType) {
        $sessionId = $this->getSessionId();
        $ipAddress = $this->getClientIpAddress();
        
        $sql = "
            SELECT COUNT(*) as count 
            FROM global_search_history 
            WHERE query = ? 
            AND search_type = ? 
            AND (user_session_id = ? OR ip_address = ?)
            AND searched_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$query, $searchType, $sessionId, $ipAddress]);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
            
        } catch (Exception $e) {
            error_log("Duplicate check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 人気検索ワードを取得（モーダル用）
     */
    public function getPopularSearchesForModal($page = 1, $limit = 20, $searchQuery = '', $searchType = '') {
        $offset = ($page - 1) * $limit;
        
        $whereClauses = ["searched_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)"];
        $params = [];
        
        // 検索クエリフィルタ
        if (!empty($searchQuery)) {
            $whereClauses[] = "query LIKE ?";
            $params[] = '%' . $searchQuery . '%';
        }
        
        // 検索タイプフィルタ
        if (!empty($searchType)) {
            $whereClauses[] = "search_type = ?";
            $params[] = $searchType;
        }
        
        $whereClause = implode(' AND ', $whereClauses);
        
        $sql = "
            SELECT 
                query,
                search_type,
                COUNT(*) as total_searches,
                COUNT(DISTINCT COALESCE(user_id, user_session_id, ip_address)) as unique_users,
                MAX(searched_at) as last_searched,
                MAX(JSON_EXTRACT(filters, '$.pageType')) as page_type,
                MAX(JSON_EXTRACT(filters, '$.identifier')) as identifier,
                MAX(JSON_EXTRACT(filters, '$.title')) as title,
                MAX(filters) as filters
            FROM global_search_history
            WHERE {$whereClause}
            GROUP BY query, search_type
            HAVING COUNT(*) >= 2
            ORDER BY 
                total_searches DESC, 
                unique_users DESC,
                last_searched DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $searches = $stmt->fetchAll();
            
            // リンク情報を追加
            foreach ($searches as &$search) {
                $search['link'] = $this->generateLinkFromSearchData($search);
            }
            
            // 総件数を取得
            $countSql = "
                SELECT COUNT(*) as total
                FROM (
                    SELECT query, search_type
                    FROM global_search_history
                    WHERE {$whereClause}
                    GROUP BY query, search_type
                    HAVING COUNT(*) >= 2
                ) as subquery
            ";
            
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute(array_slice($params, 0, -2)); // LIMIT, OFFSETを除く
            $totalCount = $countStmt->fetch()['total'];
            
            return [
                'searches' => $searches,
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($totalCount / $limit)
            ];
            
        } catch (Exception $e) {
            error_log("Get popular searches error: " . $e->getMessage());
            return [
                'searches' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => 0
            ];
        }
    }
    
    /**
     * 検索データからリンクを生成
     */
    private function generateLinkFromSearchData($searchData) {
        $pageType = $searchData['page_type'];
        $identifier = $searchData['identifier'];
        $searchType = $searchData['search_type'];
        $query = $searchData['query'];
        
        // ページタイプが設定されている場合（ページ閲覧ログ）
        if ($pageType && $pageType !== 'null') {
            switch ($pageType) {
                case 'architect':
                    return "/architects/{$identifier}/";
                case 'building':
                    return "/buildings/{$identifier}/";
                case 'prefecture':
                    return "/index.php?prefectures=" . urlencode($identifier);
            }
        }
        
        // 検索タイプに基づいてリンクを生成（検索ログ）
        switch ($searchType) {
            case 'architect':
                // 建築家検索の場合、filtersからslugを取得
                $filters = json_decode($searchData['filters'] ?? '{}', true);
                
                // ページ閲覧ログの場合（pageTypeが設定されている）
                if (isset($filters['pageType']) && $filters['pageType'] === 'architect') {
                    return "/architects/{$filters['identifier']}/";
                }
                
                // 検索ログの場合
                if (isset($filters['architect_slug'])) {
                    return "/architects/{$filters['architect_slug']}/";
                }
                
                // filtersにarchitect_slugがない場合、クエリから直接検索
                $architectData = $this->getArchitectDataByQuery($query);
                if ($architectData && isset($architectData['slug'])) {
                    return "/architects/{$architectData['slug']}/";
                }
                
                return "/index.php?q=" . urlencode($query) . "&type=architect";
                
            case 'prefecture':
                // 都道府県検索の場合、filtersからprefecturesEnを取得
                $filters = json_decode($searchData['filters'] ?? '{}', true);
                
                // ページ閲覧ログの場合（pageTypeが設定されている）
                if (isset($filters['pageType']) && $filters['pageType'] === 'prefecture') {
                    return "/index.php?prefectures=" . urlencode($filters['identifier'] ?? $query);
                }
                
                // 検索ログの場合
                if (isset($filters['prefecture_en'])) {
                    return "/index.php?prefectures=" . urlencode($filters['prefecture_en']);
                }
                
                // filtersにprefecture_enがない場合、日本語名から英語名に変換
                $prefectureEn = $this->convertJapaneseToEnglishPrefecture($query);
                return "/index.php?prefectures=" . urlencode($prefectureEn);
                
            case 'building':
                // 建築物検索の場合、filtersからslugを取得
                $filters = json_decode($searchData['filters'] ?? '{}', true);
                
                // ページ閲覧ログの場合
                if (isset($filters['pageType']) && $filters['pageType'] === 'building') {
                    return "/buildings/{$filters['identifier']}/";
                }
                
                // 検索ログの場合
                if (isset($filters['building_slug'])) {
                    return "/buildings/{$filters['building_slug']}/";
                }
                return "/index.php?q=" . urlencode($query) . "&type=building";
                
            default:
                return "/index.php?q=" . urlencode($query);
        }
    }
    
    /**
     * サイドバー用の人気検索ワードを取得（上位20件）
     */
    public function getPopularSearchesForSidebar($limit = 20) {
        $sql = "
            SELECT 
                query,
                search_type,
                COUNT(*) as total_searches,
                MAX(JSON_EXTRACT(filters, '$.pageType')) as page_type,
                MAX(JSON_EXTRACT(filters, '$.identifier')) as identifier,
                MAX(JSON_EXTRACT(filters, '$.title')) as title,
                MAX(filters) as filters
            FROM global_search_history
            WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)
            AND search_type IS NOT NULL
            AND search_type != ''
            GROUP BY query, search_type
            HAVING COUNT(*) >= 1
            ORDER BY 
                total_searches DESC, 
                COUNT(DISTINCT COALESCE(user_id, user_session_id, ip_address)) DESC,
                MAX(searched_at) DESC
            LIMIT ?
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            $searches = $stmt->fetchAll();
            
            // リンク情報を追加
            foreach ($searches as &$search) {
                $search['link'] = $this->generateLinkFromSearchData($search);
                // countフィールドを追加（サイドバー表示用）
                $search['count'] = $search['total_searches'];
            }
            
            return $searches;
            
        } catch (Exception $e) {
            error_log("Get sidebar popular searches error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 日本語都道府県名から英語名に変換
     */
    private function convertJapaneseToEnglishPrefecture($japaneseName) {
        // 都道府県名の日本語→英語変換配列
        $japaneseToEnglish = [
            '北海道' => 'Hokkaido',
            '青森県' => 'Aomori',
            '岩手県' => 'Iwate',
            '宮城県' => 'Miyagi',
            '秋田県' => 'Akita',
            '山形県' => 'Yamagata',
            '福島県' => 'Fukushima',
            '茨城県' => 'Ibaraki',
            '栃木県' => 'Tochigi',
            '群馬県' => 'Gunma',
            '埼玉県' => 'Saitama',
            '千葉県' => 'Chiba',
            '東京都' => 'Tokyo',
            '神奈川県' => 'Kanagawa',
            '新潟県' => 'Niigata',
            '富山県' => 'Toyama',
            '石川県' => 'Ishikawa',
            '福井県' => 'Fukui',
            '山梨県' => 'Yamanashi',
            '長野県' => 'Nagano',
            '岐阜県' => 'Gifu',
            '静岡県' => 'Shizuoka',
            '愛知県' => 'Aichi',
            '三重県' => 'Mie',
            '滋賀県' => 'Shiga',
            '京都府' => 'Kyoto',
            '大阪府' => 'Osaka',
            '兵庫県' => 'Hyogo',
            '奈良県' => 'Nara',
            '和歌山県' => 'Wakayama',
            '鳥取県' => 'Tottori',
            '島根県' => 'Shimane',
            '岡山県' => 'Okayama',
            '広島県' => 'Hiroshima',
            '山口県' => 'Yamaguchi',
            '徳島県' => 'Tokushima',
            '香川県' => 'Kagawa',
            '愛媛県' => 'Ehime',
            '高知県' => 'Kochi',
            '福岡県' => 'Fukuoka',
            '佐賀県' => 'Saga',
            '長崎県' => 'Nagasaki',
            '熊本県' => 'Kumamoto',
            '大分県' => 'Oita',
            '宮崎県' => 'Miyazaki',
            '鹿児島県' => 'Kagoshima',
            '沖縄県' => 'Okinawa'
        ];
        
        return $japaneseToEnglish[$japaneseName] ?? $japaneseName;
    }
    
    /**
     * セッションIDを取得または生成
     */
    private function getSessionId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['search_session_id'])) {
            $_SESSION['search_session_id'] = uniqid('search_', true);
        }
        
        return $_SESSION['search_session_id'];
    }
    
    /**
     * クライアントのIPアドレスを取得
     */
    private function getClientIpAddress() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // プロキシ経由の場合は最初のIPを使用
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * 古い検索履歴データをクリーンアップ
     * 
     * @param int $retentionDays データ保持期間（日数）
     * @param bool $archiveImportant 重要なデータをアーカイブするかどうか
     * @return array クリーンアップ結果
     */
    public function cleanupOldSearchHistory($retentionDays = 90, $archiveImportant = true) {
        $result = [
            'deleted_count' => 0,
            'archived_count' => 0,
            'error' => null
        ];
        
        try {
            $this->db->beginTransaction();
            
            // アーカイブ対象のデータを特定（人気検索ワード）
            if ($archiveImportant) {
                $result['archived_count'] = $this->archiveImportantSearches($retentionDays);
            }
            
            // 古いデータを削除
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
            
            $deleteSql = "
                DELETE FROM global_search_history 
                WHERE searched_at < ?
            ";
            
            $stmt = $this->db->prepare($deleteSql);
            $stmt->execute([$cutoffDate]);
            $result['deleted_count'] = $stmt->rowCount();
            
            // インデックスを最適化
            $this->db->exec("OPTIMIZE TABLE global_search_history");
            
            $this->db->commit();
            
            error_log("Search history cleanup completed: {$result['deleted_count']} records deleted, {$result['archived_count']} records archived");
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $result['error'] = $e->getMessage();
            error_log("Search history cleanup error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * 重要な検索データをアーカイブ
     * 
     * @param int $retentionDays データ保持期間
     * @return int アーカイブされたレコード数
     */
    private function archiveImportantSearches($retentionDays) {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        
        // アーカイブテーブルが存在しない場合は作成
        $this->createArchiveTableIfNotExists();
        
        // 人気検索ワード（過去5日間で2回以上検索されたもの）をアーカイブ
        $archiveSql = "
            INSERT INTO global_search_history_archive 
            (original_id, query, search_type, user_id, user_session_id, ip_address, filters, searched_at, created_at, archived_at)
            SELECT 
                id, query, search_type, user_id, user_session_id, ip_address, filters, searched_at, created_at, NOW()
            FROM global_search_history gsh1
            WHERE gsh1.searched_at < ?
            AND gsh1.id IN (
                SELECT gsh2.id
                FROM global_search_history gsh2
                WHERE gsh2.query = gsh1.query 
                AND gsh2.search_type = gsh1.search_type
                AND gsh2.searched_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)
                GROUP BY gsh2.query, gsh2.search_type
                HAVING COUNT(*) >= 2
            )
        ";
        
        $stmt = $this->db->prepare($archiveSql);
        $stmt->execute([$cutoffDate]);
        
        return $stmt->rowCount();
    }
    
    /**
     * アーカイブテーブルを作成（存在しない場合）
     */
    private function createArchiveTableIfNotExists() {
        $createTableSql = "
            CREATE TABLE IF NOT EXISTS `global_search_history_archive` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `original_id` BIGINT NOT NULL,
                `query` TEXT NOT NULL,
                `search_type` VARCHAR(20) NOT NULL,
                `user_id` BIGINT NULL,
                `user_session_id` VARCHAR(255) NULL,
                `ip_address` VARCHAR(45) NULL,
                `filters` JSON NULL,
                `searched_at` TIMESTAMP NOT NULL,
                `created_at` TIMESTAMP NOT NULL,
                `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_original_id` (`original_id`),
                INDEX `idx_query` (`query`(100)),
                INDEX `idx_search_type` (`search_type`),
                INDEX `idx_searched_at` (`searched_at`),
                INDEX `idx_archived_at` (`archived_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->db->exec($createTableSql);
    }
    
    /**
     * データベースの統計情報を取得
     * 
     * @return array 統計情報
     */
    public function getDatabaseStats() {
        try {
            // 現在のテーブルサイズ
            $sizeSql = "
                SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
                    table_rows
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name IN ('global_search_history', 'global_search_history_archive', 'weekly_trending_searches')
            ";
            
            $stmt = $this->db->query($sizeSql);
            $tableStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 検索履歴の統計
            $historyStatsSql = "
                SELECT 
                    COUNT(*) as total_records,
                    COUNT(DISTINCT query) as unique_queries,
                    COUNT(DISTINCT search_type) as search_types,
                    MIN(searched_at) as oldest_record,
                    MAX(searched_at) as newest_record,
                    COUNT(CASE WHEN searched_at >= DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 1 END) as records_last_3days,
                    COUNT(CASE WHEN searched_at >= DATE_SUB(NOW(), INTERVAL 5 DAY) THEN 1 END) as records_last_5days
                FROM global_search_history
            ";
            
            $stmt = $this->db->query($historyStatsSql);
            $historyStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'table_stats' => $tableStats,
                'history_stats' => $historyStats,
                'recommendations' => $this->generateRecommendations($historyStats)
            ];
            
        } catch (Exception $e) {
            error_log("Get database stats error: " . $e->getMessage());
            return [
                'table_stats' => [],
                'history_stats' => [],
                'recommendations' => [],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * データベース管理の推奨事項を生成
     * 
     * @param array $stats 統計情報
     * @return array 推奨事項
     */
    private function generateRecommendations($stats) {
        $recommendations = [];
        
        if (empty($stats)) {
            return $recommendations;
        }
        
        $totalRecords = $stats['total_records'] ?? 0;
        $recordsLastMonth = $stats['records_last_month'] ?? 0;
        
        // レコード数に基づく推奨事項
        if ($totalRecords > 100000) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => '検索履歴が10万件を超えています。定期的なクリーンアップを推奨します。',
                'action' => 'cleanup_immediate'
            ];
        } elseif ($totalRecords > 50000) {
            $recommendations[] = [
                'type' => 'info',
                'message' => '検索履歴が5万件を超えています。クリーンアップの準備を検討してください。',
                'action' => 'cleanup_plan'
            ];
        }
        
        // 月間レコード数に基づく推奨事項
        if ($recordsLastMonth > 10000) {
            $recommendations[] = [
                'type' => 'info',
                'message' => '月間検索数が多いため、データ保持期間を短縮することを検討してください。',
                'action' => 'reduce_retention'
            ];
        }
        
        // 古いデータの推奨事項
        if (isset($stats['oldest_record'])) {
            $oldestDate = new DateTime($stats['oldest_record']);
            $daysOld = $oldestDate->diff(new DateTime())->days;
            
            if ($daysOld > 180) {
                $recommendations[] = [
                    'type' => 'warning',
                    'message' => "最も古いデータが{$daysOld}日前のものです。アーカイブまたは削除を検討してください。",
                    'action' => 'archive_old_data'
                ];
            }
        }
        
        return $recommendations;
    }
}