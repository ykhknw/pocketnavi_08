<?php

/**
 * エラーハンドリングサービス
 */
class ErrorHandler {
    
    /**
     * データベースエラーを処理
     */
    public static function handleDatabaseError($e, $context = '') {
        $message = "Database error" . ($context ? " in {$context}" : "") . ": " . $e->getMessage();
        error_log($message);
        error_log("Stack trace: " . $e->getTraceAsString());
        
        return [
            'error' => true,
            'message' => 'データベースエラーが発生しました',
            'code' => 'DATABASE_ERROR'
        ];
    }
    
    /**
     * 検索エラーを処理
     */
    public static function handleSearchError($e, $context = '') {
        $message = "Search error" . ($context ? " in {$context}" : "") . ": " . $e->getMessage();
        error_log($message);
        error_log("Stack trace: " . $e->getTraceAsString());
        
        return [
            'error' => true,
            'message' => '検索中にエラーが発生しました',
            'code' => 'SEARCH_ERROR'
        ];
    }
    
    /**
     * データ変換エラーを処理
     */
    public static function handleDataTransformError($e, $data = null) {
        $message = "Data transform error: " . $e->getMessage();
        error_log($message);
        if ($data) {
            error_log("Data: " . print_r($data, true));
        }
        error_log("Stack trace: " . $e->getTraceAsString());
        
        return [
            'error' => true,
            'message' => 'データの変換中にエラーが発生しました',
            'code' => 'DATA_TRANSFORM_ERROR'
        ];
    }
    
    /**
     * 空の検索結果を返す
     */
    public static function getEmptySearchResult($page = 1) {
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page,
            'error' => false
        ];
    }
    
    /**
     * エラーログを記録
     */
    public static function logError($message, $context = '', $data = null) {
        $logMessage = $message;
        if ($context) {
            $logMessage .= " (Context: {$context})";
        }
        if ($data) {
            $logMessage .= " (Data: " . print_r($data, true) . ")";
        }
        
        error_log($logMessage);
    }
}
