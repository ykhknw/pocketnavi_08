<?php
/**
 * 建築家検索履歴filters移行テスト（1件のみ）
 * 
 * 対象: ID 24557のレコード
 * 目的: 建築家データの移行処理の動作確認
 */

// エラー表示を有効にする（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);

class ArchitectMigrationTester {
    private $host = 'mysql320.phy.heteml.lan';
    private $db_name = '_shinkenchiku_02';
    private $username = '_shinkenchiku_02';
    private $password = 'ipgdfahuqbg3';
    private $db;
    
    // テスト対象のID
    private $testId = 24557;
    
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
     * テスト実行
     */
    public function runTest() {
        echo "<h2>建築家検索履歴filters移行テスト（1件のみ）</h2>";
        echo "<hr>";
        
        // 1. 対象レコードの取得
        $record = $this->getTargetRecord();
        if (!$record) {
            echo "<p style='color: red;'>✗ 対象レコードが見つかりません (ID: {$this->testId})</p>";
            return;
        }
        
        echo "<h3>1. 対象レコード情報</h3>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>項目</th><th>値</th></tr>";
        echo "<tr><td>ID</td><td>{$record['id']}</td></tr>";
        echo "<tr><td>Query</td><td>" . htmlspecialchars($record['query']) . "</td></tr>";
        echo "<tr><td>Search Type</td><td>{$record['search_type']}</td></tr>";
        echo "<tr><td>Searched At</td><td>{$record['searched_at']}</td></tr>";
        echo "</table>";
        
        // 2. 現在のfiltersデータの表示
        $currentFilters = json_decode($record['filters'], true);
        echo "<h3>2. 現在のfiltersデータ（移行前）</h3>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
        echo htmlspecialchars(json_encode($currentFilters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "</pre>";
        
        // 3. architect_idの確認
        $architectId = $currentFilters['architect_id'] ?? null;
        if (!$architectId) {
            echo "<p style='color: red;'>✗ architect_idが見つかりません</p>";
            return;
        }
        
        echo "<p><strong>Architect ID:</strong> {$architectId}</p>";
        
        // 4. 建築家データの取得
        $architectData = $this->getArchitectData($architectId);
        if (!$architectData) {
            echo "<p style='color: red;'>✗ 建築家データが見つかりません (architect_id: {$architectId})</p>";
            return;
        }
        
        echo "<h3>3. 建築家データ</h3>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>項目</th><th>値</th></tr>";
        echo "<tr><td>Architect ID</td><td>{$architectData['individual_architect_id']}</td></tr>";
        echo "<tr><td>Slug</td><td>" . htmlspecialchars($architectData['slug']) . "</td></tr>";
        echo "<tr><td>Name (JA)</td><td>" . htmlspecialchars($architectData['name_ja']) . "</td></tr>";
        echo "<tr><td>Name (EN)</td><td>" . htmlspecialchars($architectData['name_en']) . "</td></tr>";
        echo "</table>";
        
        // 5. 新しいfiltersデータの構築
        $newFilters = $currentFilters;
        $newFilters['architect_slug'] = $architectData['slug'];
        $newFilters['architect_name_ja'] = $architectData['name_ja'];
        $newFilters['architect_name_en'] = $architectData['name_en'];
        
        // 日本語検索の場合のみtitle_enを追加
        if (($currentFilters['lang'] ?? 'ja') === 'ja') {
            $newFilters['title_en'] = $architectData['name_en'];
        }
        
        echo "<h3>4. 新しいfiltersデータ（移行後）</h3>";
        echo "<pre style='background: #e8f5e8; padding: 10px; border: 1px solid #4caf50;'>";
        echo htmlspecialchars(json_encode($newFilters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "</pre>";
        
        // 6. 実際の更新処理
        echo "<h3>5. データベース更新</h3>";
        
        if (isset($_GET['execute']) && $_GET['execute'] === 'yes') {
            // 実際に更新を実行
            $result = $this->updateRecord($record['id'], $newFilters);
            if ($result) {
                echo "<p style='color: green;'>✓ 更新成功！</p>";
                
                // 更新後の確認
                $updatedRecord = $this->getTargetRecord();
                $updatedFilters = json_decode($updatedRecord['filters'], true);
                
                echo "<h3>6. 更新後の確認</h3>";
                echo "<pre style='background: #e8f5e8; padding: 10px; border: 1px solid #4caf50;'>";
                echo htmlspecialchars(json_encode($updatedFilters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo "</pre>";
                
                echo "<p style='color: green; font-weight: bold;'>✓ 建築家移行テスト完了！</p>";
            } else {
                echo "<p style='color: red;'>✗ 更新に失敗しました</p>";
            }
        } else {
            // プレビューモード
            echo "<p style='color: orange;'>⚠️ プレビューモードです。実際の更新は実行されていません。</p>";
            echo "<p><a href='?execute=yes' style='background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>実際に更新を実行する</a></p>";
        }
    }
    
    /**
     * 対象レコードの取得
     */
    private function getTargetRecord() {
        $sql = "SELECT * FROM global_search_history WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->testId]);
        return $stmt->fetch();
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
    
    /**
     * レコードの更新
     */
    private function updateRecord($id, $newFilters) {
        $sql = "UPDATE global_search_history SET filters = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([json_encode($newFilters), $id]);
    }
}

// HTMLヘッダー
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>建築家検索履歴移行テスト</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        pre { overflow-x: auto; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>

<div class="warning">
    <strong>⚠️ 注意:</strong> このスクリプトは本番データベースに接続します。テスト用の1件のみの処理ですが、実行前に必ずバックアップを取得してください。
</div>

<?php
// 実行部分
try {
    $tester = new ArchitectMigrationTester();
    $tester->runTest();
} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

</body>
</html>
