<?php

// 必要なファイルを読み込み
//require_once __DIR__ . '/../Utils/Database.php';

/**
 * 建築家検索サービス
 */
class ArchitectService {
    private $db;
    private $individual_architects_table = 'individual_architects_3';
    private $building_architects_table = 'building_architects';
    private $architect_compositions_table = 'architect_compositions_2';
    private $buildings_table = 'buildings_table_4';
    
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
     * スラッグで建築家を取得
     */
    public function getBySlug($slug, $lang = 'ja') {
        $sql = "
            SELECT ia.individual_architect_id,
                   ia.name_ja,
                   ia.name_en,
                   ia.slug,
                   ia.individual_website,
                   ia.website_title,
                   ia.created_at,
                   ia.updated_at
            FROM {$this->individual_architects_table} ia
            WHERE ia.slug = ?
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            
            if ($row) {
                return $this->transformArchitectData($row, $lang);
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Get architect by slug error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Slug: " . $slug);
            return null;
        }
    }
    
    /**
     * 建築家の建築物一覧を取得
     */
    public function getBuildings($architectId, $page = 1, $lang = 'ja', $limit = 10) {
        $offset = ($page - 1) * $limit;
        
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
            FROM {$this->buildings_table} b
            INNER JOIN {$this->building_architects_table} ba ON b.building_id = ba.building_id
            INNER JOIN {$this->architect_compositions_table} ac ON ba.architect_id = ac.architect_id
            INNER JOIN {$this->individual_architects_table} ia ON ac.individual_architect_id = ia.individual_architect_id
            WHERE ia.individual_architect_id = ?
            GROUP BY b.building_id
            ORDER BY b.has_photo DESC, b.building_id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $countSql = "
            SELECT COUNT(DISTINCT b.building_id) as total
            FROM {$this->buildings_table} b
            INNER JOIN {$this->building_architects_table} ba ON b.building_id = ba.building_id
            INNER JOIN {$this->architect_compositions_table} ac ON ba.architect_id = ac.architect_id
            INNER JOIN {$this->individual_architects_table} ia ON ac.individual_architect_id = ia.individual_architect_id
            WHERE ia.individual_architect_id = ?
        ";
        
        try {
            // カウント実行
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute([$architectId]);
            $total = $countStmt->fetch()['total'];
            
            // データ取得実行
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$architectId]);
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
            error_log("Get architect buildings error: " . $e->getMessage());
            return [
                'buildings' => [],
                'total' => 0,
                'totalPages' => 0,
                'currentPage' => $page
            ];
        }
    }
    
    /**
     * 人気検索語を取得(OLD!!!!!!!!!!!!!!)
     */
    public function getPopularSearches_old($lang = 'ja') {
        try {
            require_once __DIR__ . '/SearchLogService.php';
            $searchLogService = new SearchLogService();
            $searches = $searchLogService->getPopularSearchesForSidebar(20);
            
            // SearchLogServiceから取得した完全なデータをそのまま返す
            return $searches;
            
        } catch (Exception $e) {
            error_log("Get popular searches error: " . $e->getMessage());
            return [];
        }
    }

/**
 * 人気検索語を取得（キャッシュ使用版）
 */
public function getPopularSearches($lang = 'ja') {
    try {
        require_once __DIR__ . '/PopularSearchCache.php';
        $cache = new PopularSearchCache();
        
        // キャッシュからデータを取得（searchType = '' で全件）
        $result = $cache->getPopularSearches(1, 20, '', '');
        
        if (isset($result['searches']) && !empty($result['searches'])) {
            // サイドバー用に整形
            $searches = [];
            foreach ($result['searches'] as $search) {
                $searches[] = [
                    'query' => $search['query'] ?? '',
                    'count' => $search['total_searches'] ?? 0,
                    'search_type' => $search['search_type'] ?? 'text',
                    'link' => $search['link'] ?? ''
                ];
            }
            return $searches;
        }
        
        // キャッシュがない場合はフォールバック
        return $this->getFallbackSearches();
        
    } catch (Exception $e) {
        error_log("Get popular searches error: " . $e->getMessage());
        return $this->getFallbackSearches();
    }
}

/**
 * フォールバックデータ
 */
private function getFallbackSearches() {
    return [
        ['query' => '安藤忠雄', 'count' => 45, 'search_type' => 'architect', 'link' => '/?q=安藤忠雄'],
        ['query' => '隈研吾', 'count' => 42, 'search_type' => 'architect', 'link' => '/?q=隈研吾'],
        ['query' => '美術館', 'count' => 38, 'search_type' => 'text', 'link' => '/?q=美術館'],
        ['query' => '東京', 'count' => 35, 'search_type' => 'prefecture', 'link' => '/?prefectures=Tokyo'],
        ['query' => '現代建築', 'count' => 28, 'search_type' => 'text', 'link' => '/?q=現代建築']
    ];
}


    
    /**
     * 建築家データを変換
     */
    private function transformArchitectData($row, $lang) {
        try {
            // デバッグ情報（開発時のみ）
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                error_log("TransformArchitectData - Raw row data: " . print_r($row, true));
                error_log("TransformArchitectData - name_ja: '" . ($row['name_ja'] ?? 'NULL') . "'");
                error_log("TransformArchitectData - name_en: '" . ($row['name_en'] ?? 'NULL') . "'");
                error_log("TransformArchitectData - lang: '" . $lang . "'");
            }
            
            $architect = [
                'id' => $row['individual_architect_id'],
                'name' => $lang === 'ja' ? $row['name_ja'] : $row['name_en'],
                'nameJa' => $row['name_ja'],
                'nameEn' => $row['name_en'],
                'slug' => $row['slug'],
                'individual_website' => $row['individual_website'] ?? '',
                'website_title' => $row['website_title'] ?? '',
                'individual_architect_id' => $row['individual_architect_id'],
                'name_ja' => $row['name_ja'],
                'name_en' => $row['name_en'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at']
            ];
            
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                error_log("TransformArchitectData - Final architect data: " . print_r($architect, true));
            }
            
            return $architect;
        } catch (Exception $e) {
            error_log("Transform architect data error: " . $e->getMessage());
            error_log("Row data: " . print_r($row, true));
            return null;
        }
    }
}
