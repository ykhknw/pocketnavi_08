<?php
/**
 * 入力検証クラス
 * SQLインジェクション、XSS、ファイルアップロード攻撃を防止
 */
class InputValidator {
    private $errors = [];
    
    /**
     * 文字列の検証とサニタイズ
     */
    public function validateString($input, $fieldName, $options = []) {
        $maxLength = $options['max_length'] ?? 255;
        $minLength = $options['min_length'] ?? 0;
        $pattern = $options['pattern'] ?? null;
        $required = $options['required'] ?? false;
        
        // 必須チェック
        if ($required && (empty($input) || $input === null)) {
            $this->errors[$fieldName] = "{$fieldName}は必須です。";
            return false;
        }
        
        // 空の場合はスキップ
        if (empty($input)) {
            return '';
        }
        
        // 型チェック
        if (!is_string($input)) {
            $this->errors[$fieldName] = "{$fieldName}は文字列である必要があります。";
            return false;
        }
        
        // 長さチェック
        if (strlen($input) > $maxLength) {
            $this->errors[$fieldName] = "{$fieldName}は{$maxLength}文字以内で入力してください。";
            return false;
        }
        
        if (strlen($input) < $minLength) {
            $this->errors[$fieldName] = "{$fieldName}は{$minLength}文字以上で入力してください。";
            return false;
        }
        
        // パターンチェック
        if ($pattern && !preg_match($pattern, $input)) {
            $this->errors[$fieldName] = "{$fieldName}の形式が正しくありません。";
            return false;
        }
        
        // XSS対策
        return $this->sanitizeString($input);
    }
    
    /**
     * 数値の検証
     */
    public function validateInteger($input, $fieldName, $options = []) {
        $min = $options['min'] ?? PHP_INT_MIN;
        $max = $options['max'] ?? PHP_INT_MAX;
        $required = $options['required'] ?? false;
        
        // 必須チェック
        if ($required && ($input === null || $input === '')) {
            $this->errors[$fieldName] = "{$fieldName}は必須です。";
            return false;
        }
        
        // 空の場合はスキップ
        if ($input === null || $input === '') {
            return null;
        }
        
        // 数値チェック
        if (!is_numeric($input)) {
            $this->errors[$fieldName] = "{$fieldName}は数値である必要があります。";
            return false;
        }
        
        $value = (int)$input;
        
        // 範囲チェック
        if ($value < $min || $value > $max) {
            $this->errors[$fieldName] = "{$fieldName}は{$min}から{$max}の範囲で入力してください。";
            return false;
        }
        
        return $value;
    }
    
    /**
     * 浮動小数点数の検証
     */
    public function validateFloat($input, $fieldName, $options = []) {
        $min = $options['min'] ?? PHP_FLOAT_MIN;
        $max = $options['max'] ?? PHP_FLOAT_MAX;
        $required = $options['required'] ?? false;
        
        // 必須チェック
        if ($required && ($input === null || $input === '')) {
            $this->errors[$fieldName] = "{$fieldName}は必須です。";
            return false;
        }
        
        // 空の場合はスキップ
        if ($input === null || $input === '') {
            return null;
        }
        
        // 数値チェック
        if (!is_numeric($input)) {
            $this->errors[$fieldName] = "{$fieldName}は数値である必要があります。";
            return false;
        }
        
        $value = (float)$input;
        
        // 範囲チェック
        if ($value < $min || $value > $max) {
            $this->errors[$fieldName] = "{$fieldName}は{$min}から{$max}の範囲で入力してください。";
            return false;
        }
        
        return $value;
    }
    
    /**
     * メールアドレスの検証
     */
    public function validateEmail($input, $fieldName, $required = false) {
        if ($required && empty($input)) {
            $this->errors[$fieldName] = "{$fieldName}は必須です。";
            return false;
        }
        
        if (empty($input)) {
            return '';
        }
        
        if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$fieldName] = "{$fieldName}の形式が正しくありません。";
            return false;
        }
        
        return $this->sanitizeString($input);
    }
    
    /**
     * URLの検証
     */
    public function validateURL($input, $fieldName, $required = false) {
        if ($required && empty($input)) {
            $this->errors[$fieldName] = "{$fieldName}は必須です。";
            return false;
        }
        
        if (empty($input)) {
            return '';
        }
        
        if (!filter_var($input, FILTER_VALIDATE_URL)) {
            $this->errors[$fieldName] = "{$fieldName}の形式が正しくありません。";
            return false;
        }
        
        return $this->sanitizeString($input);
    }
    
    /**
     * ファイルアップロードの検証
     */
    public function validateFileUpload($file, $fieldName, $options = []) {
        $allowedTypes = $options['allowed_types'] ?? ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = $options['max_size'] ?? 5 * 1024 * 1024; // 5MB
        $required = $options['required'] ?? false;
        
        // 必須チェック
        if ($required && (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE)) {
            $this->errors[$fieldName] = "{$fieldName}は必須です。";
            return false;
        }
        
        // ファイルがアップロードされていない場合
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        
        // アップロードエラーチェック
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[$fieldName] = "ファイルのアップロードに失敗しました。";
            return false;
        }
        
        // ファイルサイズチェック
        if ($file['size'] > $maxSize) {
            $this->errors[$fieldName] = "ファイルサイズは" . ($maxSize / 1024 / 1024) . "MB以下にしてください。";
            return false;
        }
        
        // ファイルタイプチェック
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $this->errors[$fieldName] = "許可されていないファイルタイプです。";
            return false;
        }
        
        // ファイル名のサニタイズ
        $filename = $this->sanitizeFilename($file['name']);
        
        return [
            'name' => $filename,
            'type' => $mimeType,
            'size' => $file['size'],
            'tmp_name' => $file['tmp_name']
        ];
    }
    
    /**
     * SQLインジェクション対策用の検証
     */
    public function validateSQLSafe($input, $fieldName, $type = 'string') {
        if (empty($input)) {
            return null;
        }
        
        // 危険な文字の検出
        $dangerousPatterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(\b(OR|AND)\s+\'[^\']*\'\s*=\s*\'[^\']*\')/i',
            '/(\b(OR|AND)\s+\"[^\"]*\"\s*=\s*\"[^\"]*\")/i',
            '/(\b(OR|AND)\s+1\s*=\s*1)/i',
            '/(\b(OR|AND)\s+\'[^\']*\'\s*=\s*\'[^\']*\')/i'
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->errors[$fieldName] = "{$fieldName}に無効な文字が含まれています。";
                return false;
            }
        }
        
        return $this->sanitizeString($input);
    }
    
    /**
     * 文字列のサニタイズ
     */
    private function sanitizeString($input) {
        // HTMLエンティティエンコード
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 制御文字の除去
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // 改行文字の正規化
        $input = str_replace(["\r\n", "\r"], "\n", $input);
        
        return trim($input);
    }
    
    /**
     * ファイル名のサニタイズ
     */
    private function sanitizeFilename($filename) {
        // 危険な文字の除去
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // 連続するアンダースコアの除去
        $filename = preg_replace('/_+/', '_', $filename);
        
        // 先頭・末尾のアンダースコアの除去
        $filename = trim($filename, '_');
        
        // 空文字列の場合はデフォルト名
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        
        return $filename;
    }
    
    /**
     * エラーの取得
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * エラーのクリア
     */
    public function clearErrors() {
        $this->errors = [];
    }
    
    /**
     * エラーがあるかチェック
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * 特定フィールドのエラー取得
     */
    public function getFieldError($fieldName) {
        return $this->errors[$fieldName] ?? null;
    }
    
    /**
     * 配列の検証
     */
    public function validateArray($input, $fieldName, $validator, $options = []) {
        if (!is_array($input)) {
            $this->errors[$fieldName] = "{$fieldName}は配列である必要があります。";
            return false;
        }
        
        $maxItems = $options['max_items'] ?? 100;
        $minItems = $options['min_items'] ?? 0;
        $required = $options['required'] ?? false;
        
        // 必須チェック
        if ($required && empty($input)) {
            $this->errors[$fieldName] = "{$fieldName}は必須です。";
            return false;
        }
        
        // 空の場合はスキップ
        if (empty($input)) {
            return [];
        }
        
        // アイテム数チェック
        if (count($input) > $maxItems) {
            $this->errors[$fieldName] = "{$fieldName}は{$maxItems}個以下にしてください。";
            return false;
        }
        
        if (count($input) < $minItems) {
            $this->errors[$fieldName] = "{$fieldName}は{$minItems}個以上にしてください。";
            return false;
        }
        
        // 各アイテムの検証
        $validated = [];
        foreach ($input as $index => $item) {
            $result = $validator($item, "{$fieldName}[{$index}]");
            if ($result === false) {
                return false;
            }
            $validated[] = $result;
        }
        
        return $validated;
    }
}
?>
