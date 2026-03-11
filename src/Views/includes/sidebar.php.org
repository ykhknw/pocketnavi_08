<!-- Sidebar -->
<div class="sticky-top" style="top: 20px;">
    <!-- Map -->
    <div class="card mb-4">
        <div class="card-body p-0">
            <div id="map" style="height: 400px; width: 100%;"></div>
        </div>
        
        <!-- Map Action Buttons (only shown when building_slug is specified) -->
        <?php if ($currentBuilding && $currentBuilding['lat'] && $currentBuilding['lng']): ?>
            <div class="card-footer p-3">
                <div class="d-grid gap-2">
                    <!-- 付近を検索 -->
                    <button type="button" 
                            class="btn btn-outline-success btn-sm"
                            onclick="searchNearby(<?php echo $currentBuilding['lat']; ?>, <?php echo $currentBuilding['lng']; ?>)">
                        <i data-lucide="map-pinned" class="me-1" style="width: 16px; height: 16px;"></i>
                        <?php echo $lang === 'ja' ? '付近を検索' : 'Search Nearby'; ?>
                    </button>
                    
                    <!-- 経路を検索 -->
                    <button type="button" 
                            class="btn btn-outline-warning btn-sm"
                            onclick="getDirections(<?php echo $currentBuilding['lat']; ?>, <?php echo $currentBuilding['lng']; ?>)">
                        <i data-lucide="route" class="me-1" style="width: 16px; height: 16px;"></i>
                        <?php echo $lang === 'ja' ? '経路を検索' : 'Get Directions'; ?>
                    </button>
                    
                    <!-- グーグルマップで見る -->
                    <button type="button" 
                            class="btn btn-outline-info btn-sm"
                            onclick="viewOnGoogleMaps(<?php echo $currentBuilding['lat']; ?>, <?php echo $currentBuilding['lng']; ?>)">
                        <i data-lucide="external-link" class="me-1" style="width: 16px; height: 16px;"></i>
                        <?php echo $lang === 'ja' ? 'グーグルマップで見る' : 'View on Google Maps'; ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Architect Related Site (only shown when architects_slug is specified) -->
    <?php if (isset($architectInfo) && $architectInfo && !empty($architectInfo['individual_website'])): ?>
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header">
                <h6 class="mb-0">
                    <i data-lucide="square-mouse-pointer" class="me-2" style="width: 16px; height: 16px;"></i>
                    <?php echo $lang === 'ja' ? '関連サイト' : 'Useful Links'; ?>
                </h6>
            </div>
            <div class="card-body p-0">
                <a href="<?php echo htmlspecialchars($architectInfo['individual_website']); ?>" 
                   target="_blank" 
                   class="text-decoration-none d-block position-relative overflow-hidden"
                   style="transition: all 0.3s ease;"
                   onmouseover="this.style.transform='scale(1.02)'"
                   onmouseout="this.style.transform='scale(1)'">
                    
                    <!-- スクリーンショット画像 -->
                    <div class="position-relative">
                        <img src="https://kenchikuka.com/screen_shots_3_webp/shot_<?php echo $architectInfo['individual_architect_id'] ?? ''; ?>.webp" 
                             alt="<?php echo htmlspecialchars($architectInfo['website_title'] ?? $architectInfo['name_ja'] ?? ''); ?>"
                             class="img-fluid w-100"
                             style="height: 200px; object-fit: cover; transition: all 0.3s ease;"
                             onerror="this.style.display='none'">
                        
                        <!-- オーバーレイ効果 -->
                        <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                             style="background: rgba(0,0,0,0.3); opacity: 0; transition: opacity 0.3s ease;">
                            <div class="text-center text-white">
                                <i data-lucide="external-link" style="width: 32px; height: 32px; margin-bottom: 8px;"></i>
                                <div class="fw-bold"><?php echo $lang === 'ja' ? 'サイトを見る' : 'Visit Site'; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- タイトルとURL -->
                    <div class="p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                        <h5 class="mb-2 text-dark fw-bold" style="font-size: 1.1rem; line-height: 1.3;">
                            <?php echo htmlspecialchars($architectInfo['website_title'] ?? $architectInfo['name_ja'] ?? ''); ?>
                        </h5>
                        <div class="d-flex align-items-center">
                            <i data-lucide="globe" class="me-2 text-primary" style="width: 14px; height: 14px;"></i>
                            <small class="text-muted text-truncate">
                                <?php echo htmlspecialchars($architectInfo['individual_website'] ?? ''); ?>
                            </small>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        
        <style>
            .card:hover {
                box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
            }
            .card a:hover .position-absolute {
                opacity: 1 !important;
            }
            .card a:hover img {
                transform: scale(1.05);
            }
            
            /* 人気検索の文字列表示制御 */
            .list-group-item .text-truncate {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                word-break: break-all;
            }
            
            /* 長い文字列の場合の改行制御 */
            .list-group-item .text-truncate:hover {
                white-space: normal;
                word-wrap: break-word;
                max-height: none;
            }
            
            /* バッジの固定幅確保 */
            .list-group-item .badge {
                min-width: 2rem;
                text-align: center;
            }
        </style>
    <?php endif; ?>
    
    <!-- Popular Searches -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">
                <i data-lucide="trending-up" class="me-2" style="width: 16px; height: 16px;"></i>
                <?php echo t('popularSearches', $lang); ?>
            </h6>
        </div>
        <div class="card-body">
            <?php if (!empty($popularSearches)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($popularSearches as $search): ?>
                        <?php 
                        // リンクを直接生成（一時的な修正）
                        if (isset($search['search_type']) && $search['search_type'] === 'prefecture') {
                            // 都道府県検索の場合
                            $prefectureEn = '';
                            if (isset($search['filters'])) {
                                $filters = json_decode($search['filters'], true);
                                $prefectureEn = $filters['prefecture_en'] ?? '';
                            }
                            
                            if ($prefectureEn) {
                                $link = '/index.php?prefectures=' . urlencode($prefectureEn);
                            } else {
                                // 日本語名から英語名に変換
                                $prefectureTranslations = [
                                    '東京都' => 'Tokyo', '京都府' => 'Kyoto', '大阪府' => 'Osaka',
                                    '愛知県' => 'Aichi', '神奈川県' => 'Kanagawa', '埼玉県' => 'Saitama',
                                    '千葉県' => 'Chiba', '兵庫県' => 'Hyogo', '福岡県' => 'Fukuoka',
                                    '北海道' => 'Hokkaido', '青森県' => 'Aomori', '岩手県' => 'Iwate',
                                    '宮城県' => 'Miyagi', '秋田県' => 'Akita', '山形県' => 'Yamagata',
                                    '福島県' => 'Fukushima', '茨城県' => 'Ibaraki', '栃木県' => 'Tochigi',
                                    '群馬県' => 'Gunma', '新潟県' => 'Niigata', '富山県' => 'Toyama',
                                    '石川県' => 'Ishikawa', '福井県' => 'Fukui', '山梨県' => 'Yamanashi',
                                    '長野県' => 'Nagano', '岐阜県' => 'Gifu', '静岡県' => 'Shizuoka',
                                    '三重県' => 'Mie', '滋賀県' => 'Shiga', '奈良県' => 'Nara',
                                    '和歌山県' => 'Wakayama', '鳥取県' => 'Tottori', '島根県' => 'Shimane',
                                    '岡山県' => 'Okayama', '広島県' => 'Hiroshima', '山口県' => 'Yamaguchi',
                                    '徳島県' => 'Tokushima', '香川県' => 'Kagawa', '愛媛県' => 'Ehime',
                                    '高知県' => 'Kochi', '佐賀県' => 'Saga', '長崎県' => 'Nagasaki',
                                    '熊本県' => 'Kumamoto', '大分県' => 'Oita', '宮崎県' => 'Miyazaki',
                                    '鹿児島県' => 'Kagoshima', '沖縄県' => 'Okinawa'
                                ];
                                $prefectureEn = $prefectureTranslations[$search['query']] ?? $search['query'];
                                $link = '/index.php?prefectures=' . urlencode($prefectureEn);
                            }
                        } elseif (isset($search['search_type']) && $search['search_type'] === 'architect') {
                            // 建築家検索の場合
                            $architectSlug = '';
                            if (isset($search['filters'])) {
                                $filters = json_decode($search['filters'], true);
                                $architectSlug = $filters['architect_slug'] ?? $filters['identifier'] ?? '';
                            }
                            
                            if ($architectSlug) {
                                $link = '/architects/' . $architectSlug . '/';
                            } else {
                                // クエリから直接検索（一時的な処理）
                                $link = '/index.php?q=' . urlencode($search['query']) . '&type=architect';
                            }
                        } else {
                            $link = $search['link'] ?? '/index.php?q=' . urlencode($search['query']);
                        }
                        
                        $separator = (strpos($link, '?') !== false) ? '&' : '?';
                        $finalLink = $link . $separator . 'lang=' . $lang;
                        
                        // デバッグ用
                        error_log("Sidebar debug - query: " . $search['query'] . ", search_type: " . ($search['search_type'] ?? 'null') . ", link: " . $link . ", finalLink: " . $finalLink);
                        
                        // 一時的なデバッグ表示（本番では削除）
                        if ($search['query'] === '愛知県' || $search['query'] === '竹中工務店') {
                            echo "<!-- DEBUG: " . $search['query'] . " - search_type: " . ($search['search_type'] ?? 'null') . ", page_type: " . ($search['page_type'] ?? 'null') . ", link: " . $link . " -->";
                            echo "<!-- DEBUG: Full search array: " . json_encode($search) . " -->";
                        }
                        ?>
                        <a href="<?php echo htmlspecialchars($finalLink); ?>" 
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                           title="<?php echo htmlspecialchars($search['query']); ?>">
                            <div class="d-flex flex-column flex-grow-1" style="min-width: 0;">
                                <span class="text-truncate" style="max-width: 100%;" title="<?php echo htmlspecialchars($search['query']); ?>">
                                    <?php echo htmlspecialchars($search['query']); ?>
                                </span>
                                <?php if (isset($search['page_type']) && $search['page_type']): ?>
                                    <small class="text-muted text-truncate" style="max-width: 100%;">
                                        <?php 
                                        $pageTypeLabels = [
                                            'architect' => $lang === 'ja' ? '建築家' : 'Architect',
                                            'building' => $lang === 'ja' ? '建築物' : 'Building',
                                            'prefecture' => $lang === 'ja' ? '都道府県' : 'Prefecture'
                                        ];
                                        echo $pageTypeLabels[$search['page_type']] ?? '';
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-primary rounded-pill flex-shrink-0 ms-2"><?php echo $search['count']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- もっと見るボタン -->
                <button class="btn btn-outline-primary btn-sm w-100 mt-2" 
                        data-bs-toggle="modal" 
                        data-bs-target="#popularSearchesModal"
                        onclick="loadPopularSearchesModal()">
                    <i data-lucide="more-horizontal" class="me-1" style="width: 14px; height: 14px;"></i>
                    <?php echo $lang === 'ja' ? 'もっと見る' : 'View More'; ?>
                </button>
            <?php else: ?>
                <p class="text-muted mb-0">
                    <?php echo $lang === 'ja' ? '人気の検索がありません。' : 'No popular searches available.'; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 人気検索ワードモーダル -->
<div class="modal fade" id="popularSearchesModal" tabindex="-1" aria-labelledby="popularSearchesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="popularSearchesModalLabel">
                    <i data-lucide="trending-up" class="me-2" style="width: 20px; height: 20px;"></i>
                    人気の検索
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- 検索・フィルタエリア -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i data-lucide="search" style="width: 16px; height: 16px;"></i>
                            </span>
                            <input type="text" class="form-control" id="searchQueryInput" 
                                   placeholder="<?php echo $lang === 'ja' ? '検索ワードで絞り込み...' : 'Filter by search term...'; ?>">
                        </div>
                    </div>
                </div>
                
                <!-- ローディング表示 -->
                <div id="popularSearchesLoading" class="text-center py-4" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden"><?php echo $lang === 'ja' ? '読み込み中...' : 'Loading...'; ?></span>
                    </div>
                </div>
                
                <!-- 検索結果表示エリア -->
                <div id="popularSearchesContent">
                    <!-- ここに検索結果が動的に読み込まれる -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php echo $lang === 'ja' ? '閉じる' : 'Close'; ?>
                </button>
            </div>
        </div>
    </div>
</div>

