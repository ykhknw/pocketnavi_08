<?php
/**
 * 統一されたデータベース接続関数
 * アプリケーション全体で使用される唯一のgetDB()関数
 */

function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            require_once __DIR__ . '/../Utils/DatabaseConnection.php';
            $db = DatabaseConnection::getInstance();
            $pdo = $db->getConnection(); // getPdo()ではなくgetConnection()を使用
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    return $pdo;
}