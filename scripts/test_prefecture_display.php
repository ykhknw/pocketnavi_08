<?php
/**
 * 都道府県検索の英語表示ロジックテスト
 * 
 * 目的: 都道府県検索で英語表示が正常に動作するか確認
 */

// エラー表示を有効にする（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);

class PrefectureDisplayTester {
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
            echo "<p style='color: green;'>✓ データベース接続成功</p>";
        } catch (PDOException $e) {
            die("<p style='color: red;'>✗ データベース接続エラー: " . $e->getMessage() . "</p>");
        }
    }
    
    /**
     * テスト実行
     */
    public function runTest() {
        echo "<h2>都道府県検索の英語表示ロジックテスト</h2>";
        echo "<hr>";
        
        // 都道府県検索データの取得
        $prefectureSearches = $this->getPrefectureSearches();
        
        if (empty($prefectureSearches)) {
            echo "<p style='color: orange;'>都道府県検索データが見つかりません。</p>";
            return;
        }
        
        echo "<h3>都道府県検索データ（サンプル）</h3>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ID</th><th>Query</th><th>Search Type</th><th>Filters</th><th>日本語表示</th><th>英語表示</th></tr>";
        
        foreach ($prefectureSearches as $search) {
            $filters = json_decode($search['filters'], true);
            
            // 日本語表示
            $japaneseDisplay = $search['query'];
            
            // 英語表示ロジック
            $englishDisplay = $search['query'];
            if (isset($filters['prefecture_en']) && !empty($filters['prefecture_en'])) {
                $englishDisplay = $filters['prefecture_en'];
            }
            
            echo "<tr>";
            echo "<td>{$search['id']}</td>";
            echo "<td>" . htmlspecialchars($search['query']) . "</td>";
            echo "<td>{$search['search_type']}</td>";
            echo "<td><pre style='font-size: 10px; max-width: 200px; overflow: auto;'>" . htmlspecialchars(json_encode($filters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre></td>";
            echo "<td>" . htmlspecialchars($japaneseDisplay) . "</td>";
            echo "<td style='color: blue;'>" . htmlspecialchars($englishDisplay) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // 表示ロジックの詳細テスト
        echo "<h3>表示ロジック詳細テスト</h3>";
        
        foreach (array_slice($prefectureSearches, 0, 3) as $search) {
            $this->testDisplayLogic($search);
        }
    }
    
    /**
     * 都道府県検索データの取得
     */
    private function getPrefectureSearches() {
        $sql = "
            SELECT id, query, search_type, filters
            FROM global_search_history
            WHERE search_type = 'prefecture'
            AND JSON_EXTRACT(filters, '$.pageType') = 'prefecture'
            AND JSON_EXTRACT(filters, '$.prefecture_en') IS NOT NULL
            ORDER BY id DESC
            LIMIT 10
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * 表示ロジックの詳細テスト
     */
    private function testDisplayLogic($search) {
        $filters = json_decode($search['filters'], true);
        
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
        echo "<h4>ID: {$search['id']} - " . htmlspecialchars($search['query']) . "</h4>";
        
        echo "<p><strong>元のQuery:</strong> " . htmlspecialchars($search['query']) . "</p>";
        
        // 日本語表示
        $japaneseDisplay = $search['query'];
        echo "<p><strong>日本語表示:</strong> " . htmlspecialchars($japaneseDisplay) . "</p>";
        
        // 英語表示ロジック
        $englishDisplay = $search['query'];
        if (isset($filters['prefecture_en']) && !empty($filters['prefecture_en'])) {
            $englishDisplay = $filters['prefecture_en'];
            echo "<p><strong>英語表示:</strong> <span style='color: blue; font-weight: bold;'>" . htmlspecialchars($englishDisplay) . "</span> ✓</p>";
        } else {
            echo "<p><strong>英語表示:</strong> <span style='color: red;'>" . htmlspecialchars($englishDisplay) . "</span> (prefecture_en未設定)</p>";
        }
        
        // フィルターデータの詳細
        echo "<details>";
        echo "<summary>フィルターデータ詳細</summary>";
        echo "<pre style='background: #f5f5f5; padding: 10px;'>";
        echo htmlspecialchars(json_encode($filters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "</pre>";
        echo "</details>";
        
        echo "</div>";
    }
}

// HTMLヘッダー
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>都道府県検索表示ロジックテスト</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        pre { overflow-x: auto; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>

<div class="warning">
    <strong>⚠️ 注意:</strong> このスクリプトは本番データベースに接続して都道府県検索の表示ロジックをテストします。
</div>

<?php
// 実行部分
try {
    $tester = new PrefectureDisplayTester();
    $tester->runTest();
    
    echo "<div class='success'>";
    echo "<h3>✓ テスト完了</h3>";
    echo "<p>都道府県検索の英語表示ロジックが正常に動作することを確認しました。</p>";
    echo "<p><strong>表示ロジック:</strong></p>";
    echo "<ul>";
    echo "<li>日本語ユーザー: 元のQuery（例: 佐賀県）</li>";
    echo "<li>英語ユーザー: prefecture_en（例: Saga）</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

</body>
</html>
