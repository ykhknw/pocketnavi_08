<?php

require_once __DIR__ . '/DatabaseConnection.php';
require_once __DIR__ . '/EnvironmentLoader.php';

/**
 * データベースヘルパー関数
 * 既存のコードとの互換性を保ちながら、新しい接続システムを使用
 */
class DatabaseHelper {
    
    /**
     * データベース接続の取得（既存のgetDB()関数の代替）
     * @return PDO
     * @throws Exception
     */
    public static function getDB() {
        $dbConnection = DatabaseConnection::getInstance();
        return $dbConnection->getConnection();
    }
    
    /**
     * データベース接続の取得（既存のgetDatabaseConnection()関数の代替）
     * @return PDO
     * @throws Exception
     */
    public static function getDatabaseConnection() {
        return self::getDB();
    }
    
    /**
     * 接続のテスト
     * @return bool
     */
    public static function testConnection() {
        try {
            $dbConnection = DatabaseConnection::getInstance();
            return $dbConnection->testConnection();
        } catch (Exception $e) {
            error_log("Database connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 接続状態の確認
     * @return bool
     */
    public static function isConnected() {
        try {
            $dbConnection = DatabaseConnection::getInstance();
            return $dbConnection->isConnected();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 接続の再確立
     */
    public static function reconnect() {
        try {
            $dbConnection = DatabaseConnection::getInstance();
            $dbConnection->reconnect();
        } catch (Exception $e) {
            error_log("Database reconnection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 設定情報の取得（デバッグ用）
     * @return array
     */
    public static function getConfig() {
        try {
            $dbConnection = DatabaseConnection::getInstance();
            return $dbConnection->getConfig();
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * グローバル関数（既存コードとの互換性のため）
 */

/**
 * データベース接続の取得（既存のgetDB()関数）
 * @return PDO
 * @throws Exception
 */
if (!function_exists('getDB')) {
    function getDB() {
        return DatabaseHelper::getDB();
    }
}

/**
 * データベース接続の取得（既存のgetDatabaseConnection()関数）
 * @return PDO
 * @throws Exception
 */
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() {
        return DatabaseHelper::getDatabaseConnection();
    }
}

/**
 * データベース接続のテスト
 * @return bool
 */
if (!function_exists('testDatabaseConnection')) {
    function testDatabaseConnection() {
        return DatabaseHelper::testConnection();
    }
}

/**
 * データベース接続状態の確認
 * @return bool
 */
if (!function_exists('isDatabaseConnected')) {
    function isDatabaseConnected() {
        return DatabaseHelper::isConnected();
    }
}

/**
 * データベース設定情報の取得（デバッグ用）
 * @return array
 */
if (!function_exists('getDatabaseInfo')) {
    function getDatabaseInfo() {
        try {
            $config = DatabaseHelper::getConfig();
            $isConnected = DatabaseHelper::isConnected();
            
            return [
                'config' => $config,
                'connected' => $isConnected,
                'env_file' => EnvironmentLoader::getEnvFile(),
                'environment' => EnvironmentLoader::get('APP_ENV', 'unknown')
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'connected' => false
            ];
        }
    }
}
