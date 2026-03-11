<!-- Sidebar -->
<div class="sticky-top sidebar-container" style="top: 20px;">
    <!-- Map -->
    <div class="card mb-4">
        <div class="card-body p-0">
            <div id="map" style="height: 400px; width: 100%;"></div>
        </div>
        
        <!-- Map Action Buttons (shown when building_slug is specified OR when on building list page) -->
        <?php if (($currentBuilding && $currentBuilding['lat'] && $currentBuilding['lng']) || (!$currentBuilding)): ?>
            <div class="card-footer p-3">
                <div class="d-grid gap-2">
                    <?php if ($currentBuilding && $currentBuilding['lat'] && $currentBuilding['lng']): ?>
                        <!-- 建築物詳細ページの場合：3つのボタンを表示 -->
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
                    <?php else: ?>
                        <!-- 建築物一覧ページの場合：Mapの中央地点で付近検索 -->
                        <button type="button" 
                                class="btn btn-outline-success btn-sm"
                                onclick="searchNearbyFromMapCenter()">
                            <i data-lucide="map-pinned" class="me-1" style="width: 16px; height: 16px;"></i>
                            <?php echo $lang === 'ja' ? '付近を検索' : 'Search Nearby'; ?>
                        </button>
                    <?php endif; ?>
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
                        <?php 
                        // Alt属性の生成（建築家名 + ウェブサイト名）
                        $altText = $architectInfo['name_ja'] ?? $architectInfo['name_en'] ?? '';
                        if (!empty($architectInfo['website_title'])) {
                            // HTMLエンティティをデコード（&amp; → &）
                            $decodedWebsiteTitle = html_entity_decode($architectInfo['website_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $altText .= ' - ' . $decodedWebsiteTitle;
                        }
                        ?>
                        <img src="/screen_shots_3_webp/shot_<?php echo $architectInfo['individual_architect_id'] ?? ''; ?>.webp" 
                             alt="<?php echo htmlspecialchars($altText); ?>"
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
                            <?php 
                            $displayTitle = $architectInfo['website_title'] ?? $architectInfo['name_ja'] ?? '';
                            // HTMLエンティティをデコード（&amp; → &）してからエスケープ
                            $decodedTitle = html_entity_decode($displayTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            echo htmlspecialchars($decodedTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            ?>
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
            <h6 class="mb-0 d-flex justify-content-between align-items-center">
                <span>
                    <i data-lucide="trending-up" class="me-2" style="width: 16px; height: 16px;"></i>
                    <?php echo t('popularSearches', $lang); ?>
                </span>
                <button type="button" 
                        class="btn btn-link btn-sm p-0 text-muted" 
                        data-bs-toggle="modal" 
                        data-bs-target="#popularSearchesModal"
                        onclick="loadPopularSearchesModal()"
                        title="<?php echo $lang === 'ja' ? 'もっと見る' : 'View More'; ?>"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top">
                    <i data-lucide="external-link" style="width: 16px; height: 16px;"></i>
                </button>
            </h6>
        </div>
        <div class="card-body">
            <!-- サイドバー用の人気検索データを取得 -->
            <div id="sidebar-popular-searches-container">
                <!-- ローディング表示 -->
                <div id="sidebar-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden"><?php echo $lang === 'ja' ? '読み込み中...' : 'Loading...'; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- もっと見るボタン -->
            <button class="btn btn-outline-primary btn-sm w-100 mt-2" 
                    data-bs-toggle="modal" 
                    data-bs-target="#popularSearchesModal"
                    onclick="loadPopularSearchesModal()">
                <i data-lucide="more-horizontal" class="me-1" style="width: 14px; height: 14px;"></i>
                <?php echo $lang === 'ja' ? 'もっと見る' : 'View More'; ?>
            </button>
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
                    <?php echo t('popularSearches', $lang); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- タブナビゲーション -->
                <ul class="nav nav-tabs mb-3" id="popularSearchesTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="architect-tab" data-bs-toggle="tab" data-bs-target="#architect-content" type="button" role="tab" aria-controls="architect-content" aria-selected="true">
                            <i data-lucide="circle-user-round" class="me-1" style="width: 16px; height: 16px;"></i>
                            <?php echo $lang === 'ja' ? '建築家' : 'Architect'; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="building-tab" data-bs-toggle="tab" data-bs-target="#building-content" type="button" role="tab" aria-controls="building-content" aria-selected="false">
                            <i data-lucide="building" class="me-1" style="width: 16px; height: 16px;"></i>
                            <?php echo $lang === 'ja' ? '建築物' : 'Building'; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="prefecture-tab" data-bs-toggle="tab" data-bs-target="#prefecture-content" type="button" role="tab" aria-controls="prefecture-content" aria-selected="false">
                            <i data-lucide="map-pin" class="me-1" style="width: 16px; height: 16px;"></i>
                            <?php echo $lang === 'ja' ? '都道府県' : 'Prefecture'; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="text-tab" data-bs-toggle="tab" data-bs-target="#text-content" type="button" role="tab" aria-controls="text-content" aria-selected="false">
                            <i data-lucide="type" class="me-1" style="width: 16px; height: 16px;"></i>
                            <?php echo $lang === 'ja' ? 'テキスト' : 'Text'; ?>
                        </button>
                    </li>
                </ul>
                
                <!-- 検索・フィルタエリア -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" id="searchQueryInput" 
                                   placeholder="<?php echo $lang === 'ja' ? '検索ワードで絞り込み...' : 'Filter by search term...'; ?>">
                            <button type="button" 
                                    class="input-group-text search-submit-btn" 
                                    id="popularSearchSubmitBtn"
                                    aria-label="<?php echo $lang === 'ja' ? '検索' : 'Search'; ?>"
                                    style="cursor: pointer; background-color: transparent; transition: background-color 0.2s;">
                                <i data-lucide="search" style="width: 20px; height: 20px; color: #6c757d;"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- タブコンテンツ -->
                <div class="tab-content" id="popularSearchesTabContent">
                    <!-- 建築家 -->
                    <div class="tab-pane fade show active" id="architect-content" role="tabpanel" aria-labelledby="architect-tab">
                        <!-- ローディング表示 -->
                        <div id="architect-loading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"><?php echo $lang === 'ja' ? '読み込み中...' : 'Loading...'; ?></span>
                            </div>
                        </div>
                        
                        <!-- 検索結果表示エリア -->
                        <div id="architect-content-area">
                            <!-- ここに検索結果が動的に読み込まれる -->
                        </div>
                    </div>
                    
                    <!-- 建築物 -->
                    <div class="tab-pane fade" id="building-content" role="tabpanel" aria-labelledby="building-tab">
                        <!-- ローディング表示 -->
                        <div id="building-loading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"><?php echo $lang === 'ja' ? '読み込み中...' : 'Loading...'; ?></span>
                            </div>
                        </div>
                        
                        <!-- 検索結果表示エリア -->
                        <div id="building-content-area">
                            <!-- ここに検索結果が動的に読み込まれる -->
                        </div>
                    </div>
                    
                    <!-- 都道府県 -->
                    <div class="tab-pane fade" id="prefecture-content" role="tabpanel" aria-labelledby="prefecture-tab">
                        <!-- ローディング表示 -->
                        <div id="prefecture-loading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"><?php echo $lang === 'ja' ? '読み込み中...' : 'Loading...'; ?></span>
                            </div>
                        </div>
                        
                        <!-- 検索結果表示エリア -->
                        <div id="prefecture-content-area">
                            <!-- ここに検索結果が動的に読み込まれる -->
                        </div>
                    </div>
                    
                    <!-- テキスト -->
                    <div class="tab-pane fade" id="text-content" role="tabpanel" aria-labelledby="text-tab">
                        <!-- ローディング表示 -->
                        <div id="text-loading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"><?php echo $lang === 'ja' ? '読み込み中...' : 'Loading...'; ?></span>
                            </div>
                        </div>
                        
                        <!-- 検索結果表示エリア -->
                        <div id="text-content-area">
                            <!-- ここに検索結果が動的に読み込まれる -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary w-100" data-bs-dismiss="modal">
                    <i data-lucide="x" class="me-1" style="width: 16px; height: 16px;"></i>
                    <?php echo $lang === 'ja' ? '閉じる' : 'Close'; ?>
                </button>
            </div>
        </div>
    </div>
</div>

