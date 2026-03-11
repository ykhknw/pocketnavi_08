// check_local_setup.php
<?php
echo "データベース接続確認\n";
require_once 'config/database_unified.php';
$pdo = getDatabaseConnection();
$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
echo "接続DB: $dbName\n";

echo "\n環境変数確認\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: '未設定') . "\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: '未設定') . "\n";