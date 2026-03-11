<?php

/**
 * 入力値検証ユーティリティ
 */
class InputValidator {
    
    /**
     * 文字列の検証とサニタイゼーション
     */
    public static function validateString($input, $maxLength = 255, $allowEmpty = false) {
        if ($input === null) {
            return $allowEmpty ? '' : null;
        }
        
        $input = trim($input);
        
        if (!$allowEmpty && empty($input)) {
            return null;
        }
        
        if (strlen($input) > $maxLength) {
            return null;
        }
        
        // XSS対策: HTMLタグを除去
        $input = strip_tags($input);
        
        // 特殊文字をエスケープ
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * 数値の検証
     */
    public static function validateInteger($input, $min = null, $max = null) {
        if ($input === null || $input === '') {
            return null;
        }
        
        $value = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($value === false) {
            return null;
        }
        
        if ($min !== null && $value < $min) {
            return null;
        }
        
        if ($max !== null && $value > $max) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * 浮動小数点数の検証
     */
    public static function validateFloat($input, $min = null, $max = null) {
        if ($input === null || $input === '') {
            return null;
        }
        
        $value = filter_var($input, FILTER_VALIDATE_FLOAT);
        
        if ($value === false) {
            return null;
        }
        
        if ($min !== null && $value < $min) {
            return null;
        }
        
        if ($max !== null && $value > $max) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * メールアドレスの検証
     */
    public static function validateEmail($input) {
        if ($input === null || $input === '') {
            return null;
        }
        
        $email = filter_var(trim($input), FILTER_VALIDATE_EMAIL);
        
        if ($email === false) {
            return null;
        }
        
        // 長さ制限
        if (strlen($email) > 254) {
            return null;
        }
        
        return $email;
    }
    
    /**
     * URLの検証
     */
    public static function validateUrl($input) {
        if ($input === null || $input === '') {
            return null;
        }
        
        $url = filter_var(trim($input), FILTER_VALIDATE_URL);
        
        if ($url === false) {
            return null;
        }
        
        // 許可されたプロトコルのみ
        $allowedSchemes = ['http', 'https'];
        $parsedUrl = parse_url($url);
        
        if (!in_array($parsedUrl['scheme'] ?? '', $allowedSchemes)) {
            return null;
        }
        
        return $url;
    }
    
    /**
     * 言語コードの検証
     */
    public static function validateLanguage($input) {
        $allowedLanguages = ['ja', 'en'];
        
        if (!in_array($input, $allowedLanguages)) {
            return 'ja'; // デフォルト値
        }
        
        return $input;
    }
    
    /**
     * 都道府県コードの検証
     */
    public static function validatePrefecture($input) {
        $allowedPrefectures = [
            'Hokkaido', 'Aomori', 'Iwate', 'Miyagi', 'Akita', 'Yamagata', 'Fukushima',
            'Ibaraki', 'Tochigi', 'Gunma', 'Saitama', 'Chiba', 'Tokyo', 'Kanagawa',
            'Niigata', 'Toyama', 'Ishikawa', 'Fukui', 'Yamanashi', 'Nagano', 'Gifu',
            'Shizuoka', 'Aichi', 'Mie', 'Shiga', 'Kyoto', 'Osaka', 'Hyogo', 'Nara',
            'Wakayama', 'Tottori', 'Shimane', 'Okayama', 'Hiroshima', 'Yamaguchi',
            'Tokushima', 'Kagawa', 'Ehime', 'Kochi', 'Fukuoka', 'Saga', 'Nagasaki',
            'Kumamoto', 'Oita', 'Miyazaki', 'Kagoshima', 'Okinawa'
        ];
        
        if (!in_array($input, $allowedPrefectures)) {
            return null;
        }
        
        return $input;
    }
    
    /**
     * スラッグの検証
     */
    public static function validateSlug($input) {
        if ($input === null || $input === '') {
            return null;
        }
        
        $slug = trim($input);
        
        // スラッグの形式チェック（英数字、ハイフン、アンダースコアのみ）
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            return null;
        }
        
        // 長さ制限
        if (strlen($slug) > 100) {
            return null;
        }
        
        return $slug;
    }
    
    /**
     * 座標の検証
     */
    public static function validateCoordinates($lat, $lng) {
        $lat = self::validateFloat($lat, -90, 90);
        $lng = self::validateFloat($lng, -180, 180);
        
        if ($lat === null || $lng === null) {
            return [null, null];
        }
        
        return [$lat, $lng];
    }
    
    /**
     * 検索クエリの検証
     */
    public static function validateSearchQuery($input) {
        if ($input === null || $input === '') {
            return '';
        }
        
        $query = trim($input);
        
        // 長さ制限
        if (strlen($query) > 500) {
            $query = substr($query, 0, 500);
        }
        
        // 危険な文字を除去
        $query = preg_replace('/[<>"\']/', '', $query);
        
        return $query;
    }
    
    /**
     * ページ番号の検証
     */
    public static function validatePage($input, $maxPage = 10000) {
        $page = self::validateInteger($input, 1, $maxPage);
        return $page ?? 1;
    }
    
    /**
     * ブール値の検証
     */
    public static function validateBoolean($input) {
        if ($input === null || $input === '') {
            return false;
        }
        
        $value = strtolower(trim($input));
        
        return in_array($value, ['true', '1', 'yes', 'on']);
    }
    
    /**
     * 配列の検証
     */
    public static function validateArray($input, $validator = null) {
        if (!is_array($input)) {
            return [];
        }
        
        if ($validator === null) {
            return $input;
        }
        
        $validated = [];
        foreach ($input as $key => $value) {
            $validatedValue = call_user_func($validator, $value);
            if ($validatedValue !== null) {
                $validated[$key] = $validatedValue;
            }
        }
        
        return $validated;
    }
}
