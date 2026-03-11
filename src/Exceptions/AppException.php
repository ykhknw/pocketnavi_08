<?php

/**
 * アプリケーション基底例外クラス
 */
class AppException extends Exception {
    protected $context = [];
    protected $errorCode = null;
    
    public function __construct($message = "", $code = 0, $previous = null, $context = [], $errorCode = null) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->errorCode = $errorCode;
    }
    
    public function getContext() {
        return $this->context;
    }
    
    public function getErrorCode() {
        return $this->errorCode;
    }
    
    public function toArray() {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'errorCode' => $this->errorCode,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString()
        ];
    }
}

/**
 * データベース例外クラス
 */
class DatabaseException extends AppException {
    public function __construct($message = "", $code = 0, $previous = null, $context = []) {
        parent::__construct($message, $code, $previous, $context, 'DATABASE_ERROR');
    }
}

/**
 * バリデーション例外クラス
 */
class ValidationException extends AppException {
    public function __construct($message = "", $code = 0, $previous = null, $context = []) {
        parent::__construct($message, $code, $previous, $context, 'VALIDATION_ERROR');
    }
}

/**
 * セキュリティ例外クラス
 */
class SecurityException extends AppException {
    public function __construct($message = "", $code = 0, $previous = null, $context = []) {
        parent::__construct($message, $code, $previous, $context, 'SECURITY_ERROR');
    }
}

/**
 * 設定例外クラス
 */
class ConfigurationException extends AppException {
    public function __construct($message = "", $code = 0, $previous = null, $context = []) {
        parent::__construct($message, $code, $previous, $context, 'CONFIGURATION_ERROR');
    }
}

/**
 * ファイル例外クラス
 */
class FileException extends AppException {
    public function __construct($message = "", $code = 0, $previous = null, $context = []) {
        parent::__construct($message, $code, $previous, $context, 'FILE_ERROR');
    }
}
