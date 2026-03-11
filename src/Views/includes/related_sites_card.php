<?php
/**
 * 関連サイトカードコンポーネント
 * 建築物詳細ページで、その建築物を設計した建築家のウェブサイトを表示する
 * カルーセル形式で表示（複数の場合はスライド可能）
 */

// 関連サイトを持つ建築家を抽出
// 条件: individual_websiteがある、またはスクリーンショット画像が存在する
$architectsWithWebsites = [];
if (!empty($building['architects'])) {
    foreach ($building['architects'] as $architect) {
        $architectId = $architect['individual_architect_id'] ?? 0;
        if ($architectId > 0) {
            // individual_websiteがある場合は表示
            if (!empty($architect['individual_website'])) {
                $architectsWithWebsites[] = $architect;
            } else {
                // individual_websiteがなくても、スクリーンショット画像が存在する場合は表示
                $imagePath = $_SERVER['DOCUMENT_ROOT'] . "/screen_shots_3_webp/shot_{$architectId}.webp";
                if (file_exists($imagePath)) {
                    // スクリーンショット画像が存在するが、individual_websiteがない場合は、
                    // individual_websiteを空文字列として設定（URLが表示されないが、画像は表示される）
                    $architect['individual_website'] = '';
                    $architectsWithWebsites[] = $architect;
                }
            }
        }
    }
}

// 関連サイトがある場合のみ表示
if (!empty($architectsWithWebsites)): 
    $totalSites = count($architectsWithWebsites);
    $carouselId = 'relatedSitesCarousel';
    $modalId = 'relatedSitesModal';
?>

<!-- 関連サイトカード -->
<div class="card mb-4 border-0 shadow-sm related-sites-card">
    <div class="card-header">
        <h6 class="mb-0">
            <i data-lucide="square-mouse-pointer" class="me-2" style="width: 16px; height: 16px;"></i>
            <?php echo $lang === 'ja' ? '関連サイト' : 'Useful Links'; ?>
        </h6>
    </div>
    
    <div class="card-body p-0">
        <!-- カルーセルコンテナ -->
        <div id="<?php echo $carouselId; ?>" class="carousel slide" data-bs-ride="false" data-bs-interval="false">
            <!-- カルーセルインジケーター（2つ以上の場合のみ表示） -->
            <?php if ($totalSites > 1): ?>
            <div class="carousel-indicators">
                <?php for ($i = 0; $i < $totalSites; $i++): ?>
                    <button type="button" 
                            data-bs-target="#<?php echo $carouselId; ?>" 
                            data-bs-slide-to="<?php echo $i; ?>" 
                            <?php echo $i === 0 ? 'class="active" aria-current="true"' : ''; ?>
                            aria-label="Slide <?php echo $i + 1; ?>"></button>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            
            <!-- カルーセル内側 -->
            <div class="carousel-inner">
                <?php foreach ($architectsWithWebsites as $index => $architect): ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <a href="<?php echo !empty($architect['individual_website']) ? htmlspecialchars($architect['individual_website']) : '#'; ?>" 
                           <?php if (!empty($architect['individual_website'])): ?>target="_blank"<?php else: ?>onclick="return false;" style="cursor: default;"<?php endif; ?>
                           class="text-decoration-none d-block position-relative overflow-hidden"
                           style="transition: all 0.3s ease;">
                            
                            <!-- スクリーンショット画像 -->
                            <div class="position-relative">
                                <?php 
                                // Alt属性の生成（建築家名 + ウェブサイト名）
                                $altText = $architect['architectJa'] ?? $architect['architectEn'] ?? '';
                                if (!empty($architect['website_title'])) {
                                    // HTMLエンティティをデコード（&amp; → &）
                                    $decodedWebsiteTitle = html_entity_decode($architect['website_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                    $altText .= ' - ' . $decodedWebsiteTitle;
                                }
                                
                                $architectId = $architect['individual_architect_id'] ?? '';
                                $imagePath = "/screen_shots_3_webp/shot_{$architectId}.webp";
                                ?>
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($altText); ?>"
                                     class="img-fluid w-100 related-site-image"
                                     data-architect-id="<?php echo htmlspecialchars($architectId); ?>"
                                     data-image-path="<?php echo htmlspecialchars($imagePath); ?>"
                                     style="width: 100%; height: 300px; object-fit: cover; transition: all 0.3s ease;"
                                     onerror="console.error('Related Site Image Error:', {architectId: '<?php echo htmlspecialchars($architectId); ?>', imagePath: '<?php echo htmlspecialchars($imagePath); ?>', architectName: '<?php echo htmlspecialchars($architect['architectJa'] ?? ''); ?>'}); this.style.display='none';"
                                     onload="if (<?php echo isset($_GET['debug']) && $_GET['debug'] === '1' ? 'true' : 'false'; ?>) console.log('Related Site Image Loaded:', {architectId: '<?php echo htmlspecialchars($architectId); ?>', imagePath: '<?php echo htmlspecialchars($imagePath); ?>'});">
                                
                                <!-- オーバーレイ効果 -->
                                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center related-site-overlay"
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
                                    $displayTitle = $architect['website_title'] ?? $architect['architectJa'] ?? $architect['architectEn'] ?? '';
                                    // HTMLエンティティをデコード（&amp; → &）してからエスケープ
                                    $decodedTitle = html_entity_decode($displayTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                    echo htmlspecialchars($decodedTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                    ?>
                                </h5>
                                <?php if (!empty($architect['individual_website'])): ?>
                                    <div class="d-flex align-items-center">
                                        <i data-lucide="globe" class="me-2 text-primary" style="width: 14px; height: 14px;"></i>
                                        <small class="text-muted text-truncate">
                                            <?php echo htmlspecialchars($architect['individual_website']); ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center">
                                        <i data-lucide="image" class="me-2 text-muted" style="width: 14px; height: 14px;"></i>
                                        <small class="text-muted text-truncate" style="font-style: italic;">
                                            <?php echo $lang === 'ja' ? 'ウェブサイト情報なし' : 'No website information'; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- ナビゲーションボタン（2つ以上の場合のみ表示） -->
            <?php if ($totalSites > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $carouselId; ?>" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">前へ</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $carouselId; ?>" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">次へ</span>
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- カードフッター -->
    <div class="card-footer bg-light">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted">
                <span id="relatedSitesCounter">1 / <?php echo $totalSites; ?></span>
            </div>
            <div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="openRelatedSitesModal">
                    <i data-lucide="maximize" class="me-1" style="width: 14px; height: 14px;"></i>
                    <?php echo $lang === 'ja' ? '拡大表示' : 'View Full Size'; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 関連サイトモーダル -->
<div id="<?php echo $modalId; ?>" class="modal fade" tabindex="-1" aria-labelledby="relatedSitesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark">
            <!-- モーダルヘッダー -->
            <div class="modal-header border-0">
                <h5 class="modal-title text-white" id="relatedSitesModalLabel">
                    <i data-lucide="square-mouse-pointer" class="me-2" style="width: 20px; height: 20px;"></i>
                    <span id="relatedSitesModalTitle"><?php echo $lang === 'ja' ? '関連サイト' : 'Useful Links'; ?></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" id="closeRelatedSitesModalBtn" aria-label="Close"></button>
            </div>
            
            <!-- モーダルボディ -->
            <div class="modal-body p-0">
                <!-- カルーセルコンテナ -->
                <div id="relatedSitesModalCarousel" class="carousel slide" data-bs-ride="false" data-bs-interval="false">
                    <!-- カルーセルインジケーター -->
                    <?php if ($totalSites > 1): ?>
                    <div class="carousel-indicators" id="relatedSitesModalIndicators">
                        <?php for ($i = 0; $i < $totalSites; $i++): ?>
                            <button type="button" 
                                    data-bs-target="#relatedSitesModalCarousel" 
                                    data-bs-slide-to="<?php echo $i; ?>" 
                                    <?php echo $i === 0 ? 'class="active" aria-current="true"' : ''; ?>
                                    aria-label="Slide <?php echo $i + 1; ?>"></button>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- カルーセル内側 -->
                    <div class="carousel-inner" id="relatedSitesModalInner">
                        <?php foreach ($architectsWithWebsites as $index => $architect): ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <div class="d-flex flex-column h-100">
                                    <!-- スクリーンショット画像 -->
                                    <div class="position-relative flex-grow-1" style="cursor: pointer; min-height: 60vh;">
                                        <?php 
                                        $altText = $architect['architectJa'] ?? $architect['architectEn'] ?? '';
                                        if (!empty($architect['website_title'])) {
                                            $decodedWebsiteTitle = html_entity_decode($architect['website_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                            $altText .= ' - ' . $decodedWebsiteTitle;
                                        }
                                        ?>
                                        <?php 
                                        $modalArchitectId = $architect['individual_architect_id'] ?? '';
                                        $modalImagePath = "/screen_shots_3_webp/shot_{$modalArchitectId}.webp";
                                        ?>
                                        <img src="<?php echo htmlspecialchars($modalImagePath); ?>" 
                                             alt="<?php echo htmlspecialchars($altText); ?>"
                                             class="d-block w-100 related-site-modal-image"
                                             data-url="<?php echo htmlspecialchars($architect['individual_website'] ?? ''); ?>"
                                             style="width: 100%; height: 100%; object-fit: contain; background-color: #000;"
                                             onerror="this.style.display='none'">
                                    </div>
                                    
                                    <!-- タイトルとURL -->
                                    <div class="p-3 bg-dark border-top border-secondary">
                                        <h5 class="mb-2 text-white fw-bold" style="font-size: 1.1rem; line-height: 1.3;">
                                            <?php 
                                            $displayTitle = $architect['website_title'] ?? $architect['architectJa'] ?? $architect['architectEn'] ?? '';
                                            // HTMLエンティティをデコード（&amp; → &）してからエスケープ
                                            $decodedTitle = html_entity_decode($displayTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                            echo htmlspecialchars($decodedTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                            ?>
                                        </h5>
                                        <?php if (!empty($architect['individual_website'])): ?>
                                            <div class="d-flex align-items-center">
                                                <i data-lucide="globe" class="me-2 text-primary" style="width: 14px; height: 14px;"></i>
                                                <small class="text-white-50 text-truncate">
                                                    <?php echo htmlspecialchars($architect['individual_website']); ?>
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex align-items-center">
                                                <i data-lucide="image" class="me-2 text-white-50" style="width: 14px; height: 14px;"></i>
                                                <small class="text-white-50 text-truncate" style="font-style: italic;">
                                                    <?php echo $lang === 'ja' ? 'ウェブサイト情報なし' : 'No website information'; ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- ナビゲーションボタン -->
                    <?php if ($totalSites > 1): ?>
                    <button class="carousel-control-prev" type="button" data-bs-target="#relatedSitesModalCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">前へ</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#relatedSitesModalCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">次へ</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- モーダルフッター -->
            <div class="modal-footer border-0 bg-dark">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div class="text-white">
                        <span id="relatedSitesModalCounter">1 / <?php echo $totalSites; ?></span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-light btn-sm" id="relatedSitesFullscreenBtn">
                            <i data-lucide="maximize" style="width: 16px; height: 16px;"></i>
                            <?php echo $lang === 'ja' ? '全画面' : 'Fullscreen'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 全画面表示用のオーバーレイ -->
<div id="relatedSitesFullscreenOverlay" class="fullscreen-overlay" style="display: none;">
    <div class="fullscreen-container">
        <img id="relatedSitesFullscreenImage" class="fullscreen-image" alt="Fullscreen image">
        <button id="closeRelatedSitesFullscreen" class="btn-close-fullscreen">
            <i data-lucide="x" style="width: 24px; height: 24px;"></i>
        </button>
    </div>
</div>

<style>
    .related-sites-card:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    }
    .related-sites-card a:hover .related-site-overlay {
        opacity: 1 !important;
    }
    .related-sites-card a:hover img {
        transform: scale(1.05);
    }
    
    /* カルーセル用スタイル */
    .related-sites-card .carousel-indicators {
        bottom: 10px;
        margin-bottom: 0;
    }
    
    .related-sites-card .carousel-indicators button {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: 0 3px;
        background-color: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(0, 0, 0, 0.3);
    }
    
    .related-sites-card .carousel-indicators button.active {
        background-color: rgba(255, 255, 255, 0.9);
    }
    
    .related-sites-card .carousel-control-prev,
    .related-sites-card .carousel-control-next {
        width: 8%;
        opacity: 0.8;
        transition: opacity 0.3s ease;
        z-index: 10;
        background-color: rgba(0, 0, 0, 0.5);
    }
    
    .related-sites-card .carousel-control-prev:hover,
    .related-sites-card .carousel-control-next:hover {
        opacity: 1;
        background-color: rgba(0, 0, 0, 0.7);
    }
    
    .related-sites-card .carousel-control-prev-icon,
    .related-sites-card .carousel-control-next-icon {
        width: 25px;
        height: 25px;
    }
    
    /* 画像幅を調整して矢印と重ならないようにする */
    .related-sites-card .carousel-item {
        padding: 0 60px;
    }
    
    .related-sites-card .carousel-item img {
        width: calc(100% - 120px);
        margin: 0 auto;
        display: block;
    }
    
    /* モーダル用スタイル */
    #<?php echo $modalId; ?> .modal-content {
        border-radius: 0.5rem;
        overflow: hidden;
    }
    
    #<?php echo $modalId; ?> .carousel-indicators {
        bottom: 20px;
    }
    
    #<?php echo $modalId; ?> .carousel-indicators button {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin: 0 4px;
    }
    
    #<?php echo $modalId; ?> .carousel-control-prev,
    #<?php echo $modalId; ?> .carousel-control-next {
        width: 8%;
        background-color: rgba(0, 0, 0, 0.5);
        opacity: 0.8;
        transition: opacity 0.3s ease;
    }
    
    #<?php echo $modalId; ?> .carousel-control-prev:hover,
    #<?php echo $modalId; ?> .carousel-control-next:hover {
        opacity: 1;
        background-color: rgba(0, 0, 0, 0.7);
    }
    
    #<?php echo $modalId; ?> .carousel-control-prev-icon,
    #<?php echo $modalId; ?> .carousel-control-next-icon {
        width: 30px;
        height: 30px;
    }
    
    /* 画像幅を調整して矢印と重ならないようにする */
    #<?php echo $modalId; ?> .carousel-item {
        padding: 0 60px;
    }
    
    #<?php echo $modalId; ?> .carousel-item img {
        width: calc(100% - 120px);
        margin: 0 auto;
        display: block;
    }
    
    /* 全画面表示用スタイル */
    #relatedSitesFullscreenOverlay.fullscreen-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.95);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #relatedSitesFullscreenOverlay .fullscreen-container {
        position: relative;
        max-width: 95%;
        max-height: 95%;
    }
    
    #relatedSitesFullscreenOverlay .fullscreen-image {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    #relatedSitesFullscreenOverlay .btn-close-fullscreen {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.7);
        border: none;
        color: white;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    
    #relatedSitesFullscreenOverlay .btn-close-fullscreen:hover {
        background: rgba(0, 0, 0, 0.9);
    }
    
    /* スマホ対応 */
    @media (max-width: 768px) {
        .related-sites-card .carousel-control-prev,
        .related-sites-card .carousel-control-next {
            width: 8%;
        }
        
        #<?php echo $modalId; ?> .carousel-item img {
            height: 50vh;
        }
        
        #<?php echo $modalId; ?> .modal-dialog {
            margin: 0.5rem;
        }
    }
</style>

<script>
(function() {
    // 関連サイトカルーセルのカウンター更新
    const carouselElement = document.getElementById('<?php echo $carouselId; ?>');
    const counterElement = document.getElementById('relatedSitesCounter');
    
    if (carouselElement && counterElement) {
        carouselElement.addEventListener('slide.bs.carousel', function(event) {
            const currentIndex = event.to + 1;
            const total = <?php echo $totalSites; ?>;
            counterElement.textContent = currentIndex + ' / ' + total;
        });
    }
    
    // モーダルを開く
    const openModalBtn = document.getElementById('openRelatedSitesModal');
    const modalElement = document.getElementById('<?php echo $modalId; ?>');
    
    if (openModalBtn && modalElement) {
        // モーダルインスタンスを作成
        let modal = null;
        try {
            modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
        } catch (e) {
            console.error('Bootstrap Modal error:', e);
            // フォールバック: 直接表示
            modal = {
                show: function() {
                    modalElement.style.display = 'block';
                    modalElement.classList.add('show');
                    document.body.classList.add('modal-open');
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.id = 'relatedSitesModalBackdrop';
                    document.body.appendChild(backdrop);
                },
                hide: function() {
                    modalElement.style.display = 'none';
                    modalElement.classList.remove('show');
                    document.body.classList.remove('modal-open');
                    const backdrop = document.getElementById('relatedSitesModalBackdrop');
                    if (backdrop) backdrop.remove();
                }
            };
        }
        
        openModalBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // 現在のカルーセルの位置を取得
            const currentCarousel = document.getElementById('<?php echo $carouselId; ?>');
            let activeIndex = 0;
            if (currentCarousel) {
                const activeItem = currentCarousel.querySelector('.carousel-item.active');
                if (activeItem) {
                    activeIndex = Array.from(currentCarousel.querySelectorAll('.carousel-item')).indexOf(activeItem);
                }
            }
            
            // モーダル内のカルーセルを同じ位置に設定
            const modalCarousel = document.getElementById('relatedSitesModalCarousel');
            if (modalCarousel) {
                // モーダルが開いた後にカルーセルを設定
                setTimeout(function() {
                    try {
                        const modalCarouselInstance = bootstrap.Carousel.getInstance(modalCarousel) || new bootstrap.Carousel(modalCarousel);
                        modalCarouselInstance.to(activeIndex);
                    } catch (e) {
                        // 手動でアクティブなアイテムを設定
                        const items = modalCarousel.querySelectorAll('.carousel-item');
                        items.forEach((item, index) => {
                            item.classList.remove('active');
                            if (index === activeIndex) {
                                item.classList.add('active');
                            }
                        });
                    }
                }, 100);
            }
            
            modal.show();
        });
    }
    
    // モーダルを閉じる関数
    function closeRelatedSitesModal() {
        if (!modalElement) return;
        
        try {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            } else {
                // フォールバック
                modalElement.style.display = 'none';
                modalElement.classList.remove('show');
                document.body.classList.remove('modal-open');
                const backdrop = document.getElementById('relatedSitesModalBackdrop');
                if (backdrop) backdrop.remove();
            }
        } catch (e) {
            // フォールバック
            modalElement.style.display = 'none';
            modalElement.classList.remove('show');
            document.body.classList.remove('modal-open');
            const backdrop = document.getElementById('relatedSitesModalBackdrop');
            if (backdrop) backdrop.remove();
        }
    }
    
    // モーダルを閉じるボタン
    const closeModalBtn = document.getElementById('closeRelatedSitesModalBtn');
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            closeRelatedSitesModal();
        });
    }
    
    // ESCキーでモーダルを閉じる
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // 全画面表示が開いている場合は閉じる
            const fullscreenOverlay = document.getElementById('relatedSitesFullscreenOverlay');
            if (fullscreenOverlay && fullscreenOverlay.style.display === 'flex') {
                fullscreenOverlay.style.display = 'none';
                return;
            }
            
            // モーダルが開いている場合は閉じる
            if (modalElement && modalElement.classList.contains('show')) {
                closeRelatedSitesModal();
            }
        }
    });
    
    // モーダル内のカルーセルカウンター更新
    const modalCarousel = document.getElementById('relatedSitesModalCarousel');
    const modalCounter = document.getElementById('relatedSitesModalCounter');
    
    if (modalCarousel && modalCounter) {
        modalCarousel.addEventListener('slide.bs.carousel', function(event) {
            const currentIndex = event.to + 1;
            const total = <?php echo $totalSites; ?>;
            modalCounter.textContent = currentIndex + ' / ' + total;
        });
    }
    
    // モーダル内の画像クリックでURLを開く
    const modalImages = document.querySelectorAll('#relatedSitesModalCarousel .related-site-modal-image');
    modalImages.forEach(function(img) {
        img.addEventListener('click', function(e) {
            const url = this.getAttribute('data-url');
            if (url) {
                window.open(url, '_blank');
            }
        });
    });
    
    // 全画面表示
    const fullscreenBtn = document.getElementById('relatedSitesFullscreenBtn');
    const fullscreenOverlay = document.getElementById('relatedSitesFullscreenOverlay');
    const fullscreenImg = document.getElementById('relatedSitesFullscreenImage');
    const closeFullscreenBtn = document.getElementById('closeRelatedSitesFullscreen');
    
    if (fullscreenBtn && fullscreenOverlay && fullscreenImg) {
        fullscreenBtn.addEventListener('click', function() {
            const activeModalItem = document.querySelector('#relatedSitesModalCarousel .carousel-item.active img');
            if (activeModalItem && activeModalItem.src) {
                fullscreenImg.src = activeModalItem.src;
                fullscreenOverlay.style.display = 'flex';
                
                // モーダルを閉じる
                const modalElement = document.getElementById('<?php echo $modalId; ?>');
                if (modalElement) {
                    try {
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) modal.hide();
                    } catch (e) {
                        modalElement.style.display = 'none';
                        modalElement.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        const backdrop = document.getElementById('relatedSitesModalBackdrop');
                        if (backdrop) backdrop.remove();
                    }
                }
            }
        });
        
        // 閉じるボタン
        if (closeFullscreenBtn) {
            closeFullscreenBtn.addEventListener('click', function() {
                fullscreenOverlay.style.display = 'none';
            });
        }
        
        // ESCキーで閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && fullscreenOverlay.style.display === 'flex') {
                fullscreenOverlay.style.display = 'none';
            }
        });
    }
})();
</script>

<?php endif; ?>
