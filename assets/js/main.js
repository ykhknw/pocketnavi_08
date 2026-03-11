// Main JavaScript for PocketNavi

let map;
let markers = [];

// 地図の初期化
function initMap(center = [35.6814, 139.7670], buildings = []) {
    
    // マップ要素の存在確認
    const mapElement = document.getElementById('map');
    if (!mapElement) {
        console.error('Map element not found');
        return;
    }
    
    if (map) {
        map.remove();
    }
    
    try {
        map = L.map('map').setView(center, 15);
        
        // グローバル変数としてMapインスタンスを保存（付近検索機能用）
        window.mapInstance = map;
        
        // タイルレイヤーの追加
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
    } catch (error) {
        console.error('Error creating map:', error);
        return;
    }
    
    // ズームコントロールの位置調整
    map.zoomControl.setPosition('bottomleft');
    
    
    // マーカーの追加
    addMarkers(buildings);
    
    // 全マーカーを表示する範囲に調整（複数マーカーの場合のみ）
    if (buildings.length > 1) {
        const group = new L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
}

// マーカーの追加
function addMarkers(buildings) {
    // 既存のマーカーを削除
    markers.forEach(marker => map.removeLayer(marker));
    markers = [];
    
    // ページ情報を取得（通し番号計算用）
    const pageInfo = window.pageInfo || { currentPage: 1, limit: 10 };
    
    buildings.forEach((building, index) => {
        if (building.lat && building.lng && building.lat !== 0 && building.lng !== 0) {
            const isDetailView = buildings.length === 1;
            
            let icon;
            if (isDetailView) {
                // 詳細ページ用の赤いマーカー
                icon = L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                });
            } else {
                // 一覧ページ用の数字付きマーカー（通し番号）
                const globalIndex = (pageInfo.currentPage - 1) * pageInfo.limit + index + 1;
                icon = L.divIcon({
                    html: `<div style="background-color: #2563eb; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">${globalIndex}</div>`,
                    className: 'custom-marker',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });
            }
            
            const marker = L.marker([building.lat, building.lng], { icon })
                .bindPopup(createPopupContent(building), {
                    closeButton: true,
                    autoClose: false,
                    closeOnClick: false
                })
                .on('popupopen', function() {
                    // ポップアップが開かれた時にLucideアイコンを初期化
                    setTimeout(() => {
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }, 100);
                });
            
            markers.push(marker);
            map.addLayer(marker);
        }
    });
}

// ポップアップコンテンツの作成（PHPで生成されたHTMLを使用）
function createPopupContent(building) {
    // PHPで生成されたHTMLを直接使用
    return building.popupContent || '';
}

// 現在地の取得
function getCurrentLocation() {
    const btn = document.getElementById('getLocationBtn');
    const originalText = btn.innerHTML;
    
    // 現在の言語を取得
    const currentUrl = new URL(window.location);
    const lang = currentUrl.searchParams.get('lang') || 'ja';
    const loadingText = lang === 'ja' ? '取得中...' : 'Getting...';
    
    btn.innerHTML = '<i data-lucide="loader-2" class="me-1" style="width: 16px; height: 16px; animation: spin 1s linear infinite;"></i>' + loadingText;
    btn.disabled = true;
    
    // HTTPS環境でない場合の警告
    if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
        const errorMessage = lang === 'ja' 
            ? '位置情報の取得にはHTTPS接続が必要です。'
            : 'HTTPS connection is required for geolocation.';
        alert(errorMessage);
        btn.innerHTML = originalText;
        btn.disabled = false;
        return;
    }
    
    if (navigator.geolocation) {
        // 位置情報のオプション設定
        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 300000 // 5分間キャッシュ
        };
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                // 現在地検索のURLにリダイレクト（ルートディレクトリから）
                const lang = new URLSearchParams(window.location.search).get('lang') || 'ja';
                const rootUrl = new URL('/', window.location.origin);
                
                // 現在地検索用のパラメータを設定
                rootUrl.searchParams.set('lang', lang);
                rootUrl.searchParams.set('lat', lat);
                rootUrl.searchParams.set('lng', lng);
                rootUrl.searchParams.set('radius', '5'); // デフォルト5km
                
                window.location.href = rootUrl.toString();
            },
            function(error) {
                let errorMessage = '';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage = lang === 'ja' 
                            ? '位置情報のアクセスが拒否されました。ブラウザの設定で位置情報を許可してください。'
                            : 'Location access denied. Please allow location access in your browser settings.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage = lang === 'ja' 
                            ? '位置情報が利用できません。'
                            : 'Location information is unavailable.';
                        break;
                    case error.TIMEOUT:
                        errorMessage = lang === 'ja' 
                            ? '位置情報の取得がタイムアウトしました。'
                            : 'Location request timed out.';
                        break;
                    default:
                        errorMessage = lang === 'ja' 
                            ? '位置情報の取得に失敗しました: ' + error.message
                            : 'Failed to get location: ' + error.message;
                        break;
                }
                alert(errorMessage);
                btn.innerHTML = originalText;
                btn.disabled = false;
            },
            options
        );
    } else {
        const errorMessage = lang === 'ja' 
            ? 'このブラウザは位置情報をサポートしていません。'
            : 'This browser does not support geolocation.';
        alert(errorMessage);
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// 現在地マーカーの追加
function addCurrentLocationMarker(lat, lng) {
    // 既存の現在地マーカーを削除
    markers.forEach(marker => {
        if (marker.options.isLocationMarker) {
            map.removeLayer(marker);
            const index = markers.indexOf(marker);
            if (index > -1) {
                markers.splice(index, 1);
            }
        }
    });
    
    const locationIcon = L.divIcon({
        html: '<div style="background-color: #ef4444; border-radius: 50%; width: 16px; height: 16px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2); animation: pulse 2s infinite;"></div>',
        className: 'location-marker',
        iconSize: [16, 16],
        iconAnchor: [8, 8]
    });
    
    const locationMarker = L.marker([lat, lng], { 
        icon: locationIcon,
        isLocationMarker: true
    }).bindPopup('<div style="padding: 8px;"><strong>現在地</strong></div>');
    
    markers.push(locationMarker);
    map.addLayer(locationMarker);
}

// 地図上で建物を表示
function showOnMap(lat, lng) {
    if (map) {
        map.setView([lat, lng], 15);
    }
}

// 動画を開く
function openVideo(url) {
    window.open(url, '_blank');
}

// 写真を開く（カルーセル表示）
// 古い関数を完全に削除
delete window.openPhoto;

// 新しい関数を定義
window.openPhoto = function(uid) {
    
    // ローディング表示
    showPhotoCarouselLoading();
    
    // APIから写真リストを取得
    // 現在のパスからルートディレクトリを取得
    let basePath = window.location.pathname;
    
    // 建築物詳細ページの場合（/buildings/slug）はルートに戻る
    if (basePath.includes('/buildings/')) {
        basePath = '/';
    } else if (basePath === '/' || basePath === '/index.php') {
        // ルートページの場合はそのまま
        basePath = '/';
    } else {
        // その他の場合は現在のディレクトリを使用
        basePath = basePath.replace(/\/[^\/]*$/, '');
        if (basePath === '') basePath = '/';
    }
    
    // パスの最後にスラッシュがない場合は追加
    if (!basePath.endsWith('/')) {
        basePath += '/';
    }
    
    const apiUrl = `${window.location.origin}${basePath}api/get-photos.php?uid=${encodeURIComponent(uid)}`;
    console.log('openPhoto API URL:', apiUrl);
    
    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.photos && data.photos.length > 0) {
                showPhotoCarousel(data.photos, uid);
            } else {
                showPhotoError(`写真が見つかりませんでした。${data.error ? ' (' + data.error + ')' : ''}`);
            }
        })
        .catch(error => {
            showPhotoError(`写真の読み込みに失敗しました。エラー: ${error.message}`);
        });
}

// 写真カルーセルのローディング表示
function showPhotoCarouselLoading() {
    const modal = document.getElementById('photoCarouselModal');
    const carouselInner = document.getElementById('carouselInner');
    const carouselIndicators = document.getElementById('carouselIndicators');
    const photoCounter = document.getElementById('photoCounter');
    
    // ローディング状態を設定
    carouselInner.innerHTML = `
        <div class="carousel-item active">
            <div class="d-flex align-items-center justify-content-center" style="height: 70vh; background-color: #000;">
                <div class="text-center text-white">
                    <div class="spinner-border mb-3" role="status">
                        <span class="visually-hidden">読み込み中...</span>
                    </div>
                    <p>写真を読み込み中...</p>
                </div>
            </div>
        </div>
    `;
    
    carouselIndicators.innerHTML = '';
    photoCounter.textContent = '読み込み中...';
    
    // モーダルを表示
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// 写真カルーセルを表示
function showPhotoCarousel(photos, uid) {
    const modal = document.getElementById('photoCarouselModal');
    const carouselInner = document.getElementById('carouselInner');
    const carouselIndicators = document.getElementById('carouselIndicators');
    const photoCounter = document.getElementById('photoCounter');
    
    // カルーセルアイテムを生成
    carouselInner.innerHTML = '';
    carouselIndicators.innerHTML = '';
    
    photos.forEach((photo, index) => {
        // カルーセルアイテム
        const carouselItem = document.createElement('div');
        carouselItem.className = `carousel-item ${index === 0 ? 'active' : ''}`;
        carouselItem.innerHTML = `
            <img src="${photo}" class="d-block w-100" alt="Photo ${index + 1}" loading="lazy">
        `;
        carouselInner.appendChild(carouselItem);
        
        // インジケーター
        const indicator = document.createElement('button');
        indicator.type = 'button';
        indicator.setAttribute('data-bs-target', '#photoCarousel');
        indicator.setAttribute('data-bs-slide-to', index);
        indicator.className = index === 0 ? 'active' : '';
        indicator.setAttribute('aria-label', `Slide ${index + 1}`);
        carouselIndicators.appendChild(indicator);
    });
    
    // カウンターを更新
    photoCounter.textContent = `1 / ${photos.length}`;
    
    // カルーセルイベントリスナーを設定
    const carousel = document.getElementById('photoCarousel');
    carousel.addEventListener('slid.bs.carousel', function(event) {
        const activeIndex = event.to;
        photoCounter.textContent = `${activeIndex + 1} / ${photos.length}`;
    });
    
    // イベント設定
    setupPhotoCarouselEvents(photos);
    
    // モーダルを表示
    const bsModal = new bootstrap.Modal(modal);
    
    // モーダルが閉じられた時のイベントリスナーを追加
    modal.addEventListener('hidden.bs.modal', function() {
        // バックドロップを手動で削除
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        
        // bodyのクラスをリセット
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });
    
    bsModal.show();
};

// DOMContentLoadedでも関数を再定義（本番環境での確実性のため）
document.addEventListener('DOMContentLoaded', function() {
    // 古い関数を削除
    delete window.openPhoto;
    
    // 新しい関数を定義
    window.openPhoto = function(uid) {
        
        // ローディング表示
        showPhotoCarouselLoading();
        
        // APIから写真リストを取得
        // 現在のパスからルートディレクトリを取得
        let basePath = window.location.pathname;
        
        // 建築物詳細ページの場合（/buildings/slug）はルートに戻る
        if (basePath.includes('/buildings/')) {
            basePath = '/';
        } else if (basePath === '/' || basePath === '/index.php') {
            // ルートページの場合はそのまま
            basePath = '/';
        } else {
            // その他の場合は現在のディレクトリを使用
            basePath = basePath.replace(/\/[^\/]*$/, '');
            if (basePath === '') basePath = '/';
        }
        
        // パスの最後にスラッシュがない場合は追加
        if (!basePath.endsWith('/')) {
            basePath += '/';
        }
        
        const apiUrl = `${window.location.origin}${basePath}api/get-photos.php?uid=${encodeURIComponent(uid)}`;
        console.log('openPhoto API URL:', apiUrl);
        
        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.photos && data.photos.length > 0) {
                    showPhotoCarousel(data.photos, uid);
                } else {
                    showPhotoError(`写真が見つかりませんでした。${data.error ? ' (' + data.error + ')' : ''}`);
                }
            })
            .catch(error => {
                showPhotoError(`写真の読み込みに失敗しました。エラー: ${error.message}`);
            });
    };
});

// 写真エラー表示
function showPhotoError(message) {
    const modal = document.getElementById('photoCarouselModal');
    const carouselInner = document.getElementById('carouselInner');
    const carouselIndicators = document.getElementById('carouselIndicators');
    const photoCounter = document.getElementById('photoCounter');
    
    carouselInner.innerHTML = `
        <div class="carousel-item active">
            <div class="d-flex align-items-center justify-content-center" style="height: 70vh; background-color: #000;">
                <div class="text-center text-white">
                    <i data-lucide="alert-circle" style="width: 48px; height: 48px;" class="mb-3"></i>
                    <p>${message}</p>
                </div>
            </div>
        </div>
    `;
    
    carouselIndicators.innerHTML = '';
    photoCounter.textContent = 'エラー';
    
    // モーダルを表示
    const bsModal = new bootstrap.Modal(modal);
    
    // モーダルが閉じられた時のイベントリスナーを追加
    modal.addEventListener('hidden.bs.modal', function() {
        // バックドロップを手動で削除
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        
        // bodyのクラスをリセット
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });
    
    bsModal.show();
}

// 写真カルーセルのイベント設定
function setupPhotoCarouselEvents(photos) {
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    
    // 全画面ボタン
    fullscreenBtn.onclick = function() {
        const activeItem = document.querySelector('#photoCarousel .carousel-item.active');
        if (activeItem) {
            const img = activeItem.querySelector('img');
            if (img) {
                showFullscreenImage(img.src);
            }
        }
    };
}

// 全画面画像表示
function showFullscreenImage(src) {
    const overlay = document.getElementById('fullscreenOverlay');
    const fullscreenImg = document.getElementById('fullscreenImage');
    
    fullscreenImg.src = src;
    overlay.style.display = 'flex';
    
    // 閉じるボタン
    document.getElementById('closeFullscreen').onclick = function() {
        overlay.style.display = 'none';
    };
    
    // ESCキーで閉じる
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.style.display === 'flex') {
            overlay.style.display = 'none';
        }
    });
}

// 付近を検索
function searchNearby(lat, lng) {
    // ルートディレクトリから付近検索
    const lang = new URLSearchParams(window.location.search).get('lang') || 'ja';
    const rootUrl = new URL('/', window.location.origin);
    
    // 付近検索用のパラメータを設定
    rootUrl.searchParams.set('lang', lang);
    rootUrl.searchParams.set('lat', lat);
    rootUrl.searchParams.set('lng', lng);
    rootUrl.searchParams.set('radius', '5'); // デフォルト5km
    
    window.location.href = rootUrl.toString();
}

// Mapの中央地点から付近を検索（建築物一覧ページ用）
function searchNearbyFromMapCenter() {
    // LeafletのMapインスタンスを取得
    if (typeof window.mapInstance === 'undefined' || !window.mapInstance) {
        console.error('Map instance not found');
        alert('地図が読み込まれていません。ページを再読み込みしてください。');
        return;
    }
    
    // Mapの中央地点を取得
    const center = window.mapInstance.getCenter();
    const lat = center.lat;
    const lng = center.lng;
    
    console.log('Map center:', lat, lng);
    
    // 付近検索を実行
    searchNearby(lat, lng);
}

// 経路を検索
function getDirections(lat, lng) {
    const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
    window.open(url, '_blank');
}

// グーグルマップで見る（航空写真）
function viewOnGoogleMaps(lat, lng) {
    const url = `https://maps.google.com/?q=${lat},${lng}&t=k`;
    window.open(url, '_blank');
}

// 言語切り替え機能
function initLanguageSwitch() {
    const languageSwitch = document.getElementById('languageSwitch');
    if (languageSwitch) {
        languageSwitch.addEventListener('click', function(e) {
            e.preventDefault();
            
            // 現在のURLを取得
            const currentUrl = new URL(window.location);
            const currentLang = currentUrl.searchParams.get('lang') || 'ja';
            
            // 言語を切り替え
            const newLang = currentLang === 'ja' ? 'en' : 'ja';
            currentUrl.searchParams.set('lang', newLang);
            
            // ページをリロード
            window.location.href = currentUrl.toString();
        });
    }
}

// ページ読み込み時の初期化
document.addEventListener('DOMContentLoaded', function() {
    // 言語切り替え機能の初期化
    initLanguageSwitch();
    
    
    // 建築物データの取得
    const buildingCards = document.querySelectorAll('.building-card');
    
    const buildings = Array.from(buildingCards).map((card, index) => {
        
        const lat = parseFloat(card.dataset.lat);
        const lng = parseFloat(card.dataset.lng);
        
        // 座標が有効な場合のみ建築物データに含める
        if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
            return {
                building_id: card.dataset.buildingId,
                lat: lat,
                lng: lng,
                title: card.dataset.title,
                titleEn: card.dataset['title-en'], // ハイフンを含む属性名は角括弧でアクセス
                location: card.dataset.location,
                locationEn: card.dataset['location-en'], // ハイフンを含む属性名は角括弧でアクセス
                slug: card.dataset.slug,
                popupContent: card.dataset.popupContent // PHPで生成されたポップアップHTML
            };
        }
        return null;
    }).filter(building => building !== null);
    
    
    // 地図の初期化（ページ情報の設定を待つ）
    if (document.getElementById('map')) {
        // ページ情報が設定されるまで少し待つ
        setTimeout(() => {
            if (buildings.length > 0) {
                // 建築物がある場合は、最初の建築物を中心に設定
                const centerLat = buildings[0].lat;
                const centerLng = buildings[0].lng;
                initMap([centerLat, centerLng], buildings);
            } else {
                // 建築物がない場合は東京駅を中心に設定
                initMap([35.6814, 139.7670], []);
            }
        }, 100);
    }
    
    // 検索フォームの送信
    const searchForm = document.querySelector('form[method="GET"]');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const query = this.querySelector('input[name="q"]').value.trim();
            if (!query) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // カードのクリックイベント（buildingのslugを持つページでは無効）
    const isBuildingPage = window.location.pathname.includes('/buildings/') && window.location.pathname.split('/').length > 2;
    
    if (!isBuildingPage) {
        buildingCards.forEach(card => {
            card.addEventListener('click', function(e) {
                if (!e.target.closest('a') && !e.target.closest('button')) {
                    const link = this.querySelector('.card-title a');
                    if (link) {
                        window.location.href = link.href;
                    }
                }
            });
        });
    }
});

// アニメーション用のCSS追加
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.1); opacity: 0.7; }
        100% { transform: scale(1); opacity: 1; }
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// 写真ギャラリーの初期化
function initializePhotoGallery() {
    console.log('Initializing photo gallery...');
    
    // 建築物詳細ページで写真ギャラリーカードが存在する場合
    const galleryCard = document.getElementById('photoGalleryCard');
    if (!galleryCard) {
        console.log('Photo gallery card not found');
        return;
    }
    
    // 現在の建築物のUIDを取得
    const buildingCard = document.querySelector('.building-card');
    if (!buildingCard) {
        console.log('Building card not found');
        return;
    }
    
    const uid = buildingCard.getAttribute('data-uid');
    console.log('Building UID:', uid);
    
    if (!uid) {
        console.log('No UID found');
        return;
    }
    
    // 写真データを取得
    console.log('Fetching photos for UID:', uid);
    fetch(`/api/get-photos.php?uid=${encodeURIComponent(uid)}`)
        .then(response => {
            console.log('API response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('API response data:', data);
            if (data.success && data.photos && data.photos.length > 0) {
                console.log('Photos found:', data.photos.length);
                // 現在の建築物のyoutubeUrlを取得
                const youtubeUrl = buildingCard.getAttribute('data-youtube-url') || null;
                // 写真ギャラリーマネージャーに写真と動画URLを設定
                if (window.photoGalleryManager) {
                    window.photoGalleryManager.setPhotos(data.photos, youtubeUrl);
                }
            } else {
                console.log('No photos found, hiding gallery');
                // 写真がない場合はギャラリーを非表示
                galleryCard.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error loading photos:', error);
            galleryCard.style.display = 'none';
        });
}

// ページ読み込み完了後に写真ギャラリーを初期化
document.addEventListener('DOMContentLoaded', function() {
    // 少し遅延させて他の初期化処理が完了してから実行
    setTimeout(initializePhotoGallery, 500);
});

