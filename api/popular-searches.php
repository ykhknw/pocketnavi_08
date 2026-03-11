<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


// OPTIONSリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // パラメータの取得
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
    $searchType = isset($_GET['search_type']) ? trim($_GET['search_type']) : ''; // タブ別フィルタ
    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'ja';
    
    // パラメータの検証
    if ($page < 1) $page = 1;
    if ($limit < 1 || $limit > 100) $limit = 20;
    
    // キャッシュファイルを直接読み込み
    $cacheFile = __DIR__ . '/../cache/popular_searches.php';
    $result = ['searches' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'totalPages' => 0];
    
    if (file_exists($cacheFile)) {
        $cacheData = include $cacheFile;
        
        if (is_array($cacheData) && isset($cacheData['data'])) {
            // 検索タイプに応じてデータを統合
            $allSearches = [];
            if (!empty($searchType)) {
                // 特定の検索タイプのデータをすべてのキーから収集
                foreach ($cacheData['data'] as $key => $data) {
                    if (isset($data['searches'])) {
                        foreach ($data['searches'] as $search) {
                            if ($search['search_type'] === $searchType) {
                                $allSearches[] = $search;
                            }
                        }
                    }
                }
            } else {
                // 「すべて」タブの場合は最初のキーを使用
                $targetKey = array_key_first($cacheData['data']);
                if ($targetKey && isset($cacheData['data'][$targetKey]['searches'])) {
                    $allSearches = $cacheData['data'][$targetKey]['searches'];
                }
            }
            
            if (!empty($allSearches)) {
                // 重複排除処理（query + search_type の組み合わせで一意化）
                // 同じ組み合わせが複数ある場合は、total_searches > unique_users > last_searched の優先順位で最大のものを残す
                $uniqueSearches = [];
                foreach ($allSearches as $search) {
                    $query = $search['query'] ?? '';
                    $searchType = $search['search_type'] ?? '';
                    $key = $query . '|' . $searchType;
                    
                    if (!isset($uniqueSearches[$key])) {
                        // 初めて見つかった場合はそのまま追加
                        $uniqueSearches[$key] = $search;
                    } else {
                        // 既に存在する場合、より良いデータを選択
                        $existing = $uniqueSearches[$key];
                        $shouldReplace = false;
                        
                        // total_searches が大きい方が優先
                        if ($search['total_searches'] > $existing['total_searches']) {
                            $shouldReplace = true;
                        } elseif ($search['total_searches'] === $existing['total_searches']) {
                            // total_searches が同じ場合は unique_users が大きい方が優先
                            if ($search['unique_users'] > $existing['unique_users']) {
                                $shouldReplace = true;
                            } elseif ($search['unique_users'] === $existing['unique_users']) {
                                // unique_users も同じ場合は last_searched が新しい方が優先
                                $timeA = isset($search['last_searched']) ? strtotime($search['last_searched']) : 0;
                                $timeB = isset($existing['last_searched']) ? strtotime($existing['last_searched']) : 0;
                                if ($timeA > $timeB) {
                                    $shouldReplace = true;
                                }
                            }
                        }
                        
                        if ($shouldReplace) {
                            $uniqueSearches[$key] = $search;
                        }
                    }
                }
                
                // 連想配列を通常の配列に変換
                $allSearches = array_values($uniqueSearches);
                
                // ソート処理（データベース取得時と同じ順序）
                // ORDER BY total_searches DESC, unique_users DESC, last_searched DESC
                usort($allSearches, function($a, $b) {
                    // total_searches DESC
                    if ($b['total_searches'] !== $a['total_searches']) {
                        return $b['total_searches'] - $a['total_searches'];
                    }
                    // unique_users DESC
                    if ($b['unique_users'] !== $a['unique_users']) {
                        return $b['unique_users'] - $a['unique_users'];
                    }
                    // last_searched DESC
                    $timeA = isset($a['last_searched']) ? strtotime($a['last_searched']) : 0;
                    $timeB = isset($b['last_searched']) ? strtotime($b['last_searched']) : 0;
                    return $timeB - $timeA;
                });
                
                // 検索タイプでフィルタリング（「すべて」タブの場合のみ）
                if (!empty($searchType) && empty($allSearches)) {
                    // 既にフィルタリング済みの場合はスキップ
                } elseif (empty($searchType)) {
                    // 「すべて」タブの場合はフィルタリング不要
                } else {
                    // 追加のフィルタリングが必要な場合
                    $allSearches = array_filter($allSearches, function($search) use ($searchType) {
                        return $search['search_type'] === $searchType;
                    });
                }
                
                // 検索クエリでフィルタリング
                if (!empty($searchQuery)) {
                    $allSearches = array_filter($allSearches, function($search) use ($searchQuery) {
                        return stripos($search['query'], $searchQuery) !== false;
                    });
                }
                
                // ページネーション処理
                $totalCount = count($allSearches);
                $offset = ($page - 1) * $limit;
                $searches = array_slice($allSearches, $offset, $limit);
                
                $result = [
                    'searches' => $searches,
                    'total' => $totalCount,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => ceil($totalCount / $limit)
                ];
            }
        }
    }
    
    // フォールバックデータ関数
    function getFallbackData($searchType, $page, $limit) {
        $sampleData = [
            'all' => [
                ['query' => '安藤忠雄', 'search_type' => 'architect', 'total_searches' => 15, 'unique_users' => 8, 'link' => '/architects/ando-tadao/'],
                ['query' => '隈研吾', 'search_type' => 'architect', 'total_searches' => 12, 'unique_users' => 6, 'link' => '/architects/kuma-kengo/'],
                ['query' => '丹下健三', 'search_type' => 'architect', 'total_searches' => 9, 'unique_users' => 4, 'link' => '/architects/tange-kenzo/'],
                ['query' => '東京', 'search_type' => 'prefecture', 'total_searches' => 20, 'unique_users' => 10, 'link' => '/prefectures/tokyo/'],
                ['query' => '大阪', 'search_type' => 'prefecture', 'total_searches' => 8, 'unique_users' => 4, 'link' => '/prefectures/osaka/'],
                ['query' => '京都', 'search_type' => 'prefecture', 'total_searches' => 6, 'unique_users' => 3, 'link' => '/prefectures/kyoto/'],
                ['query' => '国立代々木競技場', 'search_type' => 'building', 'total_searches' => 5, 'unique_users' => 3, 'link' => '/buildings/yoyogi-national-gymnasium/'],
                ['query' => '東京スカイツリー', 'search_type' => 'building', 'total_searches' => 4, 'unique_users' => 2, 'link' => '/buildings/tokyo-skytree/'],
                ['query' => '現代建築', 'search_type' => 'text', 'total_searches' => 10, 'unique_users' => 5, 'link' => '/index.php?q=現代建築'],
                ['query' => '住宅', 'search_type' => 'text', 'total_searches' => 7, 'unique_users' => 3, 'link' => '/index.php?q=住宅']
            ],
            'architect' => [
                ['query' => '安藤忠雄', 'search_type' => 'architect', 'total_searches' => 15, 'unique_users' => 8, 'link' => '/architects/ando-tadao/'],
                ['query' => '隈研吾', 'search_type' => 'architect', 'total_searches' => 12, 'unique_users' => 6, 'link' => '/architects/kuma-kengo/'],
                ['query' => '丹下健三', 'search_type' => 'architect', 'total_searches' => 9, 'unique_users' => 4, 'link' => '/architects/tange-kenzo/']
            ],
            'prefecture' => [
                ['query' => '東京', 'search_type' => 'prefecture', 'total_searches' => 20, 'unique_users' => 10, 'link' => '/prefectures/tokyo/'],
                ['query' => '大阪', 'search_type' => 'prefecture', 'total_searches' => 8, 'unique_users' => 4, 'link' => '/prefectures/osaka/'],
                ['query' => '京都', 'search_type' => 'prefecture', 'total_searches' => 6, 'unique_users' => 3, 'link' => '/prefectures/kyoto/']
            ],
            'building' => [
                ['query' => '国立代々木競技場', 'search_type' => 'building', 'total_searches' => 5, 'unique_users' => 3, 'link' => '/buildings/yoyogi-national-gymnasium/'],
                ['query' => '東京スカイツリー', 'search_type' => 'building', 'total_searches' => 4, 'unique_users' => 2, 'link' => '/buildings/tokyo-skytree/']
            ],
            'text' => [
                ['query' => '現代建築', 'search_type' => 'text', 'total_searches' => 10, 'unique_users' => 5, 'link' => '/index.php?q=現代建築'],
                ['query' => '住宅', 'search_type' => 'text', 'total_searches' => 7, 'unique_users' => 3, 'link' => '/index.php?q=住宅']
            ]
        ];
        
        $searches = $sampleData[$searchType] ?? $sampleData['all'];
        $totalCount = count($searches);
        $offset = ($page - 1) * $limit;
        $searches = array_slice($searches, $offset, $limit);
        
        return [
            'searches' => $searches,
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($totalCount / $limit)
        ];
    }
    
    // キャッシュが無効またはデータがない場合はフォールバックデータを使用
    if (empty($result['searches'])) {
        $result = getFallbackData($searchType, $page, $limit);
    }
    
    // レスポンスの準備
    $response = [
        'success' => true,
        'data' => [
            'searches' => $result['searches'],
            'pagination' => [
                'current_page' => $result['page'],
                'per_page' => $result['limit'],
                'total' => $result['total'],
                'total_pages' => $result['totalPages'],
                'has_next' => $result['page'] < $result['totalPages'],
                'has_prev' => $result['page'] > 1
            ]
        ],
        'lang' => $lang
    ];
    
    // 検索結果をHTMLに変換
    $html = '';
    if (!empty($result['searches'])) {
        $html .= '<div class="list-group list-group-flush">';
        
        foreach ($result['searches'] as $search) {
            $searchTypeLabel = '';
            $pageTypeLabel = '';
            
            // 検索タイプのラベル
            switch ($search['search_type']) {
                case 'text':
                    $searchTypeLabel = $lang === 'ja' ? 'テキスト' : 'Text';
                    break;
                case 'architect':
                    $searchTypeLabel = $lang === 'ja' ? '建築家' : 'Architect';
                    break;
                case 'prefecture':
                    $searchTypeLabel = $lang === 'ja' ? '都道府県' : 'Prefecture';
                    break;
                case 'building':
                    $searchTypeLabel = $lang === 'ja' ? '建築物' : 'Building';
                    break;
            }
            
            // ページタイプのラベル
            if (isset($search['page_type']) && $search['page_type']) {
                switch ($search['page_type']) {
                    case 'architect':
                        $pageTypeLabel = $lang === 'ja' ? '建築家ページ' : 'Architect Page';
                        break;
                    case 'building':
                        $pageTypeLabel = $lang === 'ja' ? '建築物ページ' : 'Building Page';
                        break;
                    case 'prefecture':
                        $pageTypeLabel = $lang === 'ja' ? '都道府県ページ' : 'Prefecture Page';
                        break;
                }
            }
            
            // 表示用タイトルを決定（英語ユーザー向け対応）
            $displayTitle = $search['query'];
            if ($lang === 'en') {
                // 英語ユーザーの場合、適切な英語表示データを使用
                $filters = json_decode($search['filters'] ?? '{}', true);
                
                // 都道府県検索の場合
                if ($search['search_type'] === 'prefecture' && isset($filters['prefecture_en']) && !empty($filters['prefecture_en'])) {
                    $displayTitle = $filters['prefecture_en'];
                }
                // 建築物・建築家検索の場合
                else if (isset($filters['title_en']) && !empty($filters['title_en'])) {
                    $displayTitle = $filters['title_en'];
                }
            }
            
            // リンクを生成
            $link = $search['link'] ?? '/index.php?q=' . urlencode($search['query']);
            if (strpos($link, '?') !== false) {
                $link .= '&lang=' . $lang;
            } else {
                $link .= '?lang=' . $lang;
            }
            
            $html .= sprintf(
                '<a href="%s" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">',
                htmlspecialchars($link)
            );
            $html .= '<div class="d-flex flex-column">';
            $html .= sprintf('<span class="fw-medium">%s</span>', htmlspecialchars($displayTitle));
            // 「すべて」タブの場合のみカテゴリラベルを表示
            if (empty($searchType)) {
                if ($pageTypeLabel) {
                    $html .= sprintf('<small class="text-muted">%s</small>', $pageTypeLabel);
                } else {
                    $html .= sprintf('<small class="text-muted">%s</small>', $searchTypeLabel);
                }
            }
            $html .= '</div>';
            $html .= '<div class="d-flex flex-column align-items-end">';
            $html .= sprintf('<span class="badge bg-primary rounded-pill">%d</span>', $search['total_searches']);
            // 「すべて」タブの場合のみユーザー数を表示
            if (empty($searchType)) {
                $html .= sprintf('<small class="text-muted">%d %s</small>', 
                    $search['unique_users'], 
                    $lang === 'ja' ? 'ユーザー' : 'users'
                );
            }
            $html .= '</div>';
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        // ページネーション
        if ($result['totalPages'] > 1) {
            $html .= '<nav aria-label="Popular searches pagination" class="mt-3">';
            $html .= '<ul class="pagination justify-content-center">';
            
            // 前のページ
            if ($result['page'] > 1) {
                $html .= sprintf(
                    '<li class="page-item"><a class="page-link" href="#" onclick="loadPopularSearchesPage(%d)">%s</a></li>',
                    $result['page'] - 1,
                    $lang === 'ja' ? '前へ' : 'Previous'
                );
            }
            
            // ページ番号
            $startPage = max(1, $result['page'] - 2);
            $endPage = min($result['totalPages'], $result['page'] + 2);
            
            if ($startPage > 1) {
                $html .= '<li class="page-item"><a class="page-link" href="#" onclick="loadPopularSearchesPage(1)">1</a></li>';
                if ($startPage > 2) {
                    $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            for ($i = $startPage; $i <= $endPage; $i++) {
                $activeClass = $i === $result['page'] ? ' active' : '';
                $html .= sprintf(
                    '<li class="page-item%s"><a class="page-link" href="#" onclick="loadPopularSearchesPage(%d)">%d</a></li>',
                    $activeClass,
                    $i,
                    $i
                );
            }
            
            if ($endPage < $result['totalPages']) {
                if ($endPage < $result['totalPages'] - 1) {
                    $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                $html .= sprintf(
                    '<li class="page-item"><a class="page-link" href="#" onclick="loadPopularSearchesPage(%d)">%d</a></li>',
                    $result['totalPages'],
                    $result['totalPages']
                );
            }
            
            // 次のページ
            if ($result['page'] < $result['totalPages']) {
                $html .= sprintf(
                    '<li class="page-item"><a class="page-link" href="#" onclick="loadPopularSearchesPage(%d)">%s</a></li>',
                    $result['page'] + 1,
                    $lang === 'ja' ? '次へ' : 'Next'
                );
            }
            
            $html .= '</ul>';
            $html .= '</nav>';
        }
        
    } else {
        $html = sprintf(
            '<div class="text-center py-4"><p class="text-muted">%s</p></div>',
            $lang === 'ja' ? '該当する検索ワードが見つかりませんでした。' : 'No search terms found.'
        );
    }
    
    $response['data']['html'] = $html;
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Popular searches API error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response = [
        'success' => false,
        'error' => [
            'message' => $lang === 'ja' ? 'データの取得に失敗しました。' : 'Failed to fetch data.',
            'code' => 'FETCH_ERROR',
            'details' => $e->getMessage()
        ],
        'lang' => $lang
    ];
    
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>
