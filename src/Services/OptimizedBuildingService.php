<?php

/**
 * 最適化された建築物検索サービス
 * Phase 4.1.2: クエリ最適化版
 */
class OptimizedBuildingService {
    private $db;
    private $buildings_table;
    private $building_architects_table;
    private $architect_compositions_table;
    private $individual_architects_table;
    
    public function __construct($db) {
        $this->db = $db;
        $this->buildings_table = 'buildings_table_3';
        $this->building_architects_table = 'building_architects';
        $this->architect_compositions_table = 'architect_compositions_2';
        $this->individual_architects_table = 'individual_architects_3';
    }
    
    /**
     * 最適化された位置情報検索
     * インデックスを活用し、距離計算を最適化
     */
    public function searchByLocationOptimized($userLat, $userLng, $radiusKm = 5, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        // 最適化された座標検索クエリ
        // インデックス idx_buildings_coords_photo を活用
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
                   ST_Distance_Sphere(
                       POINT(?, ?),
                       POINT(b.lng, b.lat)
                   ) / 1000 AS distance,
                   '' as architectJa,
                   '' as architectEn,
                   '' as architectIds,
                   '' as architectSlugs
            FROM {$this->buildings_table} b
            WHERE b.lat IS NOT NULL 
              AND b.lng IS NOT NULL
              AND b.lat BETWEEN ? AND ?
              AND b.lng BETWEEN ? AND ?
              AND ST_Distance_Sphere(
                  POINT(?, ?),
                  POINT(b.lng, b.lat)
              ) <= ?
        ";
        
        // 緯度経度の範囲を計算（半径に基づく大まかな範囲）
        $latRange = $radiusKm / 111.0; // 1度 ≈ 111km
        $lngRange = $radiusKm / (111.0 * cos(deg2rad($userLat)));
        
        $minLat = $userLat - $latRange;
        $maxLat = $userLat + $latRange;
        $minLng = $userLng - $lngRange;
        $maxLng = $userLng + $lngRange;
        
        $params = [
            $userLng, $userLat, // ST_Distance_Sphere用
            $minLat, $maxLat, $minLng, $maxLng, // 範囲フィルター用
            $userLng, $userLat, $radiusKm * 1000 // 距離計算用（メートル）
        ];
        
        // メディアフィルターの追加
        if ($hasPhotos) {
            $sql .= " AND b.has_photo = 1";
        }
        if ($hasVideos) {
            $sql .= " AND b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
        }
        
        // 並び替えとページング
        $sql .= " ORDER BY b.has_photo DESC, distance ASC LIMIT {$limit} OFFSET {$offset}";
        
        // カウントクエリ（最適化版）
        $countSql = "
            SELECT COUNT(*) as total
            FROM {$this->buildings_table} b
            WHERE b.lat IS NOT NULL 
              AND b.lng IS NOT NULL
              AND b.lat BETWEEN ? AND ?
              AND b.lng BETWEEN ? AND ?
              AND ST_Distance_Sphere(
                  POINT(?, ?),
                  POINT(b.lng, b.lat)
              ) <= ?
        ";
        
        $countParams = [
            $minLat, $maxLat, $minLng, $maxLng,
            $userLng, $userLat, $radiusKm * 1000
        ];
        
        if ($hasPhotos) {
            $countSql .= " AND b.has_photo = 1";
        }
        if ($hasVideos) {
            $countSql .= " AND b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
        }
        
        try {
            // カウント実行
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch()['total'];
            
            // データ取得実行
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            
            // データ変換
            $buildings = $this->transformBuildingData($rows, $lang);
            
            $totalPages = ceil($total / $limit);
            
            return [
                'buildings' => $buildings,
                'total' => $total,
                'totalPages' => $totalPages,
                'currentPage' => $page
            ];
            
        } catch (Exception $e) {
            error_log("Optimized location search error: " . $e->getMessage());
            return ['buildings' => [], 'total' => 0, 'totalPages' => 0, 'currentPage' => $page];
        }
    }
    
    /**
     * 最適化されたキーワード検索
     * インデックスを活用し、不要なJOINを削除
     */
    public function searchByKeywordsOptimized($query, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
        $offset = ($page - 1) * $limit;
        $keywords = $this->parseKeywords($query);
        
        if (empty($keywords)) {
            return $this->getAllBuildingsOptimized($page, $hasPhotos, $hasVideos, $lang, $limit);
        }
        
        // 最適化されたキーワード検索クエリ
        // インデックス idx_buildings_title_search を活用
        $whereConditions = [];
        $params = [];
        
        foreach ($keywords as $keyword) {
            $whereConditions[] = "(b.title LIKE ? OR b.titleEn LIKE ? OR b.location LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        
        $whereSql = "WHERE " . implode(" AND ", $whereConditions);
        
        if ($hasPhotos) {
            $whereSql .= " AND b.has_photo = 1";
        }
        if ($hasVideos) {
            $whereSql .= " AND b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
        }
        
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
                   '' as architectJa,
                   '' as architectEn,
                   '' as architectIds,
                   '' as architectSlugs
            FROM {$this->buildings_table} b
            {$whereSql}
            ORDER BY b.has_photo DESC, b.building_id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        // カウントクエリ
        $countSql = "SELECT COUNT(*) as total FROM {$this->buildings_table} b {$whereSql}";
        
        try {
            // カウント実行
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // データ取得実行
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            
            // データ変換
            $buildings = $this->transformBuildingData($rows, $lang);
            
            $totalPages = ceil($total / $limit);
            
            return [
                'buildings' => $buildings,
                'total' => $total,
                'totalPages' => $totalPages,
                'currentPage' => $page
            ];
            
        } catch (Exception $e) {
            error_log("Optimized keyword search error: " . $e->getMessage());
            return ['buildings' => [], 'total' => 0, 'totalPages' => 0, 'currentPage' => $page];
        }
    }
    
    /**
     * 最適化された建築家検索
     * 必要なJOINのみを使用し、インデックスを活用
     */
    public function searchByArchitectOptimized($architectSlug, $page = 1, $lang = 'ja', $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        // 最適化された建築家検索クエリ
        // インデックス idx_buildings_architect_search を活用
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
            WHERE ia.slug = ?
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
            WHERE ia.slug = ?
        ";
        
        try {
            // カウント実行
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute([$architectSlug]);
            $total = $countStmt->fetch()['total'];
            
            // データ取得実行
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$architectSlug]);
            $rows = $stmt->fetchAll();
            
            // データ変換
            $buildings = $this->transformBuildingData($rows, $lang);
            
            $totalPages = ceil($total / $limit);
            
            return [
                'buildings' => $buildings,
                'total' => $total,
                'totalPages' => $totalPages,
                'currentPage' => $page
            ];
            
        } catch (Exception $e) {
            error_log("Optimized architect search error: " . $e->getMessage());
            return ['buildings' => [], 'total' => 0, 'totalPages' => 0, 'currentPage' => $page];
        }
    }
    
    /**
     * 最適化された全建築物取得
     */
    private function getAllBuildingsOptimized($page, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        if ($hasPhotos) {
            $whereConditions[] = "b.has_photo = 1";
        }
        if ($hasVideos) {
            $whereConditions[] = "b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
        }
        
        $whereSql = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);
        
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
                   '' as architectJa,
                   '' as architectEn,
                   '' as architectIds,
                   '' as architectSlugs
            FROM {$this->buildings_table} b
            {$whereSql}
            ORDER BY b.has_photo DESC, b.building_id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $countSql = "SELECT COUNT(*) as total FROM {$this->buildings_table} b {$whereSql}";
        
        try {
            // カウント実行
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute();
            $total = $countStmt->fetch()['total'];
            
            // データ取得実行
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            
            // データ変換
            $buildings = $this->transformBuildingData($rows, $lang);
            
            $totalPages = ceil($total / $limit);
            
            return [
                'buildings' => $buildings,
                'total' => $total,
                'totalPages' => $totalPages,
                'currentPage' => $page
            ];
            
        } catch (Exception $e) {
            error_log("Optimized all buildings search error: " . $e->getMessage());
            return ['buildings' => [], 'total' => 0, 'totalPages' => 0, 'currentPage' => $page];
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
     * 建築物データを変換
     */
    private function transformBuildingData($rows, $lang) {
        $buildings = [];
        
        foreach ($rows as $row) {
            $building = [
                'building_id' => $row['building_id'],
                'uid' => $row['uid'],
                'title' => $lang === 'ja' ? $row['title'] : ($row['titleEn'] ?: $row['title']),
                'titleEn' => $row['titleEn'],
                'slug' => $row['slug'],
                'lat' => $row['lat'],
                'lng' => $row['lng'],
                'location' => $lang === 'ja' ? $row['location'] : ($row['locationEn'] ?: $row['location']),
                'locationEn' => $row['locationEn'],
                'completionYears' => $row['completionYears'],
                'buildingTypes' => $lang === 'ja' ? $row['buildingTypes'] : ($row['buildingTypesEn'] ?: $row['buildingTypes']),
                'buildingTypesEn' => $row['buildingTypesEn'],
                'prefectures' => $lang === 'ja' ? $row['prefectures'] : ($row['prefecturesEn'] ?: $row['prefectures']),
                'prefecturesEn' => $row['prefecturesEn'],
                'has_photo' => $row['has_photo'],
                'thumbnailUrl' => $row['thumbnailUrl'],
                'youtubeUrl' => $row['youtubeUrl'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'likes' => $row['likes'],
                'architectJa' => $row['architectJa'],
                'architectEn' => $row['architectEn'],
                'architectIds' => $row['architectIds'],
                'architectSlugs' => $row['architectSlugs']
            ];
            
            // 距離情報がある場合は追加
            if (isset($row['distance'])) {
                $building['distance'] = round($row['distance'], 2);
            }
            
            $buildings[] = $building;
        }
        
        return $buildings;
    }
}
?>
