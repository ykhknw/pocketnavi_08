<?php
/**
 * 写真ギャラリーカードコンポーネント
 * 建築物詳細ページで写真がある場合に表示されるカルーセル形式のギャラリー
 */
?>

<!-- 写真ギャラリーカード -->
<div class="card mb-4" id="photoGalleryCard" style="display: none;">
    <div class="card-header bg-light">
        <h6 class="mb-0">
            <i data-lucide="image" class="me-2" style="width: 18px; height: 18px;"></i>
            <?php echo $lang === 'ja' ? '写真ギャラリー' : 'Photo Gallery'; ?>
        </h6>
    </div>
    
    <div class="card-body p-0">
        <!-- カルーセルコンテナ -->
        <div id="photoGalleryCarousel" class="carousel slide" data-bs-ride="false" data-bs-interval="false">
            <!-- カルーセルインジケーター -->
            <div class="carousel-indicators" id="galleryIndicators">
                <!-- 動的に生成される -->
            </div>
            
            <!-- カルーセル内側 -->
            <div class="carousel-inner" id="galleryInner">
                <!-- 動的に生成される -->
            </div>
            
            <!-- ナビゲーションボタン -->
            <button class="carousel-control-prev" type="button" data-bs-target="#photoGalleryCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">前へ</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#photoGalleryCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">次へ</span>
            </button>
        </div>
    </div>
    
    <!-- カードフッター -->
    <div class="card-footer bg-light">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted">
                <span id="galleryCounter">1 / 1</span>
            </div>
            <div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="openGalleryModal">
                    <i data-lucide="maximize" class="me-1" style="width: 14px; height: 14px;"></i>
                    <?php echo $lang === 'ja' ? '拡大表示' : 'View Full Size'; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* 写真ギャラリーカード用スタイル */
#photoGalleryCard .carousel-item img {
    width: 100%;
    height: 500px; /* PC: 400px → 500px */
    object-fit: contain; /* cover → contain で縦横比を維持しつつ全体を表示 */
    background-color: #f8f9fa;
}

#photoGalleryCard .carousel-indicators {
    bottom: 10px;
}

#photoGalleryCard .carousel-indicators button {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin: 0 3px;
}

#photoGalleryCard .carousel-control-prev,
#photoGalleryCard .carousel-control-next {
    width: 5%;
    opacity: 0.8;
    transition: opacity 0.3s ease;
    z-index: 10;
    background-color: rgba(0, 0, 0, 0.1);
}

#photoGalleryCard .carousel-control-prev:hover,
#photoGalleryCard .carousel-control-next:hover {
    opacity: 1;
    background-color: rgba(0, 0, 0, 0.2);
}

#photoGalleryCard .carousel-control-prev-icon,
#photoGalleryCard .carousel-control-next-icon {
    width: 25px;
    height: 25px;
}

/* 写真が1枚の場合は矢印を非表示（JavaScriptで制御するため、このCSSルールは削除） */

/* タブレット対応 (768px - 1024px) */
@media (max-width: 1024px) and (min-width: 769px) {
    #photoGalleryCard .carousel-item img {
        height: 420px; /* タブレット: 350px → 420px */
    }
}

/* スマホ対応 (768px以下) */
@media (max-width: 768px) {
    #photoGalleryCard .carousel-item img {
        height: 320px; /* スマホ: 250px → 320px */
    }
    
    #photoGalleryCard .carousel-control-prev,
    #photoGalleryCard .carousel-control-next {
        width: 8%;
    }
}

/* 小さいスマホ対応 (480px以下) */
@media (max-width: 480px) {
    #photoGalleryCard .carousel-item img {
        height: 250px; /* 小さいスマホ: 200px → 250px */
    }
}

/* 大きなデスクトップ対応 (1200px以上) */
@media (min-width: 1200px) {
    #photoGalleryCard .carousel-item img {
        height: 550px; /* 大きなPC: 450px → 550px */
    }
}

/* Youtube再生ボタンスタイル */
.youtube-play-button {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.youtube-play-button:hover {
    transform: scale(1.1);
    opacity: 0.9;
}

.youtube-play-button svg {
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}
</style>

<script>
// 写真ギャラリーの初期化と管理
class PhotoGalleryManager {
    constructor() {
        this.photos = [];
        this.currentIndex = 0;
        this.carousel = null;
        this.isInitialized = false;
    }
    
    // 写真データを設定
    setPhotos(photos, youtubeUrl = null) {
        this.photos = photos;
        this.youtubeUrl = youtubeUrl;
        if (photos.length > 0 || youtubeUrl) {
            this.showGallery();
            this.initializeCarousel();
        } else {
            this.hideGallery();
        }
    }
    
    // ギャラリーを表示
    showGallery() {
        const galleryCard = document.getElementById('photoGalleryCard');
        if (galleryCard) {
            galleryCard.style.display = 'block';
        }
    }
    
    // ギャラリーを非表示
    hideGallery() {
        const galleryCard = document.getElementById('photoGalleryCard');
        if (galleryCard) {
            galleryCard.style.display = 'none';
        }
    }
    
    // カルーセルを初期化
    initializeCarousel() {
        if (this.isInitialized) return;
        
        const indicatorsContainer = document.getElementById('galleryIndicators');
        const innerContainer = document.getElementById('galleryInner');
        const counter = document.getElementById('galleryCounter');
        
        if (!indicatorsContainer || !innerContainer || !counter) return;
        
        // インジケーターを生成（写真+動画が2つ以上の場合のみ）
        indicatorsContainer.innerHTML = '';
        const totalItems = this.photos.length + (this.youtubeUrl ? 1 : 0);
        if (totalItems > 1) {
            for (let i = 0; i < totalItems; i++) {
                const button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('data-bs-target', '#photoGalleryCarousel');
                button.setAttribute('data-bs-slide-to', i.toString());
                button.className = i === 0 ? 'active' : '';
                button.setAttribute('aria-current', i === 0 ? 'true' : 'false');
                button.setAttribute('aria-label', `Slide ${i + 1}`);
                indicatorsContainer.appendChild(button);
            }
        }
        
        // 矢印の表示制御（写真+動画が2つ以上の場合のみ表示）
        const prevButton = document.querySelector('#photoGalleryCarousel .carousel-control-prev');
        const nextButton = document.querySelector('#photoGalleryCarousel .carousel-control-next');
        
        if (totalItems <= 1) {
            if (prevButton) prevButton.style.display = 'none';
            if (nextButton) nextButton.style.display = 'none';
        } else {
            if (prevButton) prevButton.style.display = 'block';
            if (nextButton) nextButton.style.display = 'block';
        }
        
        // カルーセルアイテムを生成
        innerContainer.innerHTML = '';
        
        // 写真アイテムを生成
        this.photos.forEach((photo, index) => {
            const item = document.createElement('div');
            item.className = `carousel-item ${index === 0 ? 'active' : ''}`;
            
            const img = document.createElement('img');
            img.src = photo;
            img.className = 'd-block w-100';
            img.alt = `Photo ${index + 1}`;
            img.loading = 'lazy';
            
            // 画像読み込み後に高さを動的に調整
            img.onload = () => {
                this.adjustImageHeight(img);
            };
            
            item.appendChild(img);
            innerContainer.appendChild(item);
        });
        
        // 動画アイテムを生成（最後に追加）
        if (this.youtubeUrl) {
            const videoItem = document.createElement('div');
            videoItem.className = 'carousel-item';
            
            const videoContainer = document.createElement('div');
            videoContainer.className = 'video-container d-flex align-items-center justify-content-center';
            videoContainer.style.cssText = 'height: 500px; background-color: #f8f9fa; cursor: pointer;';
            
            // 動画サムネイルを取得
            const videoId = this.getVideoId(this.youtubeUrl);
            if (videoId) {
                const thumbnailUrl = `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`;
                const img = document.createElement('img');
                img.src = thumbnailUrl;
                img.className = 'd-block w-100';
                img.alt = 'Video thumbnail';
                img.style.cssText = 'height: 500px; object-fit: contain;';
                img.onerror = () => {
                    // サムネイル取得失敗時のフォールバック
                    videoContainer.innerHTML = `
                        <div class="text-center">
                            <i data-lucide="play-circle" style="width: 80px; height: 80px; color: #dc3545;"></i>
                            <h5 class="mt-3">動画を再生</h5>
                        </div>
                    `;
                };
                videoContainer.appendChild(img);
            } else {
                // 動画IDが取得できない場合のフォールバック
                videoContainer.innerHTML = `
                    <div class="text-center">
                        <i data-lucide="play-circle" style="width: 80px; height: 80px; color: #dc3545;"></i>
                        <h5 class="mt-3">動画を再生</h5>
                    </div>
                `;
            }
            
            // Youtube再生ボタンオーバーレイ
            const playButton = document.createElement('div');
            playButton.className = 'youtube-play-button position-absolute top-50 start-50 translate-middle';
            playButton.innerHTML = `
                <svg width="68" height="48" viewBox="0 0 68 48">
                    <path d="M66.52,7.74c-0.78-2.93-2.49-5.41-5.42-6.19C55.79,.13,34,0,34,0S12.21,.13,6.9,1.55 C3.97,2.33,2.27,4.81,1.48,7.74C0.06,13.05,0,24,0,24s0.06,10.95,1.48,16.26c0.78,2.93,2.49,5.41,5.42,6.19 C12.21,47.87,34,48,34,48s21.79-0.13,27.1-1.55c2.93-0.78,4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74z" fill="#f00"></path>
                    <path d="M 45,24 27,14 27,34" fill="#fff"></path>
                </svg>
            `;
            playButton.style.cssText = 'z-index: 5; cursor: pointer;';
            
            videoContainer.style.position = 'relative';
            videoContainer.appendChild(playButton);
            
            // クリックイベント
            videoContainer.addEventListener('click', () => {
                window.open(this.youtubeUrl, '_blank');
            });
            
            videoItem.appendChild(videoContainer);
            innerContainer.appendChild(videoItem);
        }
        
        // カウンターを更新
        this.updateCounter();
        
        // カルーセルイベントリスナーを設定
        const carouselElement = document.getElementById('photoGalleryCarousel');
        if (carouselElement) {
            carouselElement.addEventListener('slide.bs.carousel', (event) => {
                this.currentIndex = event.to;
                this.updateCounter();
            });
        }
        
        // モーダル表示ボタンのイベントリスナー
        const modalButton = document.getElementById('openGalleryModal');
        if (modalButton) {
            modalButton.addEventListener('click', () => {
                this.openModal();
            });
        }
        
        this.isInitialized = true;
    }
    
    // 画像の高さを動的に調整
    adjustImageHeight(img) {
        const naturalWidth = img.naturalWidth;
        const naturalHeight = img.naturalHeight;
        const aspectRatio = naturalHeight / naturalWidth;
        
        // デバイスサイズに応じた最大高さを取得
        const maxHeight = this.getMaxHeightForDevice();
        
        // アスペクト比に基づいて高さを計算
        let calculatedHeight = maxHeight;
        
        // 縦長画像の場合（アスペクト比 > 1.2）
        if (aspectRatio > 1.2) {
            calculatedHeight = Math.min(maxHeight * 1.3, maxHeight * 1.5);
        }
        // 横長画像の場合（アスペクト比 < 0.8）
        else if (aspectRatio < 0.8) {
            calculatedHeight = Math.max(maxHeight * 0.7, maxHeight * 0.6);
        }
        
        // 高さを設定
        img.style.height = `${calculatedHeight}px`;
    }
    
    // デバイスサイズに応じた最大高さを取得
    getMaxHeightForDevice() {
        const width = window.innerWidth;
        
        if (width >= 1200) return 550; // 大きなPC
        if (width >= 1025) return 500; // PC
        if (width >= 769) return 420;  // タブレット
        if (width >= 481) return 320;  // スマホ
        return 250; // 小さいスマホ
    }
    
    // カウンターを更新
    updateCounter() {
        const counter = document.getElementById('galleryCounter');
        if (counter) {
            const totalItems = this.photos.length + (this.youtubeUrl ? 1 : 0);
            counter.textContent = `${this.currentIndex + 1} / ${totalItems}`;
        }
    }
    
    // Youtube URLからvideoIdを取得
    getVideoId(url) {
        if (!url) return null;
        
        // 複数のYoutube URLパターンに対応
        const patterns = [
            /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/,  // 通常の動画
            /youtube\.com\/shorts\/([^&\n?#]+)/,  // Shorts
            /youtube\.com\/live\/([^&\n?#]+)/,   // ライブ配信
        ];
        
        for (const pattern of patterns) {
            const match = url.match(pattern);
            if (match) {
                return match[1];
            }
        }
        
        return null;
    }
    
    // モーダルを開く
    openModal() {
        // 既存のモーダル機能を使用
        if (typeof openPhoto === 'function') {
            // 現在の建築物のUIDを取得
            const buildingCard = document.querySelector('.building-card');
            if (buildingCard) {
                const uid = buildingCard.getAttribute('data-uid');
                if (uid) {
                    openPhoto(uid);
                }
            }
        }
    }
}

// グローバルインスタンスを作成
window.photoGalleryManager = new PhotoGalleryManager();
</script>
