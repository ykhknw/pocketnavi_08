<?php
/**
 * 写真カルーセルモーダルコンポーネント
 */
?>

<!-- 写真カルーセルモーダル -->
<div id="photoCarouselModal" class="modal fade" tabindex="-1" aria-labelledby="photoCarouselModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark">
            <!-- モーダルヘッダー -->
            <div class="modal-header border-0">
                <h5 class="modal-title text-white" id="photoCarouselModalLabel">
                    <i data-lucide="image" class="me-2" style="width: 20px; height: 20px;"></i>
                    <span id="modalTitle">写真ギャラリー</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- モーダルボディ -->
            <div class="modal-body p-0">
                <!-- カルーセルコンテナ -->
                <div id="photoCarousel" class="carousel slide" data-bs-ride="false" data-bs-interval="false">
                    <!-- カルーセルインジケーター -->
                    <div class="carousel-indicators" id="carouselIndicators">
                        <!-- 動的に生成される -->
                    </div>
                    
                    <!-- カルーセル内側 -->
                    <div class="carousel-inner" id="carouselInner">
                        <!-- 動的に生成される -->
                    </div>
                    
                    <!-- ナビゲーションボタン -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#photoCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">前へ</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#photoCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">次へ</span>
                    </button>
                </div>
            </div>
            
            <!-- モーダルフッター -->
            <div class="modal-footer border-0 bg-dark">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div class="text-white">
                        <span id="photoCounter">1 / 1</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-light btn-sm" id="fullscreenBtn">
                            <i data-lucide="maximize" style="width: 16px; height: 16px;"></i>
                            全画面
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 全画面表示用のオーバーレイ -->
<div id="fullscreenOverlay" class="fullscreen-overlay" style="display: none;">
    <div class="fullscreen-container">
        <img id="fullscreenImage" class="fullscreen-image" alt="Fullscreen image">
        <button id="closeFullscreen" class="btn-close-fullscreen">
            <i data-lucide="x" style="width: 24px; height: 24px;"></i>
        </button>
    </div>
</div>

<style>
/* カルーセルモーダル用スタイル */
#photoCarouselModal .modal-content {
    border-radius: 0.5rem;
    overflow: hidden;
}

#photoCarouselModal .carousel-item img {
    width: 100%;
    height: 70vh;
    object-fit: contain;
    background-color: #000;
}

#photoCarouselModal .carousel-indicators {
    bottom: 20px;
}

#photoCarouselModal .carousel-indicators button {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin: 0 4px;
}

#photoCarouselModal .carousel-control-prev,
#photoCarouselModal .carousel-control-next {
    width: 5%;
}

#photoCarouselModal .carousel-control-prev-icon,
#photoCarouselModal .carousel-control-next-icon {
    width: 30px;
    height: 30px;
}

/* 全画面表示用スタイル */
.fullscreen-overlay {
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

.fullscreen-container {
    position: relative;
    max-width: 95%;
    max-height: 95%;
}

.fullscreen-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.btn-close-fullscreen {
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

.btn-close-fullscreen:hover {
    background: rgba(0, 0, 0, 0.9);
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    #photoCarouselModal .carousel-item img {
        height: 50vh;
    }
    
    #photoCarouselModal .modal-dialog {
        margin: 0.5rem;
    }
}
</style>
