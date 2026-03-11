<?php
/**
 * 翻訳機能クラス
 * 多言語対応のための翻訳機能を提供
 */
class Translation {
    
    private static $translations = [];
    private static $loaded = false;
    
    /**
     * 翻訳を読み込む
     */
    private static function loadTranslations() {
        if (self::$loaded) {
            return;
        }
        
        // 日本語翻訳
        self::$translations['ja'] = [
            'search' => '検索',
            'search_placeholder' => 'キーワードを入力してください',
            'prefecture' => '都道府県',
            'completion_year' => '完成年',
            'building_type' => '建築タイプ',
            'has_photos' => '写真あり',
            'has_videos' => '動画あり',
            'search_button' => '検索',
            'clear_button' => 'クリア',
            'architect' => '建築家',
            'location' => '場所',
            'completion_year_label' => '完成年',
            'building_type_label' => '建築タイプ',
            'view_photos' => '写真を見る',
            'view_details' => '詳細を見る',
            'no_results' => '検索結果が見つかりません',
            'loading' => '読み込み中...',
            'error' => 'エラーが発生しました',
            'success' => '成功しました',
            'building' => '建物',
            'buildings' => '建物',
            'total' => '合計',
            'page' => 'ページ',
            'of' => 'の',
            'next' => '次へ',
            'previous' => '前へ',
            'first' => '最初',
            'last' => '最後'
        ];
        
        // 英語翻訳
        self::$translations['en'] = [
            'search' => 'Search',
            'search_placeholder' => 'Enter keywords',
            'prefecture' => 'Prefecture',
            'completion_year' => 'Completion Year',
            'building_type' => 'Building Type',
            'has_photos' => 'Has Photos',
            'has_videos' => 'Has Videos',
            'search_button' => 'Search',
            'clear_button' => 'Clear',
            'architect' => 'Architect',
            'location' => 'Location',
            'completion_year_label' => 'Completion Year',
            'building_type_label' => 'Building Type',
            'view_photos' => 'View Photos',
            'view_details' => 'View Details',
            'no_results' => 'No results found',
            'loading' => 'Loading...',
            'error' => 'An error occurred',
            'success' => 'Success',
            'building' => 'Building',
            'buildings' => 'Buildings',
            'total' => 'Total',
            'page' => 'Page',
            'of' => 'of',
            'next' => 'Next',
            'previous' => 'Previous',
            'first' => 'First',
            'last' => 'Last'
        ];
        
        self::$loaded = true;
    }
    
    /**
     * 翻訳を取得
     */
    public static function get($key, $lang = 'ja') {
        self::loadTranslations();
        
        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }
        
        // フォールバック: 日本語で取得
        if ($lang !== 'ja' && isset(self::$translations['ja'][$key])) {
            return self::$translations['ja'][$key];
        }
        
        // キーが見つからない場合はキーをそのまま返す
        return $key;
    }
    
    /**
     * 翻訳を設定
     */
    public static function set($key, $value, $lang = 'ja') {
        self::loadTranslations();
        self::$translations[$lang][$key] = $value;
    }
    
    /**
     * 言語の存在確認
     */
    public static function hasLanguage($lang) {
        self::loadTranslations();
        return isset(self::$translations[$lang]);
    }
    
    /**
     * 利用可能な言語一覧を取得
     */
    public static function getAvailableLanguages() {
        self::loadTranslations();
        return array_keys(self::$translations);
    }
    
    /**
     * 翻訳の一覧を取得
     */
    public static function getAllTranslations($lang = 'ja') {
        self::loadTranslations();
        return self::$translations[$lang] ?? [];
    }
}

/**
 * グローバル翻訳関数
 * 簡単に翻訳を取得するための関数
 */
function t($key, $lang = 'ja') {
    return Translation::get($key, $lang);
}
?>