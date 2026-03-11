/**
 * 人気検索ワードモーダル機能
 */

let currentPage = 1;
let currentSearchQuery = '';
let currentSearchType = '';

/**
 * モーダルを開いて人気検索ワードを読み込む
 */
function loadPopularSearchesModal() {
    currentPage = 1;
    currentSearchQuery = '';
    currentSearchType = '';
    
    // フィルタをリセット
    document.getElementById('searchQueryInput').value = '';
    
    // データを読み込み
    loadPopularSearchesData();
}

/**
 * 人気検索ワードデータを読み込む
 */
function loadPopularSearchesData() {
    const loadingElement = document.getElementById('popularSearchesLoading');
    const contentElement = document.getElementById('popularSearchesContent');
    
    // ローディング表示
    loadingElement.style.display = 'block';
    contentElement.innerHTML = '';
    
    // パラメータを構築
    const params = new URLSearchParams({
        page: currentPage,
        limit: 20,
        lang: getCurrentLanguage()
    });
    
    if (currentSearchQuery) {
        params.append('q', currentSearchQuery);
    }
    
    
    // APIを呼び出し
    fetch(`/api/popular-searches.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            loadingElement.style.display = 'none';
            
            if (data.success) {
                contentElement.innerHTML = data.data.html;
                
                // ページネーションイベントを設定
                setupPaginationEvents();
                
                // 検索ワードクリックイベントを設定
                setupSearchClickEvents();
                
            } else {
                contentElement.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i data-lucide="alert-circle" class="me-2" style="width: 16px; height: 16px;"></i>
                        ${data.error.message}
                    </div>
                `;
            }
            
            // Lucideアイコンを再初期化
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        })
        .catch(error => {
            console.error('Error loading popular searches:', error);
            loadingElement.style.display = 'none';
            contentElement.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i data-lucide="alert-circle" class="me-2" style="width: 16px; height: 16px;"></i>
                    ${getCurrentLanguage() === 'ja' ? 'データの読み込みに失敗しました。' : 'Failed to load data.'}
                </div>
            `;
            
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
}

/**
 * 指定されたページの人気検索ワードを読み込む
 */
function loadPopularSearchesPage(page) {
    currentPage = page;
    loadPopularSearchesData();
}

/**
 * 検索フィルタを適用
 */
function applySearchFilter() {
    currentPage = 1;
    currentSearchQuery = document.getElementById('searchQueryInput').value.trim();
    currentSearchType = document.getElementById('searchTypeFilter').value;
    loadPopularSearchesData();
}

/**
 * ページネーションイベントを設定
 */
function setupPaginationEvents() {
    // ページネーションリンクのクリックイベントは既にHTMLにonclickで設定済み
    // ここでは追加の処理があれば実装
}

/**
 * 検索ワードクリックイベントを設定
 */
function setupSearchClickEvents() {
    const searchLinks = document.querySelectorAll('#popularSearchesContent .list-group-item');
    searchLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // モーダルを閉じる
            const modal = bootstrap.Modal.getInstance(document.getElementById('popularSearchesModal'));
            if (modal) {
                modal.hide();
            }
        });
    });
}

/**
 * 現在の言語を取得
 */
function getCurrentLanguage() {
    // URLパラメータから言語を取得
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('lang') || 'ja';
}

/**
 * 検索フィルタの初期化
 */
function initializeSearchFilters() {
    const searchInput = document.getElementById('searchQueryInput');
    
    if (!searchInput) {
        return;
    }
    
    // 検索入力のイベントリスナー
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            applySearchFilter();
        }, 500); // 500ms後に検索実行
    });
    
}

/**
 * モーダルイベントの初期化
 */
function initializePopularSearchesModal() {
    const modal = document.getElementById('popularSearchesModal');
    
    if (modal) {
        // モーダルが開かれるときのイベント
        modal.addEventListener('show.bs.modal', function() {
            loadPopularSearchesModal();
        });
        
        // モーダルが閉じられるときのイベント
        modal.addEventListener('hidden.bs.modal', function() {
            // 必要に応じてクリーンアップ処理
        });
    }
}

// DOM読み込み完了後に初期化
document.addEventListener('DOMContentLoaded', function() {
    initializePopularSearchesModal();
    initializeSearchFilters();
});

// グローバル関数として公開
window.loadPopularSearchesModal = loadPopularSearchesModal;
window.loadPopularSearchesPage = loadPopularSearchesPage;
window.applySearchFilter = applySearchFilter;
