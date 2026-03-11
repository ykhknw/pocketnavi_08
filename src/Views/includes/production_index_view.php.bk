<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Permissions Policy for Geolocation -->
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    
    <!-- CSRF Token -->
    <?php echo csrfTokenMeta('search'); ?>
    
    <!-- SEO Meta Tags -->
    <title><?php echo htmlspecialchars($seoData['title'] ?? 'PocketNavi - 建築物検索'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seoData['description'] ?? '建築物を検索できるサイト'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seoData['keywords'] ?? '建築物,検索,建築家'); ?>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" href="/assets/images/landmark.svg" type="image/svg+xml">

<!-- 早期エラーフィルタリング（最優先） -->
<script>
(function() {
    // 外部スクリプトエラーのフィルタリング（最早期版）
    window.addEventListener('error', function(event) {
        // 外部ブラウザ拡張機能のエラーを無視
        if (event.filename && (
            event.filename.includes('content.js') ||
            event.filename.includes('inject.js') ||
            event.filename.includes('main.js') ||
            event.filename.includes('chrome-extension://') ||
            event.filename.includes('moz-extension://') ||
            event.filename.includes('safari-extension://') ||
            event.filename.includes('extension://')
        )) {
            event.preventDefault();
            event.stopPropagation();
            return false;
        }
        
        // 特定のエラーメッセージを無視
        if (event.message && (
            event.message.includes('priceAreaElement is not defined') ||
            event.message.includes('Photo gallery card not found') ||
            event.message.includes('document.write()') ||
            event.message.includes('Avoid using document.write()') ||
            event.message.includes('Port connected')
        )) {
            event.preventDefault();
            event.stopPropagation();
            return false;
        }
    });
    
    // コンソール出力のフィルタリング（強化版）
    const originalWarn = console.warn;
    const originalError = console.error;
    const originalLog = console.log;
    const originalInfo = console.info;
    
    function shouldFilterMessage(message) {
        return message.includes('Avoid using document.write()') ||
               message.includes('document.write()') ||
               message.includes('Port connected') ||
               message.includes('コンテンツスクリプト実行中') ||
               message.includes('priceAreaElement is not defined') ||
               message.includes('Photo gallery card not found') ||
               message.includes('Initializing photo gallery') ||
               message.includes('MetaMask encountered an error') ||
               message.includes('Cannot set property ethereum') ||
               message.includes('Cannot redefine property: ethereum') ||
               message.includes('Could not establish connection') ||
               message.includes('Receiving end does not exist') ||
               message.includes('inpage.js') ||
               message.includes('evmAsk.js') ||
               message.includes('content.js') ||
               message.includes('inject.js') ||
               message.includes('main.js');
    }
    
    console.warn = function(...args) {
        const message = args.join(' ');
        if (shouldFilterMessage(message)) {
            return;
        }
        originalWarn.apply(console, args);
    };
    
    console.error = function(...args) {
        const message = args.join(' ');
        if (shouldFilterMessage(message)) {
            return;
        }
        originalError.apply(console, args);
    };
    
    console.log = function(...args) {
        const message = args.join(' ');
        if (shouldFilterMessage(message)) {
            return;
        }
        originalLog.apply(console, args);
    };
    
    console.info = function(...args) {
        const message = args.join(' ');
        if (shouldFilterMessage(message)) {
            return;
        }
        originalInfo.apply(console, args);
    };
})();
</script>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-9FY04VHM17"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-9FY04VHM17');
</script>

<!-- CSRFManager（本番環境用） -->
<script>
// CSRFトークン管理（本番環境用）
if (typeof CSRFManager === 'undefined') {
    class CSRFManager {
        static getToken() {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            return metaTag ? metaTag.getAttribute('content') : null;
        }
        
        static addToRequest(options = {}) {
            const token = this.getToken();
            if (!token) return options;
            
            // ヘッダーに追加
            if (!options.headers) {
                options.headers = {};
            }
            options.headers['X-CSRF-Token'] = token;
            
            // POSTデータに追加
            if (options.method && options.method.toUpperCase() === 'POST') {
                if (!options.body) {
                    options.body = new FormData();
                }
                if (options.body instanceof FormData) {
                    options.body.append('csrf_token', token);
                } else if (typeof options.body === 'string') {
                    try {
                        const data = JSON.parse(options.body);
                        data.csrf_token = token;
                        options.body = JSON.stringify(data);
                    } catch (e) {
                        // JSONでない場合はFormDataに変換
                        const formData = new FormData();
                        formData.append('csrf_token', token);
                        formData.append('data', options.body);
                        options.body = formData;
                    }
                }
            }
            
            return options;
        }
    }
    
    // グローバルに公開
    window.CSRFManager = CSRFManager;
}
</script>

</head>
<body>
    <!-- Header -->
    <?php include 'src/Views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Search Form -->
                <?php include 'src/Views/includes/search_form.php'; ?>
                
                <!-- Current Search Context Display -->
                <?php if ($architectsSlug && isset($architectInfo) && $architectInfo): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h2 class="h4 mb-2">
                                <i data-lucide="circle-user-round" class="me-2" style="width: 20px; height: 20px;"></i>
                                <?php echo $lang === 'ja' ? '建築家' : 'Architect'; ?>: 
                                <span class="text-primary"><?php echo htmlspecialchars($lang === 'ja' ? ($architectInfo['nameJa'] ?? $architectInfo['nameEn'] ?? '') : ($architectInfo['nameEn'] ?? $architectInfo['nameJa'] ?? '')); ?></span>
                            </h2>
                            <?php if ($lang === 'ja' && !empty($architectInfo['nameEn']) && $architectInfo['nameJa'] !== $architectInfo['nameEn']): ?>
                                <p class="text-muted mb-0">
                                    <?php echo htmlspecialchars($architectInfo['nameEn']); ?>
                                </p>
                            <?php elseif ($lang === 'en' && !empty($architectInfo['nameJa']) && $architectInfo['nameJa'] !== $architectInfo['nameEn']): ?>
                                <p class="text-muted mb-0">
                                    <?php echo htmlspecialchars($architectInfo['nameJa']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($architectInfo['individual_website'])): ?>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-end align-items-center">
                                    <a href="<?php echo htmlspecialchars($architectInfo['individual_website']); ?>" 
                                       target="_blank" 
                                       class="btn btn-outline-secondary btn-sm"
                                       title="<?php echo $lang === 'ja' ? '関連サイトを見る' : 'Visit Related Site'; ?>">
                                        <i data-lucide="square-mouse-pointer" style="width: 16px; height: 16px;"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($buildingSlug && $currentBuilding): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h2 class="h4 mb-2">
                                <i data-lucide="building" class="me-2" style="width: 20px; height: 20px;"></i>
                                <?php echo $lang === 'ja' ? '建築物' : 'Building'; ?>: 
                                <span class="text-primary"><?php echo htmlspecialchars($lang === 'ja' ? ($currentBuilding['title'] ?? '') : ($currentBuilding['titleEn'] ?? $currentBuilding['title'] ?? '')); ?></span>
                            </h2>
                            <?php if ($lang === 'ja' && !empty($currentBuilding['titleEn']) && $currentBuilding['title'] !== $currentBuilding['titleEn']): ?>
                                <p class="text-muted mb-0">
                                    <?php echo htmlspecialchars($currentBuilding['titleEn']); ?>
                                </p>
                            <?php elseif ($lang === 'en' && !empty($currentBuilding['title']) && $currentBuilding['title'] !== $currentBuilding['titleEn']): ?>
                                <p class="text-muted mb-0">
                                    <?php echo htmlspecialchars($currentBuilding['title']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Image Search Links -->
                            <div class="mt-3">
                                <p class="mb-2">
                                    <i data-lucide="search" class="me-1" style="width: 16px; height: 16px;"></i>
                                    <?php echo $lang === 'ja' ? '画像検索で見る' : 'View in Image Search'; ?>:
                                </p>
                                <div class="d-flex gap-3 flex-wrap">
                                    <?php 
                                    $buildingName = $currentBuilding['title'] ?? '';
                                    $encodedName = urlencode($buildingName);
                                    ?>
                                    <a href="https://www.google.com/search?q=<?php echo $encodedName; ?>&tbm=isch" 
                                       target="_blank" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i data-lucide="external-link" class="me-1" style="width: 14px; height: 14px;"></i>
                                        <?php echo $lang === 'ja' ? 'Google画像検索' : 'Google Image Search'; ?>
                                    </a>
                                    <a href="https://www.bing.com/images/search?q=<?php echo $encodedName; ?>" 
                                       target="_blank" 
                                       class="btn btn-outline-secondary btn-sm">
                                        <i data-lucide="external-link" class="me-1" style="width: 14px; height: 14px;"></i>
                                        <?php echo $lang === 'ja' ? 'Microsoft Bing画像検索' : 'Microsoft Bing Image Search'; ?>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Video Links -->
                            <?php if (!empty($currentBuilding['youtubeUrl'])): ?>
                                <div class="mt-3">
                                    <p class="mb-2">
                                        <i data-lucide="video" class="me-1" style="width: 16px; height: 16px;"></i>
                                        <?php echo $lang === 'ja' ? '動画で見る' : 'View in Video'; ?>:
                                    </p>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <a href="<?php echo htmlspecialchars($currentBuilding['youtubeUrl']); ?>" 
                                           target="_blank" 
                                           class="btn btn-outline-danger btn-sm">
                                            <i data-lucide="youtube" class="me-1" style="width: 14px; height: 14px;"></i>
                                            <?php echo $lang === 'ja' ? 'Youtubeで見る' : 'Watch on YouTube'; ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Search Results Header -->
                <?php if ($hasPhotos || $hasVideos || $completionYears || $prefectures || $query || $architectsSlug): ?>
                    <div class="alert alert-light mb-3">
                        <h6 class="mb-2">
                            <i data-lucide="filter" class="me-2" style="width: 16px; height: 16px;"></i>
                            <?php echo $lang === 'ja' ? 'フィルター適用済み' : 'Filters Applied'; ?>
                        </h6>
                        <div class="d-flex gap-3 flex-wrap mb-2">
                            <?php if ($architectsSlug): ?>
                                <span class="architect-badge filter-badge">
                                    <i data-lucide="circle-user-round" class="me-1" style="width: 12px; height: 12px;"></i>
                                    <?php 
                                    $architectName = '';
                                    if (isset($architectInfo) && $architectInfo) {
                                        $architectName = $lang === 'ja' ? 
                                            ($architectInfo['name_ja'] ?? $architectInfo['name_en'] ?? '') : 
                                            ($architectInfo['name_en'] ?? $architectInfo['name_ja'] ?? '');
                                    }
                                    if (empty($architectName)) {
                                        $architectName = str_replace('-', ' ', $architectsSlug);
                                        $architectName = ucwords($architectName);
                                    }
                                    echo htmlspecialchars($architectName); 
                                    ?>
                                    <a href="/?<?php 
                                        $removeArchitectFilter = $_GET;
                                        unset($removeArchitectFilter['architects_slug']);
                                        echo http_build_query($removeArchitectFilter); 
                                    ?>" 
                                       class="filter-remove-btn ms-2" 
                                       title="<?php echo $lang === 'ja' ? 'フィルターを解除' : 'Remove filter'; ?>">
                                        <i data-lucide="x" style="width: 12px; height: 12px;"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            <?php if ($hasPhotos): ?>
                                <span class="architect-badge filter-badge">
                                    <i data-lucide="image" class="me-1" style="width: 12px; height: 12px;"></i>
                                    <?php echo $lang === 'ja' ? '写真あり' : 'With Photos'; ?>
                                    <a href="?<?php 
                                        $removePhotosFilter = $_GET;
                                        unset($removePhotosFilter['photos']);
                                        echo http_build_query($removePhotosFilter); 
                                    ?>" 
                                       class="filter-remove-btn ms-2" 
                                       title="<?php echo $lang === 'ja' ? 'フィルターを解除' : 'Remove filter'; ?>">
                                        <i data-lucide="x" style="width: 12px; height: 12px;"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            <?php if ($hasVideos): ?>
                                <span class="architect-badge filter-badge">
                                    <i data-lucide="youtube" class="me-1" style="width: 12px; height: 12px;"></i>
                                    <?php echo $lang === 'ja' ? '動画あり' : 'With Videos'; ?>
                                    <a href="?<?php 
                                        $removeVideosFilter = $_GET;
                                        unset($removeVideosFilter['videos']);
                                        echo http_build_query($removeVideosFilter); 
                                    ?>" 
                                       class="filter-remove-btn ms-2" 
                                       title="<?php echo $lang === 'ja' ? 'フィルターを解除' : 'Remove filter'; ?>">
                                        <i data-lucide="x" style="width: 12px; height: 12px;"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            <?php if ($completionYears): ?>
                                <span class="architect-badge filter-badge">
                                    <i data-lucide="calendar" class="me-1" style="width: 12px; height: 12px;"></i>
                                    <?php echo htmlspecialchars($completionYears); ?>
                                    <a href="?<?php 
                                        $removeCompletionYearsFilter = $_GET;
                                        unset($removeCompletionYearsFilter['completionYears']);
                                        echo http_build_query($removeCompletionYearsFilter); 
                                    ?>" 
                                       class="filter-remove-btn ms-2" 
                                       title="<?php echo $lang === 'ja' ? 'フィルターを解除' : 'Remove filter'; ?>">
                                        <i data-lucide="x" style="width: 12px; height: 12px;"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            <?php if ($prefectures): ?>
                                <span class="architect-badge filter-badge">
                                    <i data-lucide="map-pin" class="me-1" style="width: 12px; height: 12px;"></i>
                                    <?php 
                                    // 都道府県の英語名から日本語名への変換
                                    $prefectureMap = [
                                        'Aichi' => '愛知県',
                                        'Tokyo' => '東京都',
                                        'Osaka' => '大阪府',
                                        'Kyoto' => '京都府',
                                        'Kanagawa' => '神奈川県',
                                        'Saitama' => '埼玉県',
                                        'Chiba' => '千葉県',
                                        'Hyogo' => '兵庫県',
                                        'Fukuoka' => '福岡県',
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
                                        'Niigata' => '新潟県',
                                        'Toyama' => '富山県',
                                        'Ishikawa' => '石川県',
                                        'Fukui' => '福井県',
                                        'Yamanashi' => '山梨県',
                                        'Nagano' => '長野県',
                                        'Gifu' => '岐阜県',
                                        'Shizuoka' => '静岡県',
                                        'Mie' => '三重県',
                                        'Shiga' => '滋賀県',
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
                                        'Saga' => '佐賀県',
                                        'Nagasaki' => '長崎県',
                                        'Kumamoto' => '熊本県',
                                        'Oita' => '大分県',
                                        'Miyazaki' => '宮崎県',
                                        'Kagoshima' => '鹿児島県',
                                        'Okinawa' => '沖縄県'
                                    ];
                                    
                                    $displayName = $lang === 'ja' ? 
                                        ($prefectureMap[$prefectures] ?? $prefectures) : 
                                        $prefectures;
                                    echo htmlspecialchars($displayName); 
                                    ?>
                                    <a href="?<?php 
                                        $removePrefecturesFilter = $_GET;
                                        unset($removePrefecturesFilter['prefectures']);
                                        echo http_build_query($removePrefecturesFilter); 
                                    ?>" 
                                       class="filter-remove-btn ms-2" 
                                       title="<?php echo $lang === 'ja' ? 'フィルターを解除' : 'Remove filter'; ?>">
                                        <i data-lucide="x" style="width: 12px; height: 12px;"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            <?php if ($query): ?>
                                <?php 
                                $keywords = preg_split('/[\s　]+/u', trim($query));
                                $keywords = array_filter($keywords, function($keyword) {
                                    return !empty(trim($keyword));
                                });
                                ?>
                                <?php foreach ($keywords as $index => $keyword): ?>
                                    <span class="architect-badge filter-badge">
                                        <i data-lucide="search" class="me-1" style="width: 12px; height: 12px;"></i>
                                        <?php echo htmlspecialchars($keyword); ?>
                                        <a href="?<?php 
                                            $remainingKeywords = $keywords;
                                            unset($remainingKeywords[$index]);
                                            $newQuery = implode(' ', $remainingKeywords);
                                            echo http_build_query(array_merge($_GET, ['q' => $newQuery ?: null])); 
                                        ?>" 
                                           class="filter-remove-btn ms-2" 
                                           title="<?php echo $lang === 'ja' ? 'このキーワードを削除' : 'Remove this keyword'; ?>">
                                            <i data-lucide="x" style="width: 12px; height: 12px;"></i>
                                        </a>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <!-- 検索結果件数表示 -->
                        <div class="search-results-summary mt-2">
                            <p class="mb-0 text-muted">
                                <i data-lucide="search" class="me-1" style="width: 14px; height: 14px;"></i>
                                検索結果: <strong><?php echo number_format($totalBuildings); ?>件</strong>の建築物が見つかりました
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- 検索結果がない場合の件数表示 -->
                <?php if (empty($buildings) && ($hasPhotos || $hasVideos || $completionYears || $prefectures || $query || $architectsSlug)): ?>
                    <div class="alert alert-light mb-3">
                        <div class="search-results-summary">
                            <p class="mb-0 text-muted">
                                <i data-lucide="search" class="me-1" style="width: 14px; height: 14px;"></i>
                                検索結果: <strong>0件</strong>の建築物が見つかりました
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Building Cards -->
                <div class="row" id="building-cards">
                    <?php if (empty($buildings)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <?php echo $lang === 'ja' ? '建築物が見つかりませんでした。' : 'No buildings found.'; ?>
                                <?php if ($query): ?>
                                    <br><small>検索キーワード: "<?php echo htmlspecialchars($query); ?>"</small>
                                <?php endif; ?>
                                <?php if ($hasPhotos): ?>
                                    <br><small>写真フィルター: 有効</small>
                                <?php endif; ?>
                                <?php if ($hasVideos): ?>
                                    <br><small>動画フィルター: 有効</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($buildings as $index => $building): ?>
                            <div class="col-12 mb-4">
                                <?php 
                                $globalIndex = ($currentPage - 1) * $limit + $index + 1;
                                ?>
                                <?php include 'src/Views/includes/building_card.php'; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <?php include 'src/Views/includes/pagination.php'; ?>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <?php include 'src/Views/includes/sidebar.php'; ?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'src/Views/includes/footer.php'; ?>
    
    <!-- Photo Carousel Modal -->
    <?php include 'src/Views/includes/photo_carousel_modal.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Custom JS -->
    <script src="/assets/js/main.js"></script>
    <!-- Popular Searches JS -->
    <script src="/assets/js/popular-searches.js"></script>
    
    <script>
        // ページ情報をJavaScriptに渡す
        window.pageInfo = {
            currentPage: <?php echo $currentPage; ?>,
            limit: <?php echo $limit; ?>
        };
        
        // 建築物データをJavaScriptに渡す
        window.buildingsData = <?php echo json_encode($buildings); ?>;
        
        // 検索結果件数の動的更新機能
        class SearchResultsUpdater {
            constructor() {
                this.updateTimeout = null;
                this.isUpdating = false;
                this.init();
            }
            
            init() {
                // フィルター変更イベントの監視
                this.observeFilterChanges();
                // 検索フォームの監視
                this.observeSearchForm();
            }
            
            // フィルター変更の監視
            observeFilterChanges() {
                // 都道府県選択の監視
                const prefectureSelects = document.querySelectorAll('select[name="prefectures[]"], select[name="prefectures"]');
                prefectureSelects.forEach(select => {
                    select.addEventListener('change', () => {
                        this.scheduleUpdate();
                    });
                });
                
                // 完成年選択の監視
                const yearSelects = document.querySelectorAll('select[name="completionYears[]"], select[name="completionYears"]');
                yearSelects.forEach(select => {
                    select.addEventListener('change', () => {
                        this.scheduleUpdate();
                    });
                });
                
                // 写真・動画チェックボックスの監視
                const photoCheckbox = document.querySelector('input[name="hasPhotos"]');
                if (photoCheckbox) {
                    photoCheckbox.addEventListener('change', () => {
                        this.scheduleUpdate();
                    });
                }
                
                const videoCheckbox = document.querySelector('input[name="hasVideos"]');
                if (videoCheckbox) {
                    videoCheckbox.addEventListener('change', () => {
                        this.scheduleUpdate();
                    });
                }
            }
            
            // 検索フォームの監視
            observeSearchForm() {
                const searchInput = document.querySelector('input[name="q"]');
                if (searchInput) {
                    searchInput.addEventListener('input', () => {
                        this.scheduleUpdate();
                    });
                }
            }
            
            // 更新のスケジュール（デバウンス）
            scheduleUpdate() {
                if (this.updateTimeout) {
                    clearTimeout(this.updateTimeout);
                }
                
                this.updateTimeout = setTimeout(() => {
                    this.updateResultsCount();
                }, 500); // 500ms後に実行
            }
            
            // 検索結果件数の更新
            async updateResultsCount() {
                if (this.isUpdating) return;
                
                this.isUpdating = true;
                this.showLoadingState();
                
                try {
                    // 現在の検索パラメータを取得
                    const searchParams = this.getCurrentSearchParams();
                    
                    // APIエンドポイントにリクエスト
                    const response = await fetch('/api/search-count.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(searchParams)
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        this.updateResultsDisplay(data.count);
                    } else {
                        console.error('Failed to update results count');
                    }
                } catch (error) {
                    console.error('Error updating results count:', error);
                } finally {
                    this.isUpdating = false;
                    this.hideLoadingState();
                }
            }
            
            // 現在の検索パラメータを取得
            getCurrentSearchParams() {
                const form = document.querySelector('form[method="get"]');
                if (!form) return {};
                
                const formData = new FormData(form);
                const params = {};
                
                for (let [key, value] of formData.entries()) {
                    if (params[key]) {
                        if (Array.isArray(params[key])) {
                            params[key].push(value);
                        } else {
                            params[key] = [params[key], value];
                        }
                    } else {
                        params[key] = value;
                    }
                }
                
                return params;
            }
            
            // 結果表示の更新
            updateResultsDisplay(count) {
                const countElements = document.querySelectorAll('.search-results-summary strong');
                countElements.forEach(element => {
                    element.textContent = count.toLocaleString() + '件';
                });
                
                // ページネーションの更新
                this.updatePagination(count);
            }
            
            // ページネーションの更新
            updatePagination(totalCount) {
                const pagination = document.querySelector('.pagination');
                if (!pagination) return;
                
                const itemsPerPage = 10; // デフォルトの1ページあたりの件数
                const totalPages = Math.ceil(totalCount / itemsPerPage);
                
                // ページネーション情報の更新
                const pageInfo = document.querySelector('.page-info');
                if (pageInfo) {
                    pageInfo.textContent = `ページ 1 / ${totalPages} (${totalCount.toLocaleString()} 件)`;
                }
            }
            
            // ローディング状態の表示
            showLoadingState() {
                const countElements = document.querySelectorAll('.search-results-summary strong');
                countElements.forEach(element => {
                    element.innerHTML = '<i class="spinner-border spinner-border-sm" role="status"></i>';
                });
            }
            
            // ローディング状態の非表示
            hideLoadingState() {
                // ローディング状態は updateResultsDisplay で上書きされる
            }
            
            // フォールバックメッセージの表示
            showFallbackMessage() {
                const countElements = document.querySelectorAll('.search-results-summary strong');
                countElements.forEach(element => {
                    element.textContent = '更新中...';
                });
                
                // 3秒後に元の値に戻す
                setTimeout(() => {
                    // ページを再読み込みして最新の件数を取得
                    window.location.reload();
                }, 3000);
            }
        }
        
        // Phase 3A: アニメーション効果の初期化
        function initializeAnimations() {
            // アニメーション無効化の確認
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            
            if (prefersReducedMotion) {
                // アニメーションを無効化
                document.documentElement.style.setProperty('--animation-duration', '0.01ms');
                return;
            }
            
            // 建築物カードの段階的表示アニメーション
            const buildingCards = document.querySelectorAll('.building-card');
            buildingCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100); // 100ms間隔で段階的に表示
            });
            
            // フィルターバッジのクリック効果
            const filterBadges = document.querySelectorAll('.filter-badge, .architect-badge, .building-type-badge, .prefecture-badge, .completion-year-badge');
            filterBadges.forEach(badge => {
                badge.addEventListener('click', function(e) {
                    // クリック時のリップル効果
                    const ripple = document.createElement('span');
                    ripple.style.position = 'absolute';
                    ripple.style.borderRadius = '50%';
                    ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                    ripple.style.transform = 'scale(0)';
                    ripple.style.animation = 'ripple 0.6s linear';
                    ripple.style.left = e.offsetX + 'px';
                    ripple.style.top = e.offsetY + 'px';
                    ripple.style.width = ripple.style.height = '20px';
                    ripple.style.pointerEvents = 'none';
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            // 検索結果件数のカウントアップアニメーション
            const resultCounts = document.querySelectorAll('.search-results-summary strong');
            resultCounts.forEach(element => {
                const finalCount = parseInt(element.textContent.replace(/[^\d]/g, ''));
                if (finalCount > 0) {
                    animateCountUp(element, finalCount);
                }
            });
            
            // ページネーションのホバー効果強化
            const pageLinks = document.querySelectorAll('.page-link');
            pageLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                });
                
                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        }

        // カウントアップアニメーション
        function animateCountUp(element, finalCount) {
            const duration = 1000; // 1秒
            const startTime = performance.now();
            
            function updateCount(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // イージング関数（ease-out）
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const currentCount = Math.floor(finalCount * easeOut);
                
                element.textContent = currentCount.toLocaleString() + '件';
                
                if (progress < 1) {
                    requestAnimationFrame(updateCount);
                }
            }
            
            requestAnimationFrame(updateCount);
        }

        // リップル効果のCSSアニメーション
        const rippleStyle = document.createElement('style');
        rippleStyle.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(rippleStyle);
        
        // Lucideアイコンの初期化とマップの初期化
        document.addEventListener("DOMContentLoaded", () => {
            lucide.createIcons();
            
            // 検索結果件数の動的更新機能を初期化
            new SearchResultsUpdater();
            
            // Phase 3A: アニメーション効果の初期化
            initializeAnimations();
            
            if (typeof L === 'undefined') {
                console.error('Leaflet library not loaded');
                return;
            }
            
            if (typeof initMap === 'function') {
                let center = [35.6762, 139.6503]; // デフォルト（東京）
                if (window.buildingsData && window.buildingsData.length > 0) {
                    const firstBuilding = window.buildingsData[0];
                    if (firstBuilding.lat && firstBuilding.lng) {
                        center = [parseFloat(firstBuilding.lat), parseFloat(firstBuilding.lng)];
                    }
                }
                
                initMap(center, window.buildingsData || []);
            } else {
                console.error('initMap function not found');
            }
        });
    </script>
</body>
</html>
