<?php
/**
 * 検索履歴テーブルのfiltersカラムを新形式に移行するスクリプト
 * 
 * 対象: global_search_historyテーブルのsearch_type="building"のデータ
 * 目的: 英語ユーザー向け表示用データ（title_en等）を追加
 */

class SearchHistoryMigrator {
    private $host = 'mysql320.phy.heteml.lan';
    private $db_name = '_shinkenchiku_02';
    private $username = '_shinkenchiku_02';
    private $password = 'ipgdfahuqbg3';
    private $db;
    
    public function __construct() {
        $this->connectDatabase();
    }
    
    /**
     * データベース接続
     */
    private function connectDatabase() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $this->db = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            echo "データベース接続成功\n";
        } catch (PDOException $e) {
            die("データベース接続エラー: " . $e->getMessage() . "\n");
        }
    }
    
    /**
     * 移行処理の実行
     */
    public function migrate() {
        echo "=== 検索履歴filters移行開始 ===\n";
        
        // 対象データの取得
        $targetRecords = $this->getTargetRecords();
        echo "対象レコード数: " . count($targetRecords) . "\n";
        
        if (empty($targetRecords)) {
            echo "移行対象のデータがありません\n";
            return;
        }
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($targetRecords as $record) {
            try {
                $result = $this->migrateRecord($record);
                if ($result) {
                    $successCount++;
                    if ($successCount % 100 == 0) {
                        echo "処理済み: {$successCount}件\n";
                    }
                } else {
                    $errorCount++;
                }
            } catch (Exception $e) {
                $errorCount++;
                echo "エラー (ID: {$record['id']}): " . $e->getMessage() . "\n";
            }
        }
        
        echo "=== 移行完了 ===\n";
        echo "成功: {$successCount}件\n";
        echo "エラー: {$errorCount}件\n";
    }
    
    /**
     * 移行対象レコードの取得
     */
    private function getTargetRecords() {
        $sql = "
            SELECT id, filters
            FROM global_search_history
            WHERE search_type = 'building'
            AND JSON_EXTRACT(filters, '$.pageType') = 'building'
            AND JSON_EXTRACT(filters, '$.building_id') IS NOT NULL
            AND JSON_EXTRACT(filters, '$.title_en') IS NULL
            ORDER BY id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 個別レコードの移行
     */
    private function migrateRecord($record) {
        $filters = json_decode($record['filters'], true);
        
        if (!$filters) {
            echo "JSON解析エラー (ID: {$record['id']})\n";
            return false;
        }
        
        // building_idの取得
        $buildingId = $filters['building_id'] ?? null;
        if (!$buildingId) {
            echo "building_idが見つかりません (ID: {$record['id']})\n";
            return false;
        }
        
        // 建築物データの取得
        $buildingData = $this->getBuildingData($buildingId);
        if (!$buildingData) {
            echo "建築物データが見つかりません (building_id: {$buildingId})\n";
            return false;
        }
        
        // 新しいfiltersデータの構築
        $newFilters = $filters;
        $newFilters['building_slug'] = $buildingData['slug'];
        $newFilters['building_title_ja'] = $buildingData['title'];
        $newFilters['building_title_en'] = $buildingData['titleEn'];
        
        // 日本語検索の場合のみtitle_enを追加
        if (($filters['lang'] ?? 'ja') === 'ja') {
            $newFilters['title_en'] = $buildingData['titleEn'];
        }
        
        // データベース更新
        $updateSql = "UPDATE global_search_history SET filters = ? WHERE id = ?";
        $updateStmt = $this->db->prepare($updateSql);
        $result = $updateStmt->execute([json_encode($newFilters), $record['id']]);
        
        if ($result) {
            echo "移行成功 (ID: {$record['id']}, building_id: {$buildingId})\n";
            return true;
        } else {
            echo "更新エラー (ID: {$record['id']})\n";
            return false;
        }
    }
    
    /**
     * 建築物データの取得
     */
    private function getBuildingData($buildingId) {
        $sql = "
            SELECT building_id, slug, title, titleEn
            FROM buildings_table_3
            WHERE building_id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$buildingId]);
        return $stmt->fetch();
    }
    
    /**
     * 移行前の統計情報表示
     */
    public function showStatistics() {
        echo "=== 移行前統計 ===\n";
        
        // 全体のbuilding検索数
        $sql = "
            SELECT COUNT(*) as total
            FROM global_search_history
            WHERE search_type = 'building'
        ";
        $stmt = $this->db->query($sql);
        $total = $stmt->fetch()['total'];
        echo "building検索総数: {$total}件\n";
        
        // 移行対象数
        $sql = "
            SELECT COUNT(*) as target
            FROM global_search_history
            WHERE search_type = 'building'
            AND JSON_EXTRACT(filters, '$.pageType') = 'building'
            AND JSON_EXTRACT(filters, '$.building_id') IS NOT NULL
            AND JSON_EXTRACT(filters, '$.title_en') IS NULL
        ";
        $stmt = $this->db->query($sql);
        $target = $stmt->fetch()['target'];
        echo "移行対象数: {$target}件\n";
        
        // 既に移行済み数
        $sql = "
            SELECT COUNT(*) as migrated
            FROM global_search_history
            WHERE search_type = 'building'
            AND JSON_EXTRACT(filters, '$.title_en') IS NOT NULL
        ";
        $stmt = $this->db->query($sql);
        $migrated = $stmt->fetch()['migrated'];
        echo "既に移行済み数: {$migrated}件\n";
        
        echo "==================\n";
    }
    
    /**
     * 移行後の検証
     */
    public function verifyMigration() {
        echo "=== 移行後検証 ===\n";
        
        // 移行後の統計
        $sql = "
            SELECT COUNT(*) as migrated
            FROM global_search_history
            WHERE search_type = 'building'
            AND JSON_EXTRACT(filters, '$.title_en') IS NOT NULL
        ";
        $stmt = $this->db->query($sql);
        $migrated = $stmt->fetch()['migrated'];
        echo "移行済み数: {$migrated}件\n";
        
        // サンプルデータの表示
        $sql = "
            SELECT id, filters
            FROM global_search_history
            WHERE search_type = 'building'
            AND JSON_EXTRACT(filters, '$.title_en') IS NOT NULL
            LIMIT 3
        ";
        $stmt = $this->db->query($sql);
        $samples = $stmt->fetchAll();
        
        echo "サンプルデータ:\n";
        foreach ($samples as $sample) {
            $filters = json_decode($sample['filters'], true);
            echo "ID: {$sample['id']}\n";
            echo "  title: " . ($filters['title'] ?? 'N/A') . "\n";
            echo "  title_en: " . ($filters['title_en'] ?? 'N/A') . "\n";
            echo "  building_id: " . ($filters['building_id'] ?? 'N/A') . "\n";
            echo "---\n";
        }
        
        echo "==================\n";
    }
}

// 実行部分
try {
    $migrator = new SearchHistoryMigrator();
    
    // 統計情報表示
    $migrator->showStatistics();
    
    // 確認
    echo "移行を実行しますか？ (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) === 'y' || trim($line) === 'Y') {
        // 移行実行
        $migrator->migrate();
        
        // 検証
        $migrator->verifyMigration();
    } else {
        echo "移行をキャンセルしました\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
