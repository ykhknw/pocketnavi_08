<?php
/**
 * 検索履歴filters移行（architect全件）
 * 
 * 対象: global_search_historyテーブルのpageType="architect"の全データ
 * 目的: 英語ユーザー向け表示用データを一括追加
 */

// エラー表示を有効にする（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);

class AllArchitectsMigrator {
    private $host = 'mysql320.phy.heteml.lan';
    private $db_name = '_shinkenchiku_02';
    private $username = '_shinkenchiku_02';
    private $password = 'ipgdfahuqbg3';
    private $db;
    
    // バッチサイズ（一度に処理する件数）
    private $batchSize = 50;
    
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
            echo "<p style='color: green;'>✓ データベース接続成功</p>";
        } catch (PDOException $e) {
            die("<p style='color: red;'>✗ データベース接続エラー: " . $e->getMessage() . "</p>");
        }
    }
    
    /**
     * 移行処理の実行
     */
    public function migrateAll() {
        echo "<h2>検索履歴filters移行（architect全件）</h2>";
        echo "<hr>";
        
        // 統計情報の表示
        $this->showStatistics();
        
        // 移行対象データの取得
        $totalCount = $this->getTotalCount();
        echo "<p><strong>移行対象総数:</strong> {$totalCount}件</p>";
        
        if ($totalCount == 0) {
            echo "<p style='color: orange;'>移行対象のデータがありません。</p>";
            return;
        }
        
        // バッチ処理の実行
        $offset = 0;
        $successCount = 0;
        $errorCount = 0;
        $processedCount = 0;
        
        echo "<h3>移行処理開始</h3>";
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
        
        while ($offset < $totalCount) {
            $batch = $this->getBatch($offset, $this->batchSize);
            
            if (empty($batch)) {
                break;
            }
            
            foreach ($batch as $record) {
                try {
                    $result = $this->migrateRecord($record);
                    if ($result) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                    $processedCount++;
                    
                    // 進捗表示
                    if ($processedCount % 10 == 0) {
                        $progress = round(($processedCount / $totalCount) * 100, 1);
                        echo "進捗: {$processedCount}/{$totalCount} ({$progress}%) - 成功: {$successCount}, エラー: {$errorCount}<br>";
                        flush(); // リアルタイム表示
                    }
                    
                } catch (Exception $e) {
                    $errorCount++;
                    echo "<span style='color: red;'>エラー (ID: {$record['id']}): " . htmlspecialchars($e->getMessage()) . "</span><br>";
                }
            }
            
            $offset += $this->batchSize;
            
            // メモリ使用量の監視
            $memoryUsage = memory_get_usage(true);
            $memoryMB = round($memoryUsage / 1024 / 1024, 2);
            echo "<small>メモリ使用量: {$memoryMB}MB</small><br>";
            
            // メモリ使用量が多すぎる場合は処理を一時停止
            if ($memoryMB > 100) {
                echo "<span style='color: orange;'>メモリ使用量が多いため、処理を一時停止します。</span><br>";
                break;
            }
        }
        
        echo "</div>";
        
        // 結果表示
        echo "<h3>移行結果</h3>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>項目</th><th>件数</th></tr>";
        echo "<tr><td>処理済み</td><td>{$processedCount}件</td></tr>";
        echo "<tr><td>成功</td><td style='color: green;'>{$successCount}件</td></tr>";
        echo "<tr><td>エラー</td><td style='color: red;'>{$errorCount}件</td></tr>";
        echo "<tr><td>成功率</td><td>" . ($processedCount > 0 ? round(($successCount / $processedCount) * 100, 1) : 0) . "%</td></tr>";
        echo "</table>";
        
        // 移行後の統計
        $this->showPostMigrationStatistics();
    }
    
    /**
     * 統計情報の表示
     */
    public function showStatistics() {
        echo "<h3>移行前統計</h3>";
        
        // 全体のarchitect検索数
        $sql = "
            SELECT COUNT(*) as total
            FROM global_search_history
            WHERE search_type = 'architect'
            AND JSON_EXTRACT(filters, '$.pageType') = 'architect'
        ";
        $stmt = $this->db->query($sql);
        $total = $stmt->fetch()['total'];
        echo "<p><strong>architect検索総数:</strong> {$total}件</p>";
        
        // 移行対象数
        $sql = "
            SELECT COUNT(*) as target
            FROM global_search_history
            WHERE search_type = 'architect'
            AND JSON_EXTRACT(filters, '$.pageType') = 'architect'
            AND JSON_EXTRACT(filters, '$.architect_id') IS NOT NULL
            AND JSON_EXTRACT(filters, '$.title_en') IS NULL
        ";
        $stmt = $this->db->query($sql);
        $target = $stmt->fetch()['target'];
        echo "<p><strong>移行対象数:</strong> {$target}件</p>";
        
        // 既に移行済み数
        $sql = "
            SELECT COUNT(*) as migrated
            FROM global_search_history
            WHERE search_type = 'architect'
            AND JSON_EXTRACT(filters, '$.pageType') = 'architect'
            AND JSON_EXTRACT(filters, '$.title_en') IS NOT NULL
        ";
        $stmt = $this->db->query($sql);
        $migrated = $stmt->fetch()['migrated'];
        echo "<p><strong>既に移行済み数:</strong> {$migrated}件</p>";
        
        echo "<hr>";
    }
    
    /**
     * 移行後の統計
     */
    public function showPostMigrationStatistics() {
        echo "<h3>移行後統計</h3>";
        
        $sql = "
            SELECT COUNT(*) as migrated
            FROM global_search_history
            WHERE search_type = 'architect'
            AND JSON_EXTRACT(filters, '$.pageType') = 'architect'
            AND JSON_EXTRACT(filters, '$.title_en') IS NOT NULL
        ";
        $stmt = $this->db->query($sql);
        $migrated = $stmt->fetch()['migrated'];
        echo "<p><strong>移行済み数:</strong> {$migrated}件</p>";
        
        // サンプルデータの表示
        $sql = "
            SELECT id, JSON_EXTRACT(filters, '$.title') as title_ja, JSON_EXTRACT(filters, '$.title_en') as title_en
            FROM global_search_history
            WHERE search_type = 'architect'
            AND JSON_EXTRACT(filters, '$.title_en') IS NOT NULL
            ORDER BY id DESC
            LIMIT 5
        ";
        $stmt = $this->db->query($sql);
        $samples = $stmt->fetchAll();
        
        echo "<h4>最新の移行データ（サンプル）</h4>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ID</th><th>日本語タイトル</th><th>英語タイトル</th></tr>";
        foreach ($samples as $sample) {
            echo "<tr>";
            echo "<td>{$sample['id']}</td>";
            echo "<td>" . htmlspecialchars($sample['title_ja']) . "</td>";
            echo "<td>" . htmlspecialchars($sample['title_en']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    /**
     * 移行対象の総数を取得
     */
    private function getTotalCount() {
        $sql = "
            SELECT COUNT(*) as total
            FROM global_search_history
            WHERE search_type = 'architect'
            AND JSON_EXTRACT(filters, '$.pageType') = 'architect'
            AND JSON_EXTRACT(filters, '$.architect_id') IS NOT NULL
            AND JSON_EXTRACT(filters, '$.title_en') IS NULL
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetch()['total'];
    }
    
    /**
     * バッチデータの取得
     */
    private function getBatch($offset, $limit) {
        $sql = "
            SELECT id, filters
            FROM global_search_history
            WHERE search_type = 'architect'
            AND JSON_EXTRACT(filters, '$.pageType') = 'architect'
            AND JSON_EXTRACT(filters, '$.architect_id') IS NOT NULL
            AND JSON_EXTRACT(filters, '$.title_en') IS NULL
            ORDER BY id
            LIMIT {$limit} OFFSET {$offset}
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
            return false;
        }
        
        $architectId = $filters['architect_id'] ?? null;
        if (!$architectId) {
            return false;
        }
        
        // 建築家データの取得
        $architectData = $this->getArchitectData($architectId);
        if (!$architectData) {
            return false;
        }
        
        // 新しいfiltersデータの構築
        $newFilters = $filters;
        $newFilters['architect_slug'] = $architectData['slug'];
        $newFilters['architect_name_ja'] = $architectData['name_ja'];
        $newFilters['architect_name_en'] = $architectData['name_en'];
        
        // 日本語検索の場合のみtitle_enを追加
        if (($filters['lang'] ?? 'ja') === 'ja') {
            $newFilters['title_en'] = $architectData['name_en'];
        }
        
        // データベース更新
        $updateSql = "UPDATE global_search_history SET filters = ? WHERE id = ?";
        $updateStmt = $this->db->prepare($updateSql);
        return $updateStmt->execute([json_encode($newFilters), $record['id']]);
    }
    
    /**
     * 建築家データの取得
     */
    private function getArchitectData($architectId) {
        $sql = "SELECT individual_architect_id, slug, name_ja, name_en FROM individual_architects_3 WHERE individual_architect_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$architectId]);
        return $stmt->fetch();
    }
}

// HTMLヘッダー
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>検索履歴移行（architect全件）</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>

<div class="warning">
    <strong>⚠️ 注意:</strong> このスクリプトは本番データベースのarchitect検索データを一括移行します。実行前に必ずバックアップを取得してください。
</div>

<?php
// 実行部分
try {
    $migrator = new AllArchitectsMigrator();
    
    if (isset($_GET['execute']) && $_GET['execute'] === 'yes') {
        $migrator->migrateAll();
        echo "<div class='success'><strong>✓ 移行処理が完了しました！</strong></div>";
    } else {
        echo "<h3>移行前の確認</h3>";
        $migrator->showStatistics();
        echo "<p><a href='?execute=yes' style='background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px;'>全件移行を実行する</a></p>";
        echo "<div class='warning'><strong>注意:</strong> 上記ボタンをクリックすると、移行処理が開始されます。</div>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

</body>
</html>
