<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <?php echo SEOHelper::renderMetaTags($seoData); ?>
    
    <!-- Structured Data (JSON-LD) -->
    <?php echo SEOHelper::renderStructuredData($structuredData); ?>
    
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

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-9FY04VHM17"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-9FY04VHM17', {
    'debug_mode': true,
    'send_page_view': true
  });

  // Google Analytics デバッグ用
  console.log('Google Analytics initialized with ID: G-9FY04VHM17');
  console.log('DataLayer:', window.dataLayer);
  
  // ページビューイベントの確認
  gtag('event', 'page_view', {
    'page_title': document.title,
    'page_location': window.location.href
  });
  
  // カスタムイベントのテスト
  setTimeout(function() {
    gtag('event', 'test_event', {
      'event_category': 'debug',
      'event_label': 'analytics_test'
    });
    console.log('Test event sent to Google Analytics');
  }, 2000);
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
                
                <!-- Debug Information -->
                <?php if ($debugInfo): ?>
                    <div class="alert alert-warning">
                        <h6>デバッグ情報:</h6>
                        <ul>
                            <li>総建築物数: <?php echo $debugInfo['total_buildings'] ?? 'N/A'; ?></li>
                            <li>座標がある建築物数: <?php echo $debugInfo['buildings_with_coords'] ?? 'N/A'; ?></li>
                            <li>東京の建築物数: <?php echo $debugInfo['tokyo_buildings'] ?? 'N/A'; ?></li>
                            <li>検索テスト結果: <?php echo $debugInfo['search_test_result'] ?? 'N/A'; ?></li>
                        </ul>
                        <small>URLに?debug=1を追加するとこの情報が表示されます</small>
                    </div>
                <?php endif; ?>
                
                <!-- URL Debug Information -->
                <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                    <div class="alert alert-info">
                        <h6>URLデバッグ情報:</h6>
                        <ul>
                            <li>buildingSlug: "<?php echo htmlspecialchars($buildingSlug ?: 'empty'); ?>"</li>
                            <li>lang: "<?php echo htmlspecialchars($lang); ?>"</li>
                            <li>現在のURL: "<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>"</li>
                            <li>GET parameters: <?php echo htmlspecialchars(print_r($_GET, true)); ?></li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Search Debug Information -->
                <?php if (($query || $architectsSlug || $completionYears || $hasPhotos || $hasVideos) && isset($_GET['debug'])): ?>
                    <div class="alert alert-info">
                        <h6>検索デバッグ情報:</h6>
                        <ul>
                            <?php if ($query): ?>
                                <li>検索クエリ: "<?php echo htmlspecialchars($query); ?>"</li>
                            <?php endif; ?>
                            <?php if ($architectsSlug): ?>
                                <li>建築家スラッグ: "<?php echo htmlspecialchars($architectsSlug); ?>"</li>
                            <?php endif; ?>
                            <?php if ($completionYears): ?>
                                <li>建築年: "<?php echo htmlspecialchars($completionYears); ?>"</li>
                            <?php endif; ?>
                            <?php if ($hasPhotos): ?>
                                <li>写真フィルター: 有効</li>
                            <?php endif; ?>
                            <?php if ($hasVideos): ?>
                                <li>動画フィルター: 有効</li>
                            <?php endif; ?>
                            <li>検索結果数: <?php echo count($buildings); ?></li>
                            <li>総件数: <?php echo $totalBuildings; ?></li>
                            <li>現在のページ: <?php echo $page; ?></li>
                            <li>総ページ数: <?php echo $totalPages; ?></li>
                            <li>リミット: <?php echo $limit; ?></li>
                        </ul>
                        <p><strong>注意:</strong> エラーログを確認してください（通常は C:\xampp\apache\logs\error.log）</p>
                    </div>
                <?php endif; ?>
                
                <!-- Search Results Header -->
                <?php if ($hasPhotos || $hasVideos || $completionYears || $prefectures || $query || $architectsSlug): ?>
                    <div class="alert alert-light mb-3">
                        <h6 class="mb-2">
                            <i data-lucide="filter" class="me-2" style="width: 16px; height: 16px;"></i>
                            <?php echo $lang === 'ja' ? 'フィルター適用済み' : 'Filters Applied'; ?>
                        </h6>
                        <div class="d-flex gap-3 flex-wrap">
                            <?php if ($architectsSlug): ?>
                                <span class="architect-badge filter-badge">
                                    <i data-lucide="circle-user-round" class="me-1" style="width: 12px; height: 12px;"></i>
                                    <?php 
                                    // 建築家名を取得
                                    $architectName = '';
                                    if (isset($architectInfo) && $architectInfo) {
                                        if ($lang === 'ja') {
                                            // 日本語ページの場合：日本語名を優先、なければ英語名
                                            $architectName = !empty($architectInfo['name_ja']) ? 
                                                $architectInfo['name_ja'] : 
                                                ($architectInfo['name_en'] ?? '');
                                        } else {
                                            // 英語ページの場合：英語名を優先、なければ日本語名
                                            $architectName = !empty($architectInfo['name_en']) ? 
                                                $architectInfo['name_en'] : 
                                                ($architectInfo['name_ja'] ?? '');
                                        }
                                    }
                                    
                                    // 建築家名が取得できない場合は、スラッグから推測
                                    if (empty($architectName)) {
                                        // スラッグを人間が読みやすい形式に変換
                                        $architectName = str_replace('-', ' ', $architectsSlug);
                                        $architectName = ucwords($architectName);
                                        
                                        // 日本語ページの場合は、一般的な建築家名のパターンを適用
                                        if ($lang === 'ja') {
                                            // 英語の建築家名を日本語に変換する一般的なパターン
                                            $nameMappings = [
                                                'tadao ando architect associates' => '安藤忠雄建築研究所',
                                                'tadao ando' => '安藤忠雄',
                                                'takenaka corporation' => '竹中工務店',
                                                'nikken sekkei' => '日建設計',
                                                'kengo kuma' => '隈研吾',
                                                'kenzo tange' => '丹下健三',
                                                'fumihiko maki' => '槇文彦',
                                                'toyo ito' => '伊東豊雄',
                                                'kazuyo sejima' => '妹島和世',
                                                'ryue nishizawa' => '西沢立衛',
                                                'sanaa' => 'SANAA',
                                                'ggn' => 'GGN'
                                            ];
                                            
                                            $lowerSlug = strtolower($architectsSlug);
                                            if (isset($nameMappings[$lowerSlug])) {
                                                $architectName = $nameMappings[$lowerSlug];
                                            }
                                        }
                                    }
                                    
                                    // デバッグ情報（開発時のみ）
                                    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                                        echo "<!-- Debug: architectInfo = " . print_r($architectInfo, true) . " -->";
                                        echo "<!-- Debug: architectName = '" . $architectName . "' -->";
                                        echo "<!-- Debug: lang = '" . $lang . "' -->";
                                        echo "<!-- Debug: name_ja = '" . ($architectInfo['name_ja'] ?? 'not_set') . "' -->";
                                        echo "<!-- Debug: name_en = '" . ($architectInfo['name_en'] ?? 'not_set') . "' -->";
                                        echo "<!-- Debug: architectsSlug = '" . $architectsSlug . "' -->";
                                    }
                                    
                                    echo htmlspecialchars($architectName); 
                                    ?>
                                    <a href="/index_refactored_complete.php?<?php echo http_build_query(array_merge($_GET, ['architects_slug' => null])); ?>" 
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
                                    <a href="/index_refactored_complete.php?<?php echo http_build_query(array_merge($_GET, ['photos' => null])); ?>" 
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
                                    <a href="/index_refactored_complete.php?<?php echo http_build_query(array_merge($_GET, ['videos' => null])); ?>" 
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
                                    <a href="/index_refactored_complete.php?<?php echo http_build_query(array_merge($_GET, ['completionYears' => null])); ?>" 
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
                                    // 都道府県名の英語→日本語変換配列
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
                                    
                                    // 言語に応じて都道府県名を表示
                                    $displayPrefecture = $lang === 'ja' && isset($prefectureTranslations[$prefectures]) 
                                        ? $prefectureTranslations[$prefectures] 
                                        : $prefectures;
                                    echo htmlspecialchars($displayPrefecture); 
                                    ?>
                                    <a href="/index_refactored_complete.php?<?php echo http_build_query(array_merge($_GET, ['prefectures' => null])); ?>" 
                                       class="filter-remove-btn ms-2" 
                                       title="<?php echo $lang === 'ja' ? 'フィルターを解除' : 'Remove filter'; ?>">
                                        <i data-lucide="x" style="width: 12px; height: 12px;"></i>
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($query): ?>
                                <?php 
                                // キーワードを全角・半角スペースで分割
                                $keywords = preg_split('/[\s　]+/u', trim($query));
                                $keywords = array_filter($keywords, function($keyword) {
                                    return !empty(trim($keyword));
                                });
                                ?>
                                <?php foreach ($keywords as $index => $keyword): ?>
                                    <span class="architect-badge filter-badge">
                                        <i data-lucide="search" class="me-1" style="width: 12px; height: 12px;"></i>
                                        <?php echo htmlspecialchars($keyword); ?>
                                        <a href="/index_refactored_complete.php?<?php 
                                            // 現在のキーワードを除いた新しいクエリを作成
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
                    </div>
                <?php endif; ?>
                
                <!-- Debug Information for Media Filters -->
                <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                    <div class="alert alert-warning">
                        <h6>メディアフィルターデバッグ情報:</h6>
                        <ul>
                            <li>hasPhotos: <?php echo $hasPhotos ? 'true' : 'false'; ?></li>
                            <li>hasVideos: <?php echo $hasVideos ? 'true' : 'false'; ?></li>
                            <li>検索条件: <?php echo $query || $hasPhotos || $hasVideos ? 'メディアフィルター検索' : 'トップページ'; ?></li>
                            <li>検索結果数: <?php echo count($buildings); ?></li>
                            <li>総件数: <?php echo $totalBuildings; ?></li>
                            <li>現在のページ: <?php echo $currentPage; ?></li>
                            <li>総ページ数: <?php echo $totalPages; ?></li>
                        </ul>
                    </div>
                    
                    <!-- データベース状態確認 -->
                    <div class="alert alert-info">
                        <h6>データベース状態確認:</h6>
                        <?php
                        try {
                            $db = getDB();
                            $tables = ['buildings_table_3', 'building_architects', 'architect_compositions_2', 'individual_architects_3'];
                            echo '<ul>';
                            foreach ($tables as $table) {
                                $stmt = $db->prepare("SELECT COUNT(*) FROM $table");
                                $stmt->execute();
                                $count = $stmt->fetchColumn();
                                echo "<li>テーブル $table: $count レコード</li>";
                            }
                            
                            // 座標がある建築物数を確認
                            $coordSql = "SELECT COUNT(*) FROM buildings_table_3 WHERE lat IS NOT NULL AND lng IS NOT NULL";
                            $coordStmt = $db->prepare($coordSql);
                            $coordStmt->execute();
                            $buildingsWithCoords = $coordStmt->fetchColumn();
                            echo "<li>座標がある建築物: $buildingsWithCoords 件</li>";
                            
                            // locationがある建築物数を確認
                            $locationSql = "SELECT COUNT(*) FROM buildings_table_3 WHERE location IS NOT NULL AND location != ''";
                            $locationStmt = $db->prepare($locationSql);
                            $locationStmt->execute();
                            $buildingsWithLocation = $locationStmt->fetchColumn();
                            echo "<li>locationがある建築物: $buildingsWithLocation 件</li>";
                            
                            // 両方の条件を満たす建築物数を確認
                            $bothSql = "SELECT COUNT(*) FROM buildings_table_3 WHERE lat IS NOT NULL AND lng IS NOT NULL AND location IS NOT NULL AND location != ''";
                            $bothStmt = $db->prepare($bothSql);
                            $bothStmt->execute();
                            $buildingsWithBoth = $bothStmt->fetchColumn();
                            echo "<li>座標とlocation両方がある建築物: $buildingsWithBoth 件</li>";
                            
                            echo '</ul>';
                            
                        } catch (Exception $e) {
                            echo '<p style="color: red;">データベース接続エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
                        }
                        ?>
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
                                // 通し番号を計算
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
        
        // Lucideアイコンの初期化とマップの初期化
        document.addEventListener("DOMContentLoaded", () => {
            
            lucide.createIcons();
            
            // Leafletライブラリの読み込み確認
            if (typeof L === 'undefined') {
                console.error('Leaflet library not loaded');
                return;
            }
            
            // マップの初期化
            if (typeof initMap === 'function') {
                // 建築物がある場合は最初の建築物の位置を中心に、ない場合は東京駅を中心に
                let center = [35.6814, 139.7670]; // デフォルト（東京駅）
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
