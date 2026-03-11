<?php
// HETEML用データベース設定
class Database {
    private $host = 'localhost';
    private $db_name = '_shinkenchiku_02';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// データベース接続の取得
function getDB() {
    static $db = null;
    if ($db === null) {
        $database = new Database();
        $db = $database->getConnection();
    }
    return $db;
}

// PDO接続の取得（functions_new.php用）
function getDatabaseConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=_shinkenchiku_02;charset=utf8mb4",
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}
?>
