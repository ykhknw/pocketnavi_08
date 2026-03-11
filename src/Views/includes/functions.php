<?php
// 統合された共通関数ファイル - セキュリティ強化版

// クラスファイルを読み込み
//require_once __DIR__ . '/../../Utils/Database.php';
require_once __DIR__ . '/../../Services/BuildingService.php';
require_once __DIR__ . '/../../Services/ArchitectService.php';
require_once __DIR__ . '/../../Utils/ErrorHandler.php';

// 入力検証クラスの読み込み（既に読み込まれている場合はスキップ）
if (!class_exists('InputValidator') && file_exists(__DIR__ . '/../../Security/InputValidator.php')) {
    require_once __DIR__ . '/../../Security/InputValidator.php';
}

/**
 * セキュアな入力検証関数
 */
function secureValidateInput($input, $type = 'string', $options = []) {
    if (!class_exists('InputValidator')) {
        // フォールバック: 基本的なサニタイズ
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    $validator = new InputValidator();
    
    switch ($type) {
        case 'string':
            return $validator->validateString($input, 'input', $options);
        case 'integer':
            return $validator->validateInteger($input, 'input', $options);
        case 'email':
            return $validator->validateEmail($input, 'input', $options['required'] ?? false);
        case 'url':
            return $validator->validateURL($input, 'input', $options['required'] ?? false);
        case 'sql_safe':
            return $validator->validateSQLSafe($input, 'input', $type);
        default:
            return $validator->validateString($input, 'input', $options);
    }
}

/**
 * セキュアなGETパラメータ取得
 */
function secureGetParam($key, $default = '', $type = 'string', $options = []) {
    $value = $_GET[$key] ?? $default;
    return secureValidateInput($value, $type, $options);
}

/**
 * セキュアなPOSTパラメータ取得
 */
function securePostParam($key, $default = '', $type = 'string', $options = []) {
    $value = $_POST[$key] ?? $default;
    return secureValidateInput($value, $type, $options);
}




/**
 * サムネイルURLを生成
 */
function generateThumbnailUrl($uid, $hasPhoto) {
    // has_photoがNULLまたは空の場合は空文字を返す
    if (empty($hasPhoto)) {
        return '';
    }
    
    return '/pictures/' . urlencode($uid) . '/' . urlencode($hasPhoto);
}

/**
 * 建築物を検索する（新しいクラスベース設計に移行）
 */
function searchBuildings($query, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
    try {
        $buildingService = new BuildingService();
        return $buildingService->search($query, $page, $hasPhotos, $hasVideos, $lang, $limit);
    } catch (Exception $e) {
        ErrorHandler::log("Search error: " . $e->getMessage(), ErrorHandler::LOG_LEVEL_ERROR, [
            'query' => $query,
            'page' => $page,
            'hasPhotos' => $hasPhotos,
            'hasVideos' => $hasVideos,
            'lang' => $lang,
            'limit' => $limit
        ]);
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    }
}

/**
 * 複数条件での建築物検索（新しいクラスベース設計に移行）
 */
function searchBuildingsWithMultipleConditions($query, $completionYears, $prefectures, $buildingTypes, $hasPhotos, $hasVideos, $page = 1, $lang = 'ja', $limit = 10) {
    try {
        $buildingService = new BuildingService();
        
        // 配列に変換（文字列の場合は配列に分割）
        $completionYearsArray = is_array($completionYears) ? $completionYears : (!empty($completionYears) ? [$completionYears] : []);
        $prefecturesArray = is_array($prefectures) ? $prefectures : (!empty($prefectures) ? [$prefectures] : []);
        $buildingTypesArray = is_array($buildingTypes) ? $buildingTypes : (!empty($buildingTypes) ? [$buildingTypes] : []);
        
        return $buildingService->searchWithMultipleConditions($query, $completionYearsArray, $prefecturesArray, $buildingTypesArray, $hasPhotos, $hasVideos, $page, $lang, $limit);
    } catch (Exception $e) {
        ErrorHandler::log("Multiple conditions search error: " . $e->getMessage(), ErrorHandler::LOG_LEVEL_ERROR, [
            'query' => $query,
            'completionYears' => $completionYears,
            'prefectures' => $prefectures,
            'buildingTypes' => $buildingTypes,
            'hasPhotos' => $hasPhotos,
            'hasVideos' => $hasVideos,
            'page' => $page,
            'lang' => $lang,
            'limit' => $limit
        ]);
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    }
}

/**
 * 現在地検索用の関数（新しいクラスベース設計に移行）
 */
function searchBuildingsByLocation($userLat, $userLng, $radiusKm = 5, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
    try {
        $buildingService = new BuildingService();
        return $buildingService->searchByLocation($userLat, $userLng, $radiusKm, $page, $hasPhotos, $hasVideos, $lang, $limit);
    } catch (Exception $e) {
        ErrorHandler::log("Location search error: " . $e->getMessage(), ErrorHandler::LOG_LEVEL_ERROR, [
            'userLat' => $userLat,
            'userLng' => $userLng,
            'radiusKm' => $radiusKm,
            'page' => $page,
            'hasPhotos' => $hasPhotos,
            'hasVideos' => $hasVideos,
            'lang' => $lang,
            'limit' => $limit
        ]);
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    }
}

/**
 * 建築物データを変換（統合版）
 */
function transformBuildingData($row, $lang = 'ja') {
    // 建築家情報の処理
    $architects = [];
    if (!empty($row['architectJa'])) {
        $namesJa = explode(' / ', $row['architectJa']);
        $namesEn = !empty($row['architectEn']) ? explode(' / ', $row['architectEn']) : [];
        $architectIds = !empty($row['architectIds']) ? explode(',', $row['architectIds']) : [];
        $architectSlugs = !empty($row['architectSlugs']) ? explode(',', $row['architectSlugs']) : [];
        // individual_architect_ids, individual_websites, website_titlesは
        // "architect_order_order_index_value"形式で取得されているため、最後の部分を抽出
        $individualArchitectIdsRaw = !empty($row['individual_architect_ids']) ? explode(',', $row['individual_architect_ids']) : [];
        $individualWebsitesRaw = !empty($row['individual_websites']) ? explode('|||', $row['individual_websites']) : [];
        $websiteTitlesRaw = !empty($row['website_titles']) ? explode('|||', $row['website_titles']) : [];
        
        // プレフィックス（architect_order_order_index_）を除去して値を抽出
        $individualArchitectIds = [];
        foreach ($individualArchitectIdsRaw as $value) {
            $parts = explode('_', $value);
            $individualArchitectIds[] = isset($parts[2]) ? intval($parts[2]) : 0;
        }
        
        $individualWebsites = [];
        foreach ($individualWebsitesRaw as $value) {
            // 形式: "architect_order_order_index_value"
            // 最初の2つのアンダースコアで分割し、残りを取得
            $parts = explode('_', $value, 3);
            // $parts[0] = architect_order, $parts[1] = order_index, $parts[2] = value (URL)
            $individualWebsites[] = isset($parts[2]) ? trim($parts[2]) : '';
        }
        
        $websiteTitles = [];
        foreach ($websiteTitlesRaw as $value) {
            $parts = explode('_', $value, 3);
            $websiteTitles[] = isset($parts[2]) ? trim($parts[2]) : '';
        }
        
        for ($i = 0; $i < count($namesJa); $i++) {
            $architects[] = [
                'architect_id' => isset($architectIds[$i]) ? intval($architectIds[$i]) : 0,
                'architectJa' => trim($namesJa[$i]),
                'architectEn' => isset($namesEn[$i]) ? trim($namesEn[$i]) : trim($namesJa[$i]),
                'slug' => isset($architectSlugs[$i]) ? trim($architectSlugs[$i]) : '',
                'individual_architect_id' => isset($individualArchitectIds[$i]) ? intval($individualArchitectIds[$i]) : 0,
                'individual_website' => isset($individualWebsites[$i]) ? trim($individualWebsites[$i]) : '',
                'website_title' => isset($websiteTitles[$i]) ? trim($websiteTitles[$i]) : ''
            ];
        }
    }
    
    // 建物用途の配列変換
    $buildingTypes = !empty($row['buildingTypes']) ? 
        array_filter(explode('/', $row['buildingTypes']), function($type) {
            return !empty(trim($type));
        }) : [];
    
    $buildingTypesEn = !empty($row['buildingTypesEn']) ? 
        array_filter(explode('/', $row['buildingTypesEn']), function($type) {
            return !empty(trim($type));
        }) : [];
    
    // 英語データの処理
    $titleEn = $row['titleEn'] ?? $row['title'] ?? '';
    $locationEn = '';
    if (!empty($row['locationEn_from_datasheetChunkEn'])) {
        $locationEn = $row['locationEn_from_datasheetChunkEn'];
    } elseif (!empty($row['locationEn'])) {
        $locationEn = $row['locationEn'];
    } else {
        $locationEn = $row['location'] ?? '';
    }
    
    return [
        'building_id' => intval($row['building_id'] ?? 0),
        'uid' => $row['uid'] ?? '',
        'title' => $row['title'] ?? '',
        'titleEn' => $titleEn,
        'slug' => $row['slug'] ?? '',
        'lat' => floatval($row['lat'] ?? 0),
        'lng' => floatval($row['lng'] ?? 0),
        'location' => $row['location'] ?? '',
        'locationEn' => $locationEn,
        'completionYears' => intval($row['completionYears'] ?? 0),
        'buildingTypes' => $buildingTypes,
        'buildingTypesEn' => $buildingTypesEn,
        'prefectures' => $row['prefectures'] ?? '',
        'prefecturesEn' => $row['prefecturesEn'] ?? '',
        'has_photo' => $row['has_photo'] ?? '',
        'thumbnailUrl' => generateThumbnailUrl($row['uid'] ?? '', $row['has_photo'] ?? ''),
        'youtubeUrl' => $row['youtubeUrl'] ?? '',
        'building_column_text' => $row['building_column_text'] ?? '',
        'column_title' => $row['column_title'] ?? '',
        'architects' => $architects,
        // SEO用に元の文字列データも保持
        'architectJa' => $row['architectJa'] ?? '',
        'architectEn' => $row['architectEn'] ?? '',
        'likes' => intval($row['likes'] ?? 0),
        'distance' => isset($row['distance']) ? round(floatval($row['distance']), 2) : null,
        'created_at' => $row['created_at'] ?? '',
        'updated_at' => $row['updated_at'] ?? ''
    ];
}

/**
 * スラッグで建築物を取得（新しいクラスベース設計に移行）
 */
function getBuildingBySlug($slug, $lang = 'ja') {
    try {
        $buildingService = new BuildingService();
        return $buildingService->getBySlug($slug, $lang);
    } catch (Exception $e) {
        ErrorHandler::log("Get building by slug error: " . $e->getMessage(), ErrorHandler::LOG_LEVEL_ERROR, [
            'slug' => $slug,
            'lang' => $lang
        ]);
        return null;
    }
}

/**
 * 建築家のスラッグで建築物を検索（新しいクラスベース設計に移行）
 */
function searchBuildingsByArchitectSlug($architectSlug, $page = 1, $lang = 'ja', $limit = 10, $completionYears = '', $prefectures = '', $query = '') {
    try {
        $buildingService = new BuildingService();
        
        // prefectures、completionYears、queryフィルタリングを適用した建築家検索
        if (!empty($prefectures) || !empty($completionYears) || !empty($query)) {
            // フィルタリングが必要な場合は全件を取得してからフィルタリング
            // 全件を取得（大きなlimitを設定）
            $result = $buildingService->searchByArchitectSlug($architectSlug, 1, $lang, 1000);
            
            // 追加のフィルタリングを適用
            if (!empty($result['buildings'])) {
                $filteredBuildings = [];
                foreach ($result['buildings'] as $building) {
                    $include = true;
                    
                    // queryフィルタリング（キーワード検索）
                    if (!empty($query) && $include) {
                        $queryLower = mb_strtolower($query);
                        $titleMatch = mb_strpos(mb_strtolower($building['title']), $queryLower) !== false;
                        $titleEnMatch = mb_strpos(mb_strtolower($building['titleEn']), $queryLower) !== false;
                        $locationMatch = mb_strpos(mb_strtolower($building['location']), $queryLower) !== false;
                        $locationEnMatch = mb_strpos(mb_strtolower($building['locationEn']), $queryLower) !== false;
                        $buildingTypesMatch = false;
                        $buildingTypesEnMatch = false;
                        
                        if (!empty($building['buildingTypes'])) {
                            foreach ($building['buildingTypes'] as $type) {
                                if (mb_strpos(mb_strtolower($type), $queryLower) !== false) {
                                    $buildingTypesMatch = true;
                                    break;
                                }
                            }
                        }
                        
                        if (!empty($building['buildingTypesEn'])) {
                            foreach ($building['buildingTypesEn'] as $typeEn) {
                                if (mb_strpos(mb_strtolower($typeEn), $queryLower) !== false) {
                                    $buildingTypesEnMatch = true;
                                    break;
                                }
                            }
                        }
                        
                        $include = $titleMatch || $titleEnMatch || $locationMatch || $locationEnMatch || $buildingTypesMatch || $buildingTypesEnMatch;
                    }
                    
                    // prefecturesフィルタリング
                    if (!empty($prefectures) && $include) {
                        // 都道府県の英語名→日本語名マッピング
                        $prefectureTranslations = [
                            'Hokkaido' => '北海道',
                            'Aomori' => '青森県',
                            'Iwate' => '岩手県',
                            'Miyagi' => '宮城県',
                            'Akita' => '秋田県',
                            'Yamagata' => '山形県',
                            'Fukushima' => '福島県',
                            'Ibaraki' => '茨城県',
                            'Tochigi' => '栃木県',
                            'Gunma' => '群馬県',
                            'Saitama' => '埼玉県',
                            'Chiba' => '千葉県',
                            'Tokyo' => '東京都',
                            'Kanagawa' => '神奈川県',
                            'Niigata' => '新潟県',
                            'Toyama' => '富山県',
                            'Ishikawa' => '石川県',
                            'Fukui' => '福井県',
                            'Yamanashi' => '山梨県',
                            'Nagano' => '長野県',
                            'Gifu' => '岐阜県',
                            'Shizuoka' => '静岡県',
                            'Aichi' => '愛知県',
                            'Mie' => '三重県',
                            'Shiga' => '滋賀県',
                            'Kyoto' => '京都府',
                            'Osaka' => '大阪府',
                            'Hyogo' => '兵庫県',
                            'Nara' => '奈良県',
                            'Wakayama' => '和歌山県',
                            'Tottori' => '鳥取県',
                            'Shimane' => '島根県',
                            'Okayama' => '岡山県',
                            'Hiroshima' => '広島県',
                            'Yamaguchi' => '山口県',
                            'Tokushima' => '徳島県',
                            'Kagawa' => '香川県',
                            'Ehime' => '愛媛県',
                            'Kochi' => '高知県',
                            'Fukuoka' => '福岡県',
                            'Saga' => '佐賀県',
                            'Nagasaki' => '長崎県',
                            'Kumamoto' => '熊本県',
                            'Oita' => '大分県',
                            'Miyazaki' => '宮崎県',
                            'Kagoshima' => '鹿児島県',
                            'Okinawa' => '沖縄県'
                        ];
                        
                        // 英語名を日本語名に変換
                        $japaneseName = isset($prefectureTranslations[$prefectures]) ? $prefectureTranslations[$prefectures] : $prefectures;
                        
                        // prefecturesフィールド（日本語名）またはprefecturesEnフィールド（英語名）と比較
                        $prefectureMatch = false;
                        if (isset($building['prefectures']) && !empty($building['prefectures'])) {
                            // 日本語名で部分一致検索（LIKE検索と同様の動作）
                            $prefectureMatch = mb_strpos($building['prefectures'], $japaneseName) !== false;
                        }
                        if (!$prefectureMatch && isset($building['prefecturesEn']) && !empty($building['prefecturesEn'])) {
                            // 英語名で完全一致検索
                            $prefectureMatch = $building['prefecturesEn'] === $prefectures;
                        }
                        
                        $include = $prefectureMatch;
                    }
                    
                    // completionYearsフィルタリング
                    if (!empty($completionYears) && $include) {
                        $include = $building['completionYears'] === $completionYears;
                    }
                    
                    if ($include) {
                        $filteredBuildings[] = $building;
                    }
                }
                
                // フィルタリング後にページネーションを適用
                $totalFiltered = count($filteredBuildings);
                $offset = ($page - 1) * $limit;
                $pagedBuildings = array_slice($filteredBuildings, $offset, $limit);
                
                $result['buildings'] = $pagedBuildings;
                $result['total'] = $totalFiltered;
                $result['totalPages'] = ceil($totalFiltered / $limit);
                $result['currentPage'] = $page;
            }
        } else {
            // 通常の建築家検索
            $result = $buildingService->searchByArchitectSlug($architectSlug, $page, $lang, $limit);
        }
        
        // 建築家情報を取得して追加
        try {
            require_once __DIR__ . '/../../Services/ArchitectService.php';
            $architectService = new ArchitectService();
            $architectInfo = $architectService->getBySlug($architectSlug, $lang);
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                error_log("searchBuildingsByArchitectSlug - architectInfo: " . print_r($architectInfo, true));
            }
        } catch (Exception $e) {
            error_log("searchBuildingsByArchitectSlug - ArchitectService error: " . $e->getMessage());
            $architectInfo = null;
        }
        
        // 建築家ページ閲覧ログを記録
        if ($architectInfo) {
            try {
                require_once __DIR__ . '/../../Services/SearchLogService.php';
                $searchLogService = new SearchLogService();
                $searchLogService->logPageView('architect', $architectSlug, $architectInfo['name_ja'] ?? $architectInfo['name_en'] ?? $architectSlug, [
                    'architect_id' => $architectInfo['individual_architect_id'] ?? null,
                    'lang' => $lang
                ]);
            } catch (Exception $e) {
                error_log("Architect page view log error: " . $e->getMessage());
            }
        }
        
        $result['architectInfo'] = $architectInfo;
        
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            error_log("searchBuildingsByArchitectSlug - Final result: " . print_r($result, true));
        }
        
        return $result;
    } catch (Exception $e) {
        ErrorHandler::log("Architect search error: " . $e->getMessage(), ErrorHandler::LOG_LEVEL_ERROR, [
            'architectSlug' => $architectSlug,
            'page' => $page,
            'lang' => $lang,
            'limit' => $limit,
            'completionYears' => $completionYears,
            'prefectures' => $prefectures,
            'query' => $query
        ]);
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page,
            'architectInfo' => null
        ];
    }
}

/**
 * スラッグで建築家を取得（新しいクラスベース設計に移行）
 */
function getArchitectBySlug($slug, $lang = 'ja') {
    try {
        $architectService = new ArchitectService();
        return $architectService->getBySlug($slug, $lang);
    } catch (Exception $e) {
        ErrorHandler::log("Get architect by slug error: " . $e->getMessage(), ErrorHandler::LOG_LEVEL_ERROR, [
            'slug' => $slug,
            'lang' => $lang
        ]);
        return null;
    }
}

/**
 * 人気検索を取得（新しいクラスベース設計に移行）
 */
function getPopularSearches($lang = 'ja') {
    try {
        $architectService = new ArchitectService();
        return $architectService->getPopularSearches($lang);
    } catch (Exception $e) {
        ErrorHandler::log("Get popular searches error: " . $e->getMessage(), ErrorHandler::LOG_LEVEL_ERROR, [
            'lang' => $lang
        ]);
        // フォールバック用の固定データ
        return [
            ['query' => '安藤忠雄', 'count' => 45],
            ['query' => '美術館', 'count' => 38],
            ['query' => '東京', 'count' => 32],
            ['query' => '現代建築', 'count' => 28]
        ];
    }
}

/**
 * 翻訳関数
 */
function t($key, $lang = 'ja') {
    $translations = [
        'ja' => [
            'searchPlaceholder' => '建築物名、建築家、場所で検索...',
            'search' => '検索',
            'currentLocation' => '現在地を検索',
            'detailedSearch' => '詳細検索',
            'withPhotos' => '写真あり',
            'withVideos' => '動画あり',
            'clearFilters' => 'クリア',
            'loading' => '読み込み中...',
            'searchAround' => '周辺を検索',
            'getDirections' => '道順を取得',
            'viewOnGoogleMap' => 'Googleマップで表示',
            'buildingDetails' => '建築物詳細',
            'backToList' => '一覧に戻る',
            'architect' => '建築家',
            'location' => '所在地',
            'prefecture' => '都道府県',
            'buildingTypes' => '建物用途',
            'completionYear' => '完成年',
            'photos' => '写真',
            'videos' => '動画',
            'popularSearches' => '人気の検索',
            'noBuildingsFound' => '建築物が見つかりませんでした。',
            'loadingMap' => '地図を読み込み中...',
            'currentLocation' => '現在地を検索'
        ],
        'en' => [
            'searchPlaceholder' => 'Search by building name, architect, location...',
            'search' => 'Search',
            'currentLocation' => 'Search Current Location',
            'detailedSearch' => 'Detailed Search',
            'withPhotos' => 'With Photos',
            'withVideos' => 'With Videos',
            'clearFilters' => 'Clear',
            'loading' => 'Loading...',
            'searchAround' => 'Search Around',
            'getDirections' => 'Get Directions',
            'viewOnGoogleMap' => 'View on Google Maps',
            'buildingDetails' => 'Building Details',
            'backToList' => 'Back to List',
            'architect' => 'Architect',
            'location' => 'Location',
            'prefecture' => 'Prefecture',
            'buildingTypes' => 'Building Types',
            'completionYear' => 'Completion Year',
            'photos' => 'Photos',
            'videos' => 'Videos',
            'popularSearches' => 'Popular Searches',
            'noBuildingsFound' => 'No buildings found.',
            'loadingMap' => 'Loading map...',
            'currentLocation' => 'Search Current Location'
        ]
    ];
    
    return $translations[$lang][$key] ?? $key;
}

/**
 * ページネーションの範囲を取得
 */
function getPaginationRange($currentPage, $totalPages, $maxVisible = 7) {
    if ($totalPages <= $maxVisible) {
        // 総ページ数が表示可能数以下の場合は全て表示
        return range(1, $totalPages);
    }
    
    $pages = [];
    
    // 常に1ページ目を追加
    $pages[] = 1;
    
    // 現在のページ周辺のページを計算
    $half = floor(($maxVisible - 2) / 2); // 1と最終ページを除いた半分
    $start = max(2, $currentPage - $half);
    $end = min($totalPages - 1, $currentPage + $half);
    
    // 開始位置が2より大きい場合は「...」を追加
    if ($start > 2) {
        $pages[] = '...';
    }
    
    // 中間のページを追加
    for ($i = $start; $i <= $end; $i++) {
        if ($i != 1 && $i != $totalPages) { // 1と最終ページは既に追加済み
            $pages[] = $i;
        }
    }
    
    // 終了位置が最終ページより小さい場合は「...」を追加
    if ($end < $totalPages - 1) {
        $pages[] = '...';
    }
    
    // 常に最終ページを追加（1ページ目でない場合のみ）
    if ($totalPages > 1) {
        $pages[] = $totalPages;
    }
    
    return $pages;
}

/**
 * ポップアップコンテンツを生成
 */
function generatePopupContent($building, $lang = 'ja') {
    $title = $lang === 'ja' ? $building['title'] : $building['titleEn'];
    $location = $lang === 'ja' ? $building['location'] : $building['locationEn'];
    $architectNames = [];
    
    foreach ($building['architects'] as $architect) {
        $architectNames[] = $lang === 'ja' ? $architect['architectJa'] : $architect['architectEn'];
    }
    
    $architectText = implode(' / ', $architectNames);
    
    $content = "<div class='popup-content'>";
    $content .= "<h5 class='popup-title'><a href='/buildings/" . htmlspecialchars($building['slug']) . "?lang=" . $lang . "' style='color: #007bff; text-decoration: underline; cursor: pointer;'>" . htmlspecialchars($title) . "</a></h5>";
    
    if (!empty($location)) {
        $content .= "<p class='popup-location'><i data-lucide='map-pin' style='width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;'></i>" . htmlspecialchars($location) . "</p>";
    }
    
    $content .= "</div>";
    
    return $content;
}

/**
 * デバッグ用：データベースの状況を確認
 */
function debugDatabase() {
    $db = getDB();
    
    try {
        // 建築物の総数
        $stmt = $db->query("SELECT COUNT(*) as total FROM buildings_table_3");
        $buildingCount = $stmt->fetch()['total'];
        
        // 座標がある建築物の数
        $stmt = $db->query("SELECT COUNT(*) as total FROM buildings_table_3 WHERE lat IS NOT NULL AND lng IS NOT NULL");
        $buildingWithCoords = $stmt->fetch()['total'];
        
        // 東京を含む建築物の数
        $stmt = $db->query("SELECT COUNT(*) as total FROM buildings_table_3 WHERE location LIKE '%東京%' OR prefectures LIKE '%東京%'");
        $tokyoBuildings = $stmt->fetch()['total'];
        
        // サンプルデータの確認
        $stmt = $db->query("SELECT building_id, title, location, prefectures, lat, lng FROM buildings_table_3 LIMIT 5");
        $sampleData = $stmt->fetchAll();
        
        return [
            'buildingCount' => $buildingCount,
            'buildingWithCoords' => $buildingWithCoords,
            'tokyoBuildings' => $tokyoBuildings,
            'sampleData' => $sampleData
        ];
        
    } catch (Exception $e) {
        error_log("Debug database error: " . $e->getMessage());
        return null;
    }
}

// ============================================================================
// 新しいクラスベースの設計を利用するラッパー関数
// ============================================================================

/**
 * 建築物検索（新しいクラスベース設計）
 */
function searchBuildingsNew($query, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
    try {
        $buildingService = new BuildingService();
        return $buildingService->search($query, $page, $hasPhotos, $hasVideos, $lang, $limit);
    } catch (Exception $e) {
        return ErrorHandler::handleSearchError($e, $query);
    }
}

/**
 * 複数条件での建築物検索（新しいクラスベース設計）
 */
function searchBuildingsWithMultipleConditionsNew($query, $completionYears, $prefectures, $buildingTypes, $hasPhotos, $hasVideos, $page = 1, $lang = 'ja', $limit = 10) {
    try {
        $buildingService = new BuildingService();
        return $buildingService->searchWithMultipleConditions($query, $completionYears, $prefectures, $buildingTypes, $hasPhotos, $hasVideos, $page, $lang, $limit);
    } catch (Exception $e) {
        return ErrorHandler::handleSearchError($e, $query);
    }
}

/**
 * 位置情報による建築物検索（新しいクラスベース設計）
 */
function searchBuildingsByLocationNew($userLat, $userLng, $radiusKm = 5, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
    try {
        $buildingService = new BuildingService();
        return $buildingService->searchByLocation($userLat, $userLng, $radiusKm, $page, $hasPhotos, $hasVideos, $lang, $limit);
    } catch (Exception $e) {
        return ErrorHandler::handleSearchError($e, "Location: {$userLat}, {$userLng}");
    }
}

/**
 * 建築家による建築物検索（新しいクラスベース設計）
 */
function searchBuildingsByArchitectSlugNew($architectSlug, $page = 1, $lang = 'ja', $limit = 10) {
    try {
        $buildingService = new BuildingService();
        return $buildingService->searchByArchitectSlug($architectSlug, $page, $lang, $limit);
    } catch (Exception $e) {
        return ErrorHandler::handleSearchError($e, "Architect: {$architectSlug}");
    }
}

/**
 * スラッグで建築物を取得（新しいクラスベース設計）
 */
function getBuildingBySlugNew($slug, $lang = 'ja') {
    try {
        $buildingService = new BuildingService();
        return $buildingService->getBySlug($slug, $lang);
    } catch (Exception $e) {
        return ErrorHandler::handleSearchError($e, "Building: {$slug}");
    }
}

/**
 * スラッグで建築家を取得（新しいクラスベース設計）
 */
function getArchitectBySlugNew($slug, $lang = 'ja') {
    try {
        $architectService = new ArchitectService();
        return $architectService->getBySlug($slug, $lang);
    } catch (Exception $e) {
        return ErrorHandler::handleSearchError($e, "Architect: {$slug}");
    }
}

/**
 * 建築家の建築物一覧を取得（新しいクラスベース設計）
 */
function getArchitectBuildingsNew($architectId, $page = 1, $lang = 'ja', $limit = 10) {
    try {
        $architectService = new ArchitectService();
        return $architectService->getBuildings($architectId, $page, $lang, $limit);
    } catch (Exception $e) {
        return ErrorHandler::handleSearchError($e, "Architect ID: {$architectId}");
    }
}

/**
 * 人気検索語を取得（新しいクラスベース設計）
 */
function getPopularSearchesNew($lang = 'ja') {
    try {
        $architectService = new ArchitectService();
        return $architectService->getPopularSearches($lang);
    } catch (Exception $e) {
        return ErrorHandler::handleSearchError($e, "Popular searches");
    }
}

// ============================================================================
// 段階的移行のための関数エイリアス
// ============================================================================

// 既存の関数名を新しい実装にリダイレクト（段階的移行用）
// 注意: 本格的な移行時は、既存の関数を削除して新しい関数名に統一する

// 例: function searchBuildings() { return searchBuildingsNew(...); }
// ただし、既存の関数が既に存在するため、この段階では新しい関数名を使用

/**
 * 指定されたslugの建築家のslug_group_idを取得
 */
function getSlugGroupId($architectSlug) {
    try {
        $db = getDB();
        $sql = "SELECT slug_group_id FROM individual_architects_3 WHERE slug = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$architectSlug]);
        $result = $stmt->fetch();
        
        return $result ? $result['slug_group_id'] : null;
    } catch (Exception $e) {
        error_log("Error getting slug_group_id: " . $e->getMessage());
        return null;
    }
}

/**
 * 指定されたgroup_idに属するすべてのslugを取得
 */
function getSlugsByGroupId($groupId) {
    try {
        $db = getDB();
        $sql = "SELECT slug FROM slug_to_group WHERE group_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$groupId]);
        $results = $stmt->fetchAll();
        
        return array_column($results, 'slug');
    } catch (Exception $e) {
        error_log("Error getting slugs by group_id: " . $e->getMessage());
        return [];
    }
}
?>
