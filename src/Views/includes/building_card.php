<?php 
// デバッグ情報は非表示（必要に応じてコメントアウトを解除）
// if (isset($_GET['debug']) && $_GET['debug'] === '1') {
//     echo "<!-- Building data debug: " . print_r($building, true) . " -->";
//     echo "<!-- titleEn debug: " . ($building['titleEn'] ?? 'NULL') . " -->";
// }

// 設定ファイルの読み込み
if (!class_exists('ConfigManager')) {
    require_once __DIR__ . '/../../Utils/ConfigManager.php';
}
// いいね表示の設定を取得（デフォルトはtrue）
$showLikes = ConfigManager::get('display.show_likes', true);
?>
<!-- Building Card - Horizontal Layout -->
<div class="card mb-3 building-card" 
     data-building-id="<?php echo htmlspecialchars($building['building_id'] ?? ''); ?>"
     data-uid="<?php echo htmlspecialchars($building['uid'] ?? ''); ?>"
     data-lat="<?php echo htmlspecialchars($building['lat'] ?? ''); ?>"
     data-lng="<?php echo htmlspecialchars($building['lng'] ?? ''); ?>"
     data-title="<?php echo htmlspecialchars($building['title'] ?? ''); ?>"
     data-title-en="<?php echo htmlspecialchars($building['titleEn'] ?? ''); ?>"
     data-location="<?php echo htmlspecialchars($building['location'] ?? ''); ?>"
     data-location-en="<?php echo htmlspecialchars($building['locationEn'] ?? ''); ?>"
     data-slug="<?php echo htmlspecialchars($building['slug'] ?? ''); ?>"
     data-youtube-url="<?php echo htmlspecialchars($building['youtubeUrl'] ?? ''); ?>"
     data-popup-content="<?php echo htmlspecialchars(generatePopupContent($building, $lang ?? 'ja')); ?>">
    <div class="row g-0">
        <!-- Image Column -->
        <div class="col-md-3">
            <?php if (!empty($building['thumbnailUrl'])): ?>
                <?php 
                // Alt属性の生成（建築物名 + 建築家名 + 場所）
                $altText = $building['title'];
                if (!empty($building['architects']) && count($building['architects']) > 0) {
                    $architectNames = array_map(function($architect) use ($lang) {
                        return $lang === 'ja' ? $architect['architectJa'] : $architect['architectEn'];
                    }, $building['architects']);
                    $altText .= ' - ' . implode(', ', $architectNames);
                }
                if (!empty($building['location'])) {
                    $altText .= ' - ' . ($lang === 'ja' ? $building['location'] : $building['locationEn']);
                }
                ?>
                <img src="<?php echo htmlspecialchars($building['thumbnailUrl']); ?>" 
                     class="img-fluid rounded-start h-100" 
                     alt="<?php echo htmlspecialchars($altText); ?>"
                     style="height: 150px; object-fit: cover; width: 100%;">
            <?php else: ?>
                <div class="bg-light d-flex align-items-center justify-content-center rounded-start" 
                     style="height: 150px;">
                    <img src="/assets/images/landmark.svg" 
                         alt="<?php echo $lang === 'ja' ? '建築物画像なし' : 'No building image'; ?>" 
                         style="width: 60px; height: 60px; opacity: 0.3;">
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Content Column -->
        <div class="col-md-9">
    
            <div class="card-body d-flex flex-column h-100">
                <div class="d-flex align-items-center mb-2">
                    <div class="search-number-badge me-2">
                        <?php echo isset($globalIndex) ? $globalIndex : ($index + 1); ?>
                    </div>
                    <h5 class="card-title mb-0 flex-grow-1">
                        <a href="/buildings/<?php echo urlencode($building['slug']); ?>?lang=<?php echo $lang; ?>" 
                           class="text-decoration-none text-dark">
                            <?php echo htmlspecialchars($lang === 'ja' ? $building['title'] : $building['titleEn']); ?>
                        </a>
                    </h5>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <?php if (!empty($building['architects'])): ?>
                            <div class="card-text mb-2">
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($building['architects'] as $architect): ?>
                                        <?php 
                                        // 現在の検索条件を保持したURLパラメータを作成
                                        $urlParams = ['architects_slug' => $architect['slug'], 'lang' => $lang];
                                        if (isset($_GET['q']) && $_GET['q']) {
                                            $urlParams['q'] = $_GET['q'];
                                        }
                                        if (isset($_GET['prefectures']) && $_GET['prefectures']) {
                                            $urlParams['prefectures'] = $_GET['prefectures'];
                                        }
                                        if (isset($_GET['completionYears']) && $_GET['completionYears']) {
                                            $urlParams['completionYears'] = $_GET['completionYears'];
                                        }
                                        if (isset($_GET['photos']) && $_GET['photos']) {
                                            $urlParams['photos'] = $_GET['photos'];
                                        }
                                        if (isset($_GET['videos']) && $_GET['videos']) {
                                            $urlParams['videos'] = $_GET['videos'];
                                        }
                                        ?>
                                        <a href="/architects/<?php echo urlencode($architect['slug']); ?>/?lang=<?php echo $lang; ?>" 
                                           class="architect-badge text-decoration-none">
                                            <i data-lucide="circle-user-round" class="me-1" style="width: 12px; height: 12px;"></i>
                                            <?php echo htmlspecialchars($lang === 'ja' ? $architect['architectJa'] : $architect['architectEn']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($building['location']): ?>
                            <p class="card-text mb-1">
                                <small class="text-muted">
                                    <i data-lucide="map-pin" class="me-1" style="width: 12px; height: 12px;"></i>
                                    <?php echo htmlspecialchars($lang === 'ja' ? $building['location'] : $building['locationEn']); ?>
                                    <?php if (isset($building['distance'])): ?>
                                        <span class="ms-2"><i data-lucide="route" class="me-1" style="width: 12px; height: 12px;"></i><?php echo $building['distance']; ?>km</span>
                                    <?php endif; ?>
                                </small>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4 text-end">
                        
                        <!-- 右端のバッジは削除（右下のボタンと重複のため） -->
                    </div>
                </div>
                
                <?php 
                $buildingTypesRaw = $lang === 'ja' ? $building['buildingTypes'] : $building['buildingTypesEn'];
                if (is_array($buildingTypesRaw)) {
                    $buildingTypes = $buildingTypesRaw;
                } else {
                    $buildingTypes = !empty($buildingTypesRaw) ? explode(',', $buildingTypesRaw) : [];
                }
                // 各要素の前後のスペースを削除
                $buildingTypes = array_map('trim', $buildingTypes);
                // 空の要素を削除
                $buildingTypes = array_filter($buildingTypes, function($type) {
                    return !empty(trim($type));
                });
                if (!empty($buildingTypes)): 
                ?>
                    <div class="mt-2">
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($buildingTypes as $type): ?>
                                <?php 
                                // 現在のページが建築家ページかどうかを判定
                                $isArchitectPage = isset($_GET['architects_slug']) && !empty($_GET['architects_slug']);
                                
                                // リライトルールが動作しない場合の代替判定
                                if (!$isArchitectPage) {
                                    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
                                    $isArchitectPage = preg_match('/^\/architects\/[^\/]+\/?/', $requestUri);
                                    if ($isArchitectPage) {
                                        // URLから建築家スラッグを抽出
                                        preg_match('/^\/architects\/([^\/]+)\/?/', $requestUri, $matches);
                                        $architectSlug = $matches[1] ?? '';
                                    }
                                }
                                
                                if ($isArchitectPage) {
                                    // 建築家ページの場合：既存のパラメータを保持してqパラメータを追加
                                    if (isset($_GET['architects_slug']) && !empty($_GET['architects_slug'])) {
                                        $architectSlug = $_GET['architects_slug'];
                                    }
                                    $urlParams = ['q' => $type, 'lang' => $lang];
                                    
                                    // 既存のパラメータを保持
                                    if (isset($_GET['completionYears']) && !empty($_GET['completionYears'])) {
                                        $urlParams['completionYears'] = $_GET['completionYears'];
                                    }
                                    if (isset($_GET['prefectures']) && !empty($_GET['prefectures'])) {
                                        $urlParams['prefectures'] = $_GET['prefectures'];
                                    }
                                    if (isset($_GET['photos']) && !empty($_GET['photos'])) {
                                        $urlParams['photos'] = $_GET['photos'];
                                    }
                                    if (isset($_GET['videos']) && !empty($_GET['videos'])) {
                                        $urlParams['videos'] = $_GET['videos'];
                                    }
                                    
                                    $url = "/architects/{$architectSlug}/?" . http_build_query($urlParams);
                                } else {
                                    // 通常ページの場合：既存のロジックを使用
                                    $urlParams = ['q' => $type, 'lang' => $lang];
                                    if (isset($_GET['prefectures']) && $_GET['prefectures']) {
                                        $urlParams['prefectures'] = $_GET['prefectures'];
                                    }
                                    if (isset($_GET['completionYears']) && $_GET['completionYears']) {
                                        $urlParams['completionYears'] = $_GET['completionYears'];
                                    }
                                    if (isset($_GET['photos']) && $_GET['photos']) {
                                        $urlParams['photos'] = $_GET['photos'];
                                    }
                                    if (isset($_GET['videos']) && $_GET['videos']) {
                                        $urlParams['videos'] = $_GET['videos'];
                                    }
                                    $url = "/index.php?" . http_build_query($urlParams);
                                }
                                ?>
                                <a href="<?php echo $url; ?>" 
                                   class="building-type-badge text-decoration-none"
                                   title="<?php echo $lang === 'ja' ? 'この用途で検索' : 'Search by this building type'; ?>">
                                    <i data-lucide="building" class="me-1" style="width: 12px; height: 12px;"></i>
                                    <?php echo htmlspecialchars($type); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($building['prefectures']) || $building['completionYears']): ?>
                    <div class="mt-2">
                        <div class="d-flex flex-wrap gap-1">
                            <?php if (!empty($building['prefectures'])): ?>
                                <?php 
                                // 現在のページが建築家ページかどうかを判定
                                $isArchitectPage = isset($_GET['architects_slug']) && !empty($_GET['architects_slug']);
                                
                                // リライトルールが動作しない場合の代替判定
                                if (!$isArchitectPage) {
                                    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
                                    $isArchitectPage = preg_match('/^\/architects\/[^\/]+\/?/', $requestUri);
                                    if ($isArchitectPage) {
                                        // URLから建築家スラッグを抽出
                                        preg_match('/^\/architects\/([^\/]+)\/?/', $requestUri, $matches);
                                        $architectSlug = $matches[1] ?? '';
                                    }
                                }
                                
                                if ($isArchitectPage) {
                                    // 建築家ページの場合：既存のパラメータを保持してprefecturesパラメータを追加
                                    if (isset($_GET['architects_slug']) && !empty($_GET['architects_slug'])) {
                                        $architectSlug = $_GET['architects_slug'];
                                    }
                                    $urlParams = ['prefectures' => $building['prefecturesEn'], 'lang' => $lang];
                                    
                                    // 既存のパラメータを保持
                                    if (isset($_GET['completionYears']) && !empty($_GET['completionYears'])) {
                                        $urlParams['completionYears'] = $_GET['completionYears'];
                                    }
                                    if (isset($_GET['photos']) && !empty($_GET['photos'])) {
                                        $urlParams['photos'] = $_GET['photos'];
                                    }
                                    if (isset($_GET['videos']) && !empty($_GET['videos'])) {
                                        $urlParams['videos'] = $_GET['videos'];
                                    }
                                    if (isset($_GET['q']) && !empty($_GET['q'])) {
                                        $urlParams['q'] = $_GET['q'];
                                    }
                                    
                                    $url = "/architects/{$architectSlug}/?" . http_build_query($urlParams);
                                    
                                } else {
                                    // 通常ページの場合：既存のロジックを使用
                                    $urlParams = ['prefectures' => $building['prefecturesEn'], 'lang' => $lang];
                                    if (isset($_GET['q']) && $_GET['q']) {
                                        $urlParams['q'] = $_GET['q'];
                                    }
                                    if (isset($_GET['completionYears']) && $_GET['completionYears']) {
                                        $urlParams['completionYears'] = $_GET['completionYears'];
                                    }
                                    if (isset($_GET['photos']) && $_GET['photos']) {
                                        $urlParams['photos'] = $_GET['photos'];
                                    }
                                    if (isset($_GET['videos']) && $_GET['videos']) {
                                        $urlParams['videos'] = $_GET['videos'];
                                    }
                                    $url = "/index.php?" . http_build_query($urlParams);
                                    
                                }
                                ?>
                                <a href="<?php echo $url; ?>" 
                                   class="prefecture-badge text-decoration-none">
                                    <i data-lucide="map-pin" class="me-1" style="width: 12px; height: 12px;"></i>
                                    <?php echo htmlspecialchars($lang === 'ja' ? $building['prefectures'] : $building['prefecturesEn']); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($building['completionYears']): ?>
                                <?php 
                                // 現在のページが建築家ページかどうかを判定
                                $isArchitectPage = isset($_GET['architects_slug']) && !empty($_GET['architects_slug']);
                                
                                // リライトルールが動作しない場合の代替判定
                                if (!$isArchitectPage) {
                                    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
                                    $isArchitectPage = preg_match('/^\/architects\/[^\/]+\/?/', $requestUri);
                                    if ($isArchitectPage) {
                                        // URLから建築家スラッグを抽出
                                        preg_match('/^\/architects\/([^\/]+)\/?/', $requestUri, $matches);
                                        $architectSlug = $matches[1] ?? '';
                                    }
                                }
                                
                                if ($isArchitectPage) {
                                    // 建築家ページの場合：既存のパラメータを保持してcompletionYearsパラメータを追加
                                    if (isset($_GET['architects_slug']) && !empty($_GET['architects_slug'])) {
                                        $architectSlug = $_GET['architects_slug'];
                                    }
                                    $urlParams = ['completionYears' => $building['completionYears'], 'lang' => $lang];
                                    
                                    // 既存のパラメータを保持
                                    if (isset($_GET['prefectures']) && !empty($_GET['prefectures'])) {
                                        $urlParams['prefectures'] = $_GET['prefectures'];
                                    }
                                    if (isset($_GET['photos']) && !empty($_GET['photos'])) {
                                        $urlParams['photos'] = $_GET['photos'];
                                    }
                                    if (isset($_GET['videos']) && !empty($_GET['videos'])) {
                                        $urlParams['videos'] = $_GET['videos'];
                                    }
                                    if (isset($_GET['q']) && !empty($_GET['q'])) {
                                        $urlParams['q'] = $_GET['q'];
                                    }
                                    
                                    $url = "/architects/{$architectSlug}/?" . http_build_query($urlParams);
                                } else {
                                    // 通常ページの場合：既存のロジックを使用
                                    $urlParams = ['completionYears' => $building['completionYears'], 'lang' => $lang];
                                    if (isset($_GET['q']) && $_GET['q']) {
                                        $urlParams['q'] = $_GET['q'];
                                    }
                                    if (isset($_GET['prefectures']) && $_GET['prefectures']) {
                                        $urlParams['prefectures'] = $_GET['prefectures'];
                                    }
                                    if (isset($_GET['photos']) && $_GET['photos']) {
                                        $urlParams['photos'] = $_GET['photos'];
                                    }
                                    if (isset($_GET['videos']) && $_GET['videos']) {
                                        $urlParams['videos'] = $_GET['videos'];
                                    }
                                    $url = "/index.php?" . http_build_query($urlParams);
                                }
                                ?>
                                <a href="<?php echo $url; ?>" 
                                   class="completion-year-badge text-decoration-none"
                                   title="<?php echo $lang === 'ja' ? 'この建築年で検索' : 'Search by this completion year'; ?>">
                                    <i data-lucide="calendar" class="me-1" style="width: 12px; height: 12px;"></i>
                                    <?php echo $building['completionYears']; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
    <div class="card-footer bg-transparent">
        <div class="d-flex justify-content-between align-items-center">
            <?php if ($showLikes): ?>
            <small class="text-muted">
                <i data-lucide="heart" class="me-1" style="width: 14px; height: 14px;"></i>
                <?php echo $lang === 'ja' ? 'いいね' : 'Likes'; ?>: <?php echo $building['likes']; ?>
            </small>
            <?php else: ?>
            <small class="text-muted"></small>
            <?php endif; ?>
            
            <div class="btn-group btn-group-sm">
                <?php if (!empty($building['uid']) && !empty($building['has_photo']) && $building['has_photo'] !== '0' && $building['has_photo'] !== null): ?>
                    <button type="button" 
                            class="btn btn-outline-success btn-sm"
                            onclick="openPhoto('<?php echo htmlspecialchars($building['uid']); ?>')"
                            title="<?php echo $lang === 'ja' ? '写真を見る' : 'View Photos'; ?>">
                        <i data-lucide="image" style="width: 16px; height: 16px;"></i>
                    </button>
                <?php endif; ?>
                
                <?php if ($building['youtubeUrl']): ?>
                    <button type="button" 
                            class="btn btn-outline-danger btn-sm"
                            onclick="openVideo('<?php echo htmlspecialchars($building['youtubeUrl']); ?>')"
                            title="<?php echo $lang === 'ja' ? '動画を見る' : 'View Video'; ?>">
                        <i data-lucide="youtube" style="width: 16px; height: 16px;"></i>
                    </button>
                <?php endif; ?>
                
                <button type="button" 
                        class="btn btn-outline-primary btn-sm"
                        onclick="showOnMap(<?php echo $building['lat']; ?>, <?php echo $building['lng']; ?>)"
                        title="<?php echo $lang === 'ja' ? '地図を見る' : 'View Map'; ?>">
                    <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- 建築物コラムセクション（建築物詳細ページでのみ表示） -->
    <?php if (isset($buildingSlug) && !empty($buildingSlug) && !empty($building['building_column_text'])): ?>
        <?php include 'src/Views/includes/building_column_card.php'; ?>
    <?php endif; ?>
</div>

<!-- 写真ギャラリーカード（建築物詳細ページでのみ表示） -->
<?php if (isset($buildingSlug) && !empty($buildingSlug) && !empty($building['has_photo']) && $building['has_photo'] != '0' && !empty($building['uid'])): ?>
    <?php include 'src/Views/includes/photo_gallery_card.php'; ?>
<?php endif; ?>

<!-- 関連サイトカード（建築物詳細ページでのみ表示） -->
<?php if (isset($buildingSlug) && !empty($buildingSlug)): ?>
    <?php include 'src/Views/includes/related_sites_card.php'; ?>
<?php endif; ?>

