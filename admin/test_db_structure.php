<?php
/**
 * データベース構造確認スクリプト
 */

// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    
    echo "✓ データベース接続成功\n\n";
    
    // テーブル構造を確認
    echo "=== global_search_history テーブル構造 ===\n";
    $stmt = $pdo->query("DESCRIBE global_search_history");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "カラム: {$column['Field']} | 型: {$column['Type']} | NULL: {$column['Null']} | デフォルト: {$column['Default']}\n";
    }
    
    echo "\n=== サンプルデータ（最初の3件） ===\n";
    $stmt = $pdo->query("SELECT * FROM global_search_history LIMIT 3");
    $samples = $stmt->fetchAll();
    
    foreach ($samples as $i => $sample) {
        echo "レコード " . ($i + 1) . ":\n";
        foreach ($sample as $key => $value) {
            echo "  {$key}: " . (is_null($value) ? 'NULL' : $value) . "\n";
        }
        echo "\n";
    }
    
    echo "\n=== テーブル統計 ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total_records FROM global_search_history");
    $count = $stmt->fetch();
    echo "総レコード数: {$count['total_records']}\n";
    
} catch (PDOException $e) {
    echo "データベース接続エラー: " . $e->getMessage() . "\n";
}
?>
