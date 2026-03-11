<?php

require_once __DIR__ . '/ConfigManager.php';

/**
 * 設定検証システム
 * アプリケーションの設定値の妥当性を検証
 */
class ConfigValidator {
    
    private static $rules = [];
    private static $messages = [];
    
    /**
     * 検証ルールの初期化
     */
    public static function initialize() {
        self::$rules = [
            // アプリケーション設定の検証ルール
            'app.name' => ['required', 'string', 'max:100'],
            'app.env' => ['required', 'in:development,staging,production'],
            'app.debug' => ['boolean'],
            'app.url' => ['required', 'url'],
            'app.timezone' => ['required', 'string'],
            'app.locale' => ['required', 'in:ja,en'],
            'app.fallback_locale' => ['required', 'in:ja,en'],
            
            // データベース設定の検証ルール
            'database.host' => ['required', 'string'],
            'database.name' => ['required', 'string'],
            'database.username' => ['required', 'string'],
            'database.password' => ['string'],
        ];
        
        self::$messages = [
            'required' => 'The :field field is required.',
            'string' => 'The :field field must be a string.',
            'integer' => 'The :field field must be an integer.',
            'boolean' => 'The :field field must be a boolean.',
            'email' => 'The :field field must be a valid email address.',
            'url' => 'The :field field must be a valid URL.',
            'in' => 'The :field field must be one of: :values.',
            'max' => 'The :field field must not exceed :max characters.',
            'min' => 'The :field field must be at least :min.',
        ];
    }
    
    /**
     * 設定の検証
     */
    public static function validate($config = null) {
        if (!self::$rules) {
            self::initialize();
        }
        
        if ($config === null) {
            $config = ConfigManager::all();
        }
        
        $errors = [];
        
        foreach (self::$rules as $key => $rules) {
            $value = self::getNestedValue($config, $key);
            
            foreach ($rules as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleParams = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];
                
                if (!self::validateRule($value, $ruleName, $ruleParams)) {
                    $errors[$key][] = self::getErrorMessage($key, $ruleName, $ruleParams);
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * ネストした配列から値を取得
     */
    private static function getNestedValue($array, $key) {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * 検証ルールの実行
     */
    private static function validateRule($value, $rule, $params) {
        switch ($rule) {
            case 'required':
                return !empty($value);
                
            case 'string':
                return is_string($value);
                
            case 'integer':
                return is_int($value) || (is_string($value) && ctype_digit($value));
                
            case 'boolean':
                return is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0]);
                
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
                
            case 'in':
                return in_array($value, $params);
                
            case 'max':
                return strlen($value) <= (int)$params[0];
                
            case 'min':
                return strlen($value) >= (int)$params[0];
                
            default:
                return true;
        }
    }
    
    /**
     * エラーメッセージの取得
     */
    private static function getErrorMessage($field, $rule, $params) {
        $message = self::$messages[$rule] ?? 'The :field field is invalid.';
        
        $message = str_replace(':field', $field, $message);
        
        if (isset($params[0])) {
            if ($rule === 'in') {
                $message = str_replace(':values', implode(', ', $params), $message);
            } else {
                $message = str_replace(':max', $params[0], $message);
                $message = str_replace(':min', $params[0], $message);
            }
        }
        
        return $message;
    }
    
    /**
     * 設定の推奨事項チェック
     */
    public static function getRecommendations() {
        $recommendations = [];
        
        // 本番環境での推奨事項
        if (ConfigManager::get('app.env') === 'production') {
            if (ConfigManager::get('app.debug')) {
                $recommendations[] = 'Debug mode should be disabled in production';
            }
        }
        
        return $recommendations;
    }
    
    /**
     * 設定の最適化提案
     */
    public static function getOptimizations() {
        $optimizations = [];
        
        // パフォーマンス最適化
        if (ConfigManager::get('cache.driver') === 'file') {
            $optimizations[] = 'Consider using Redis or Memcached for better cache performance';
        }
        
        return $optimizations;
    }
}
