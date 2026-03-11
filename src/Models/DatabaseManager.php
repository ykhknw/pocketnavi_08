<?php
/**
 * データベース管理クラス
 * データベース接続とクエリ実行を統一的に管理
 */
class DatabaseManager {
    private static $instance = null;
    private $connection = null;
    
    private function __construct() {
        $this->connection = $this->createConnection();
    }
    
    /**
     * シングルトンインスタンスを取得
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * データベース接続を作成
     */
    private function createConnection() {
        try {
            $config = getDatabaseConfig();
            $dsn = "mysql:host={$config['host']};dbname={$config['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("データベース接続に失敗しました");
        }
    }
    
    /**
     * データベース接続を取得
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * クエリを実行して結果を取得
     */
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Query execution error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw new Exception("クエリの実行に失敗しました");
        }
    }
    
    /**
     * カウントクエリを実行
     */
    public function executeCountQuery($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return (int)$result['total'];
        } catch (PDOException $e) {
            error_log("Count query execution error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw new Exception("カウントクエリの実行に失敗しました");
        }
    }
    
    /**
     * 単一レコードを取得
     */
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Fetch one error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw new Exception("レコードの取得に失敗しました");
        }
    }
    
    /**
     * トランザクションを開始
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * トランザクションをコミット
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * トランザクションをロールバック
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * 接続を閉じる
     */
    public function close() {
        $this->connection = null;
    }
}
?>

