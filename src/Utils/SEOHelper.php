<?php

/**
 * SEOヘルパークラス
 * ページタイプに応じたメタタグを生成
 */
class SEOHelper {
    
    /**
     * ページタイプに応じたメタタグを生成
     */
    public static function generateMetaTags($pageType, $data = [], $lang = 'ja') {
        $baseUrl = self::getBaseUrl();
        
        switch ($pageType) {
            case 'building':
                return self::generateBuildingMetaTags($data, $lang, $baseUrl);
            case 'architect':
                return self::generateArchitectMetaTags($data, $lang, $baseUrl);
            case 'search':
                return self::generateSearchMetaTags($data, $lang, $baseUrl);
            case 'home':
                return self::generateHomeMetaTags($lang, $baseUrl);
            case 'about':
                return self::generateAboutMetaTags($lang, $baseUrl);
            case 'contact':
                return self::generateContactMetaTags($lang, $baseUrl);
            default:
                return self::generateDefaultMetaTags($lang, $baseUrl);
        }
    }
    
    /**
     * 建築物ページのメタタグ
     */
    private static function generateBuildingMetaTags($building, $lang, $baseUrl) {
        // デバッグ用ログ（開発環境のみ）
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("SEO Debug - Building data keys: " . implode(', ', array_keys($building)));
            error_log("SEO Debug - architectJa type: " . gettype($building['architectJa'] ?? 'not_set'));
            error_log("SEO Debug - architectJa value: " . print_r($building['architectJa'] ?? 'not_set', true));
        }
        
        $title = $lang === 'ja' ? $building['title'] : ($building['titleEn'] ?? $building['title']);
        
        // 建築家情報の処理
        $architect = '';
        if ($lang === 'ja') {
            // 配列の場合は最初の要素、文字列の場合はそのまま使用
            if (is_array($building['architectJa'])) {
                $architect = !empty($building['architectJa']) ? $building['architectJa'][0] : '';
            } else {
                // 文字列の場合は最初の建築家名を取得（' / 'で区切られている場合）
                $architectString = $building['architectJa'] ?? '';
                if (!empty($architectString)) {
                    $architectNames = explode(' / ', $architectString);
                    $architect = trim($architectNames[0]);
                }
            }
        } else {
            // 英語版も同様の処理
            if (is_array($building['architectEn'])) {
                $architect = !empty($building['architectEn']) ? $building['architectEn'][0] : '';
            } else {
                $architectString = $building['architectEn'] ?? '';
                if (!empty($architectString)) {
                    $architectNames = explode(' / ', $architectString);
                    $architect = trim($architectNames[0]);
                }
            }
        }
        
        $location = $lang === 'ja' ? $building['location'] : $building['locationEn'];
        
        // 建物用途の処理（配列の場合は文字列に結合）
        $buildingTypes = '';
        if ($lang === 'ja') {
            if (is_array($building['buildingTypes'])) {
                $buildingTypes = implode('・', $building['buildingTypes']);
            } else {
                $buildingTypes = $building['buildingTypes'] ?? '';
            }
        } else {
            if (is_array($building['buildingTypesEn'])) {
                $buildingTypes = implode('・', $building['buildingTypesEn']);
            } else {
                $buildingTypes = $building['buildingTypesEn'] ?? '';
            }
        }
        
        // ページタイトルの最適化
        $pageTitle = $lang === 'ja' 
            ? "{$title} - {$architect}設計の{$buildingTypes} | 建築情報サイト PocketNavi"
            : "{$title} - {$buildingTypes} by {$architect} | Architecture Database PocketNavi";
            
        $description = $lang === 'ja'
            ? "{$title}は{$architect}が設計した{$buildingTypes}です。{$location}に位置し、{$building['completionYears']}年に完成。建築の詳細情報、写真、地図をPocketNaviで確認できます。"
            : "{$title} is a {$buildingTypes} designed by {$architect}. Located in {$location}, completed in {$building['completionYears']}. View detailed information, photos, and maps on PocketNavi.";
            
        $keywords = $lang === 'ja'
            ? "{$title}, {$architect}, {$buildingTypes}, {$location}, 建築, 設計, {$building['completionYears']}年"
            : "{$title}, {$architect}, {$buildingTypes}, {$location}, architecture, design, {$building['completionYears']}";
            
        // 建築物ページの画像選択
        if (!empty($building['has_photo']) && str_ends_with($building['has_photo'], '.webp')) {
            // has_photoが.webpで終わる場合は、撮影者所有の画像を使用可能
            $imageUrl = $baseUrl . "/pictures/{$building['uid']}/{$building['has_photo']}";
        } else {
            // has_photoがNULLまたは.webpで終わらない場合はデフォルト画像を使用
            $imageUrl = $baseUrl . '/assets/images/default-building.jpg';
        }
        $pageUrl = $baseUrl . "/buildings/{$building['slug']}/";
        
        return [
            'title' => $pageTitle,
            'description' => $description,
            'keywords' => $keywords,
            'og_title' => $pageTitle,
            'og_description' => $description,
            'og_image' => $imageUrl,
            'og_url' => $pageUrl,
            'og_type' => 'article',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $pageTitle,
            'twitter_description' => $description,
            'twitter_image' => $imageUrl,
            'canonical' => $pageUrl
        ];
    }
    
    /**
     * 検索結果ページのメタタグ
     */
    private static function generateSearchMetaTags($searchData, $lang, $baseUrl) {
        $query = $searchData['query'] ?? '';
        $totalResults = $searchData['total'] ?? 0;
        $currentPage = $searchData['currentPage'] ?? 1;
        $prefectures = $searchData['prefectures'] ?? '';
        $completionYears = $searchData['completionYears'] ?? '';
        $hasPhotos = $searchData['hasPhotos'] ?? false;
        $hasVideos = $searchData['hasVideos'] ?? false;
        
        // 検索条件の組み立て
        $searchConditions = [];
        
        if (!empty($query)) {
            $searchConditions[] = "「{$query}」";
        }
        
        if (!empty($prefectures)) {
            $prefectureNames = is_array($prefectures) ? $prefectures : [$prefectures];
            $searchConditions[] = implode('・', $prefectureNames);
        }
        
        if (!empty($completionYears)) {
            $yearNames = is_array($completionYears) ? $completionYears : [$completionYears];
            $searchConditions[] = implode('・', $yearNames) . '年完成';
        }
        
        if ($hasPhotos) {
            $searchConditions[] = '写真あり';
        }
        
        if ($hasVideos) {
            $searchConditions[] = '動画あり';
        }
        
        $searchConditionText = !empty($searchConditions) ? implode(' ', $searchConditions) . 'で検索' : '検索';
        
        // ページタイトルの最適化（SEO効果を考慮）
        if ($lang === 'ja') {
            if (!empty($query)) {
                // 検索ワードがある場合は、検索ワードを前面に
                $pageTitle = "{$query}の建築物検索結果";
                if ($totalResults > 0) {
                    $pageTitle .= " | {$totalResults}件の建築データベース";
                }
                $pageTitle .= " | PocketNavi";
            } else {
                // 検索ワードがない場合は、条件を前面に
                $pageTitle = "{$searchConditionText}の建築物検索";
                if ($totalResults > 0) {
                    $pageTitle .= " | {$totalResults}件の建築データベース";
                }
                $pageTitle .= " | PocketNavi";
            }
        } else {
            $pageTitle = "Search Results - {$totalResults} buildings found | Architecture Database PocketNavi";
        }
            
        $description = $lang === 'ja'
            ? "{$searchConditionText}の結果、{$totalResults}件の建築物が見つかりました。建築の詳細情報、写真、地図をPocketNaviで確認できます。"
            : "Search results for {$searchConditionText}. Found {$totalResults} buildings. View detailed information, photos, and maps on PocketNavi.";
            
        $keywords = $lang === 'ja'
            ? "建築物検索, {$query}, 建築, 設計, 検索結果, {$totalResults}件"
            : "building search, {$query}, architecture, design, search results, {$totalResults} buildings";
            
        // 検索結果ページのURL
        $searchParams = [];
        if (!empty($query)) $searchParams['q'] = $query;
        if (!empty($prefectures)) $searchParams['prefectures'] = is_array($prefectures) ? implode(',', $prefectures) : $prefectures;
        if (!empty($completionYears)) $searchParams['completionYears'] = is_array($completionYears) ? implode(',', $completionYears) : $completionYears;
        if ($hasPhotos) $searchParams['hasPhotos'] = '1';
        if ($hasVideos) $searchParams['hasVideos'] = '1';
        if ($currentPage > 1) $searchParams['page'] = $currentPage;
        $searchParams['lang'] = $lang;
        
        $pageUrl = $baseUrl . '/index.php?' . http_build_query($searchParams);
        
        return [
            'title' => $pageTitle,
            'description' => $description,
            'keywords' => $keywords,
            'og_title' => $pageTitle,
            'og_description' => $description,
            'og_image' => $baseUrl . '/assets/images/og-image.jpg',
            'og_url' => $pageUrl,
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $pageTitle,
            'twitter_description' => $description,
            'twitter_image' => $baseUrl . '/assets/images/og-image.jpg',
            'canonical' => $pageUrl
        ];
    }
    
    /**
     * 建築家ページのメタタグ
     */
    private static function generateArchitectMetaTags($architect, $lang, $baseUrl) {
        // 建築家名の処理（配列の場合は最初の要素を使用）
        $name = '';
        if ($lang === 'ja') {
            $name = $architect['name_ja'] ?? '';
        } else {
            $name = $architect['name_en'] ?? $architect['name_ja'] ?? '';
        }
        
        $buildingCount = $architect['building_count'] ?? 0;
        
        // ページタイトルの最適化
        $pageTitle = $lang === 'ja' 
            ? "{$name} - 建築家の作品一覧（{$buildingCount}件） | 建築情報サイト PocketNavi"
            : "{$name} - Architect Works ({$buildingCount} buildings) | Architecture Database PocketNavi";
            
        $description = $lang === 'ja'
            ? "{$name}の建築作品一覧。{$buildingCount}件の建築物を設計した建築家の作品をPocketNaviで確認できます。建築の詳細情報、写真、地図を提供。"
            : "Architectural works by {$name}. View {$buildingCount} buildings designed by this architect on PocketNavi. Detailed information, photos, and maps available.";
            
        $keywords = $lang === 'ja'
            ? "{$name}, 建築家, 建築作品, 設計, 建築"
            : "{$name}, architect, architectural works, design, architecture";
            
        // 建築家ページはメインOG画像を使用
        $imageUrl = $baseUrl . '/assets/images/og-image.jpg';
        $pageUrl = $baseUrl . "/architects/{$architect['slug']}/";
        
        return [
            'title' => $pageTitle,
            'description' => $description,
            'keywords' => $keywords,
            'og_title' => $pageTitle,
            'og_description' => $description,
            'og_image' => $imageUrl,
            'og_url' => $pageUrl,
            'og_type' => 'profile',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $pageTitle,
            'twitter_description' => $description,
            'twitter_image' => $imageUrl,
            'canonical' => $pageUrl
        ];
    }
    
    /**
     * ホームページのメタタグ
     */
    private static function generateHomeMetaTags($lang, $baseUrl) {
        // ページタイトルの最適化
        $pageTitle = $lang === 'ja' 
            ? "建築物検索データベース | 建築家・建築物・都道府県から検索 | PocketNavi"
            : "Architecture Search Database | Search by Architect, Building, Prefecture | PocketNavi";
            
        $description = $lang === 'ja'
            ? "建築を志して街歩きをする人たちのための検索型建築作品データベース。建築物名、設計者名、用途、所在地などで検索できます。"
            : "Searchable architectural works database for architecture enthusiasts and city walkers. Search by building name, architect, purpose, location, and more.";
            
        $keywords = $lang === 'ja'
            ? "建築, 建築物, 建築家, 検索, ナビゲーション, 街歩き, 建築散歩"
            : "architecture, buildings, architects, search, navigation, city walk, architectural tour";
            
        return [
            'title' => $pageTitle,
            'description' => $description,
            'keywords' => $keywords,
            'og_title' => $pageTitle,
            'og_description' => $description,
            'og_image' => $baseUrl . '/assets/images/og-image.jpg',
            'og_url' => $baseUrl . '/',
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $pageTitle,
            'twitter_description' => $description,
            'twitter_image' => $baseUrl . '/assets/images/og-image.jpg',
            'canonical' => $baseUrl . '/'
        ];
    }
    
    /**
     * Aboutページのメタタグ
     */
    private static function generateAboutMetaTags($lang, $baseUrl) {
        // ページタイトルの最適化
        $pageTitle = $lang === 'ja' 
            ? "PocketNaviについて | 建築物検索データベースのご紹介"
            : "About PocketNavi | Architecture Search Database Information";
            
        $description = $lang === 'ja'
            ? "PocketNaviは建築を志して街歩きをする人たちのための検索型建築作品データベースです。建築散歩の参考にご活用ください。"
            : "PocketNavi is a searchable architectural works database for architecture enthusiasts and city walkers. Use it as a reference for your architectural walks.";
            
        $keywords = $lang === 'ja'
            ? "PocketNavi, このサイトについて, 建築データベース, 建築散歩, 街歩き"
            : "PocketNavi, about, architectural database, architectural walk, city walk";
            
        $pageUrl = $baseUrl . "/about.php?lang={$lang}";
        
        return [
            'title' => $pageTitle,
            'description' => $description,
            'keywords' => $keywords,
            'og_title' => $pageTitle,
            'og_description' => $description,
            'og_image' => $baseUrl . '/assets/images/og-image.jpg',
            'og_url' => $pageUrl,
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $pageTitle,
            'twitter_description' => $description,
            'twitter_image' => $baseUrl . '/assets/images/og-image.jpg',
            'canonical' => $pageUrl
        ];
    }
    
    /**
     * Contactページのメタタグ
     */
    private static function generateContactMetaTags($lang, $baseUrl) {
        // ページタイトルの最適化
        $pageTitle = $lang === 'ja' 
            ? "お問い合わせ | 建築データベースPocketNaviへのご連絡"
            : "Contact Us | Get in Touch with Architecture Database PocketNavi";
            
        $description = $lang === 'ja'
            ? "PocketNaviに関するお問い合わせはこちらから。建築データベースの改善提案やご質問をお待ちしています。"
            : "Contact PocketNavi for inquiries about our architectural database. We welcome suggestions for improvement and questions.";
            
        $keywords = $lang === 'ja'
            ? "お問い合わせ, PocketNavi, 建築データベース, 連絡先"
            : "contact, PocketNavi, architectural database, inquiry";
            
        $pageUrl = $baseUrl . "/contact.php?lang={$lang}";
        
        return [
            'title' => $pageTitle,
            'description' => $description,
            'keywords' => $keywords,
            'og_title' => $pageTitle,
            'og_description' => $description,
            'og_image' => $baseUrl . '/assets/images/og-image.jpg',
            'og_url' => $pageUrl,
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $pageTitle,
            'twitter_description' => $description,
            'twitter_image' => $baseUrl . '/assets/images/og-image.jpg',
            'canonical' => $pageUrl
        ];
    }
    
    /**
     * デフォルトメタタグ
     */
    private static function generateDefaultMetaTags($lang, $baseUrl) {
        $pageTitle = $lang === 'ja' 
            ? "PocketNavi - 建築物ナビゲーション"
            : "PocketNavi - Building Navigation";
            
        $description = $lang === 'ja'
            ? "建築物検索データベース"
            : "Building search database";
            
        $keywords = $lang === 'ja'
            ? "建築, 建築物, 検索"
            : "architecture, buildings, search";
            
        return [
            'title' => $pageTitle,
            'description' => $description,
            'keywords' => $keywords,
            'og_title' => $pageTitle,
            'og_description' => $description,
            'og_image' => $baseUrl . '/assets/images/og-image.jpg',
            'og_url' => $baseUrl . '/',
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $pageTitle,
            'twitter_description' => $description,
            'twitter_image' => $baseUrl . '/assets/images/og-image.jpg',
            'canonical' => $baseUrl . '/'
        ];
    }
    
    /**
     * ベースURLを取得
     */
    private static function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . '://' . $host;
    }
    
    /**
     * 構造化データ（JSON-LD）を生成
     */
    public static function generateStructuredData($pageType, $data = [], $lang = 'ja') {
        switch ($pageType) {
            case 'building':
                return self::generateBuildingStructuredData($data, $lang);
            case 'architect':
                return self::generateArchitectStructuredData($data, $lang);
            case 'search':
                return self::generateSearchStructuredData($data, $lang);
            case 'home':
                return self::generateHomeStructuredData($lang);
            case 'about':
                return self::generateAboutStructuredData($lang);
            case 'contact':
                return self::generateContactStructuredData($lang);
            default:
                return self::generateDefaultStructuredData($lang);
        }
    }
    
    /**
     * 建築物ページの構造化データ
     */
    private static function generateBuildingStructuredData($building, $lang) {
        $baseUrl = self::getBaseUrl();
        
        // 建築家情報の処理
        $architect = '';
        if ($lang === 'ja') {
            if (is_array($building['architectJa'])) {
                $architect = !empty($building['architectJa']) ? $building['architectJa'][0] : '';
            } else {
                $architectString = $building['architectJa'] ?? '';
                if (!empty($architectString)) {
                    $architectNames = explode(' / ', $architectString);
                    $architect = trim($architectNames[0]);
                }
            }
        } else {
            if (is_array($building['architectEn'])) {
                $architect = !empty($building['architectEn']) ? $building['architectEn'][0] : '';
            } else {
                $architectString = $building['architectEn'] ?? '';
                if (!empty($architectString)) {
                    $architectNames = explode(' / ', $architectString);
                    $architect = trim($architectNames[0]);
                }
            }
        }
        
        // 建物用途の処理
        $buildingTypes = '';
        if ($lang === 'ja') {
            if (is_array($building['buildingTypes'])) {
                $buildingTypes = implode('・', $building['buildingTypes']);
            } else {
                $buildingTypes = $building['buildingTypes'] ?? '';
            }
        } else {
            if (is_array($building['buildingTypesEn'])) {
                $buildingTypes = implode('・', $building['buildingTypesEn']);
            } else {
                $buildingTypes = $building['buildingTypesEn'] ?? '';
            }
        }
        
        $title = $lang === 'ja' ? $building['title'] : ($building['titleEn'] ?? $building['title']);
        $location = $lang === 'ja' ? $building['location'] : $building['locationEn'];
        
        // 画像URLの決定
        if (!empty($building['has_photo']) && str_ends_with($building['has_photo'], '.webp')) {
            $imageUrl = $baseUrl . "/pictures/{$building['uid']}/{$building['has_photo']}";
        } else {
            $imageUrl = $baseUrl . '/assets/images/default-building.jpg';
        }
        
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Building',
            'name' => $title,
            'description' => $lang === 'ja' 
                ? "{$title}は{$architect}が設計した{$buildingTypes}です。{$location}に位置し、{$building['completionYears']}年に完成。"
                : "{$title} is a {$buildingTypes} designed by {$architect}. Located in {$location}, completed in {$building['completionYears']}.",
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $location
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $building['lat'],
                'longitude' => $building['lng']
            ],
            'dateCompleted' => $building['completionYears'] . '-01-01',
            'image' => $imageUrl,
            'url' => $baseUrl . "/buildings/{$building['slug']}/",
            'sameAs' => []
        ];
        
        // 建築家情報を追加
        if (!empty($architect)) {
            $structuredData['architect'] = [
                '@type' => 'Person',
                'name' => $architect
            ];
        }
        
        // YouTube URLがある場合は追加
        if (!empty($building['youtubeUrl'])) {
            $structuredData['sameAs'][] = $building['youtubeUrl'];
        }
        
        return $structuredData;
    }
    
    /**
     * 検索結果ページの構造化データ
     */
    private static function generateSearchStructuredData($searchData, $lang) {
        $baseUrl = self::getBaseUrl();
        
        $query = $searchData['query'] ?? '';
        $totalResults = $searchData['total'] ?? 0;
        $currentPage = $searchData['currentPage'] ?? 1;
        $prefectures = $searchData['prefectures'] ?? '';
        $completionYears = $searchData['completionYears'] ?? '';
        $hasPhotos = $searchData['hasPhotos'] ?? false;
        $hasVideos = $searchData['hasVideos'] ?? false;
        
        // 検索条件の組み立て
        $searchConditions = [];
        
        if (!empty($query)) {
            $searchConditions[] = $query;
        }
        
        if (!empty($prefectures)) {
            $prefectureNames = is_array($prefectures) ? $prefectures : [$prefectures];
            $searchConditions[] = implode(', ', $prefectureNames);
        }
        
        if (!empty($completionYears)) {
            $yearNames = is_array($completionYears) ? $completionYears : [$completionYears];
            $searchConditions[] = implode(', ', $yearNames) . ' completion year';
        }
        
        if ($hasPhotos) {
            $searchConditions[] = 'with photos';
        }
        
        if ($hasVideos) {
            $searchConditions[] = 'with videos';
        }
        
        $searchConditionText = !empty($searchConditions) ? implode(', ', $searchConditions) : 'general search';
        
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'SearchResultsPage',
            'name' => $lang === 'ja' 
                ? "建築物検索結果 - {$totalResults}件"
                : "Building Search Results - {$totalResults} buildings",
            'description' => $lang === 'ja'
                ? "「{$searchConditionText}」の検索結果。{$totalResults}件の建築物が見つかりました。"
                : "Search results for '{$searchConditionText}'. Found {$totalResults} buildings.",
            'url' => $baseUrl . '/index.php',
            'image' => $baseUrl . '/assets/images/og-image.jpg',
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $totalResults,
                'itemListElement' => []
            ]
        ];
        
        // 検索アクションの追加
        $structuredData['potentialAction'] = [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $baseUrl . '/index.php?q={search_term_string}'
            ],
            'query-input' => 'required name=search_term_string'
        ];
        
        return $structuredData;
    }
    
    /**
     * 建築家ページの構造化データ
     */
    private static function generateArchitectStructuredData($architect, $lang) {
        $baseUrl = self::getBaseUrl();
        
        $name = '';
        if ($lang === 'ja') {
            $name = $architect['name_ja'] ?? '';
        } else {
            $name = $architect['name_en'] ?? $architect['name_ja'] ?? '';
        }
        
        $buildingCount = $architect['building_count'] ?? 0;
        
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $name,
            'description' => $lang === 'ja'
                ? "{$name}の建築作品一覧。{$buildingCount}件の建築物を設計した建築家。"
                : "Architect {$name}. Designed {$buildingCount} buildings.",
            'jobTitle' => $lang === 'ja' ? '建築家' : 'Architect',
            'url' => $baseUrl . "/architects/{$architect['slug']}/",
            'image' => $baseUrl . '/assets/images/og-image.jpg',
            'sameAs' => []
        ];
        
        // 個人ウェブサイトがある場合は追加
        if (!empty($architect['individual_website'])) {
            $structuredData['sameAs'][] = $architect['individual_website'];
        }
        
        return $structuredData;
    }
    
    /**
     * ホームページの構造化データ
     */
    private static function generateHomeStructuredData($lang) {
        $baseUrl = self::getBaseUrl();
        
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $lang === 'ja' ? 'PocketNavi - 建築物ナビゲーション' : 'PocketNavi - Building Navigation',
            'description' => $lang === 'ja' 
                ? '建築物検索データベース。建築家、建築物、都道府県から検索できます。'
                : 'Building search database. Search by architect, building, or prefecture.',
            'url' => $baseUrl,
            'image' => $baseUrl . '/assets/images/og-image.jpg',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $baseUrl . '/index.php?q={search_term_string}'
                ],
                'query-input' => 'required name=search_term_string'
            ]
        ];
        
        return $structuredData;
    }
    
    /**
     * Aboutページの構造化データ
     */
    private static function generateAboutStructuredData($lang) {
        $baseUrl = self::getBaseUrl();
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'AboutPage',
            'name' => $lang === 'ja' ? 'このサイトについて | PocketNavi' : 'About | PocketNavi',
            'description' => $lang === 'ja' 
                ? 'PocketNaviについての情報ページ'
                : 'Information about PocketNavi',
            'url' => $baseUrl . '/about.php',
            'image' => $baseUrl . '/assets/images/og-image.jpg'
        ];
    }
    
    /**
     * Contactページの構造化データ
     */
    private static function generateContactStructuredData($lang) {
        $baseUrl = self::getBaseUrl();
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ContactPage',
            'name' => $lang === 'ja' ? 'お問い合わせ | PocketNavi' : 'Contact | PocketNavi',
            'description' => $lang === 'ja' 
                ? 'PocketNaviへのお問い合わせページ'
                : 'Contact page for PocketNavi',
            'url' => $baseUrl . '/contact.php',
            'image' => $baseUrl . '/assets/images/og-image.jpg'
        ];
    }
    
    /**
     * デフォルトの構造化データ
     */
    private static function generateDefaultStructuredData($lang) {
        $baseUrl = self::getBaseUrl();
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $lang === 'ja' ? 'PocketNavi' : 'PocketNavi',
            'description' => $lang === 'ja' ? '建築物検索データベース' : 'Building search database',
            'url' => $baseUrl,
            'image' => $baseUrl . '/assets/images/og-image.jpg'
        ];
    }
    
    /**
     * 構造化データのHTMLを生成
     */
    public static function renderStructuredData($structuredData) {
        if (empty($structuredData)) {
            return '';
        }
        
        return '<script type="application/ld+json">' . json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }

    /**
     * ページタイトルの最適化（長さ制限とキーワード配置）
     */
    private static function optimizePageTitle($title, $maxLength = 60) {
        // タイトルが長すぎる場合は切り詰める
        if (strlen($title) > $maxLength) {
            $title = substr($title, 0, $maxLength - 3) . '...';
        }
        return $title;
    }

    /**
     * メタタグのHTMLを生成
     */
    public static function renderMetaTags($seoData) {
        $html = '';
        
        // ページタイトルの最適化
        $optimizedTitle = self::optimizePageTitle($seoData['title']);
        
        // 基本メタタグ
        $html .= '<title>' . htmlspecialchars($optimizedTitle) . '</title>' . "\n";
        $html .= '<meta name="description" content="' . htmlspecialchars($seoData['description']) . '">' . "\n";
        $html .= '<meta name="keywords" content="' . htmlspecialchars($seoData['keywords']) . '">' . "\n";
        $html .= '<link rel="canonical" href="' . htmlspecialchars($seoData['canonical']) . '">' . "\n";
        
        // Open Graph タグ
        $html .= '<meta property="og:title" content="' . htmlspecialchars($seoData['og_title']) . '">' . "\n";
        $html .= '<meta property="og:description" content="' . htmlspecialchars($seoData['og_description']) . '">' . "\n";
        $html .= '<meta property="og:image" content="' . htmlspecialchars($seoData['og_image']) . '">' . "\n";
        $html .= '<meta property="og:url" content="' . htmlspecialchars($seoData['og_url']) . '">' . "\n";
        $html .= '<meta property="og:type" content="' . htmlspecialchars($seoData['og_type']) . '">' . "\n";
        $html .= '<meta property="og:site_name" content="PocketNavi">' . "\n";
        
        // Twitter Card タグ
        $html .= '<meta name="twitter:card" content="' . htmlspecialchars($seoData['twitter_card']) . '">' . "\n";
        $html .= '<meta name="twitter:title" content="' . htmlspecialchars($seoData['twitter_title']) . '">' . "\n";
        $html .= '<meta name="twitter:description" content="' . htmlspecialchars($seoData['twitter_description']) . '">' . "\n";
        $html .= '<meta name="twitter:image" content="' . htmlspecialchars($seoData['twitter_image']) . '">' . "\n";
        
        // 追加SEOタグ
        $html .= '<meta name="robots" content="index, follow">' . "\n";
        $html .= '<meta name="author" content="PocketNavi">' . "\n";
        $html .= '<meta name="generator" content="PocketNavi">' . "\n";
        
        return $html;
    }
}
