/**
 * 人気検索ワードモーダル機能
 */

let currentPage = 1;
let currentSearchQuery = '';
let currentSearchType = '';
let currentTab = 'architect'; // 現在のタブ（デフォルトは建築家）

/**
 * モーダルを開いて人気検索ワードを読み込む
 */
function loadPopularSearchesModal() {
    currentPage = 1;
    currentSearchQuery = '';
    currentSearchType = '';
    
    // モーダル内で選択中のタブを検出（デフォルトは'architect'）
    let activeModalTab = document.querySelector('#popularSearchesTabs .nav-link.active');
    
    if (activeModalTab) {
        const tabId = activeModalTab.id;
        if (tabId === 'architect-tab') {
            currentTab = 'architect';
        } else if (tabId === 'building-tab') {
            currentTab = 'building';
        } else if (tabId === 'prefecture-tab') {
            currentTab = 'prefecture';
        } else if (tabId === 'text-tab') {
            currentTab = 'text';
        } else {
            currentTab = 'architect';
        }
    } else {
        // デフォルトは'architect'タブ
        currentTab = 'architect';
    }
    
    // フィルタをリセット
    const searchInput = document.getElementById('searchQueryInput');
    if (searchInput) {
        searchInput.value = '';
    }
    
    // 適切なタブをアクティブにする
    const allTabs = ['architect-tab', 'building-tab', 'prefecture-tab', 'text-tab'];
    allTabs.forEach(tabId => {
        const tab = document.getElementById(tabId);
        if (tab) {
            if (tabId === `${currentTab}-tab`) {
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
            } else {
                tab.classList.remove('active');
                tab.setAttribute('aria-selected', 'false');
            }
        }
    });
    
    // 適切なタブコンテンツを表示する
    const allTabPanes = ['architect-content', 'building-content', 'prefecture-content', 'text-content'];
    allTabPanes.forEach(paneId => {
        const pane = document.getElementById(paneId);
        if (pane) {
            if (paneId === `${currentTab}-content`) {
                pane.classList.add('show', 'active');
            } else {
                pane.classList.remove('show', 'active');
            }
        }
    });
    
    // モーダルが完全に表示されてからデータを読み込み
    setTimeout(() => {
        loadPopularSearchesData();
    }, 100);
}

/**
 * 人気検索ワードデータを読み込む
 */
function loadPopularSearchesData() {
    console.log('loadPopularSearchesData called with currentTab:', currentTab);
    
    // 現在のタブに応じて正しい要素を取得
    const loadingElement = document.getElementById(currentTab + '-loading');
    const contentElement = document.getElementById(currentTab + '-content-area');
    
    console.log('Elements found:', {
        loadingElement: loadingElement,
        contentElement: contentElement,
        loadingElementId: currentTab + '-loading',
        contentElementId: currentTab + '-content-area'
    });
    
    if (!loadingElement || !contentElement) {
        console.error('Loading or content element not found for tab:', currentTab);
        console.error('Loading element:', loadingElement);
        console.error('Content element:', contentElement);
        console.error('Available elements:', {
            'architect-loading': document.getElementById('architect-loading'),
            'architect-content-area': document.getElementById('architect-content-area'),
            'building-loading': document.getElementById('building-loading'),
            'building-content-area': document.getElementById('building-content-area'),
            'prefecture-loading': document.getElementById('prefecture-loading'),
            'prefecture-content-area': document.getElementById('prefecture-content-area'),
            'text-loading': document.getElementById('text-loading'),
            'text-content-area': document.getElementById('text-content-area')
        });
        return;
    }
    
    // ローディング表示
    try {
        loadingElement.style.display = 'block';
    } catch (error) {
        console.error('Error setting loading display:', error);
        return;
    }
    contentElement.innerHTML = '';
    
    // パラメータを構築
    const params = new URLSearchParams({
        page: currentPage,
        limit: 10,
        lang: getCurrentLanguage()
    });
    
    if (currentSearchQuery) {
        params.append('q', currentSearchQuery);
    }
    
    // 現在のタブに応じて検索タイプを設定
    params.append('search_type', currentTab);
    
    
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
    console.log('applySearchFilter called');
    currentPage = 1;
    const searchInput = document.getElementById('searchQueryInput');
    if (searchInput) {
        currentSearchQuery = searchInput.value.trim();
        console.log('Search query:', currentSearchQuery);
    } else {
        currentSearchQuery = '';
        console.log('Search input not found');
    }
    // searchTypeFilterは存在しないので、空文字を設定
    currentSearchType = '';
    console.log('Loading popular searches data...');
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
    const searchSubmitBtn = document.getElementById('popularSearchSubmitBtn');
    
    if (!searchInput) {
        return;
    }
    
    
    // 検索入力のイベントリスナー
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            console.log('Auto search triggered');
            applySearchFilter();
        }, 500); // 500ms後に検索実行
    });
    
    // Enterキーでの即座検索
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            console.log('Enter key pressed, searching immediately');
            clearTimeout(searchTimeout); // タイムアウトをクリア
            applySearchFilter(); // 即座に検索実行
        }
    });
    
    // 検索ボタンのクリックイベント
    if (searchSubmitBtn) {
        searchSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Search button clicked, searching immediately');
            clearTimeout(searchTimeout); // タイムアウトをクリア
            applySearchFilter(); // 即座に検索実行
        });
        
        // タッチデバイスでのタップイベントもサポート
        searchSubmitBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            console.log('Search button tapped, searching immediately');
            clearTimeout(searchTimeout); // タイムアウトをクリア
            applySearchFilter(); // 即座に検索実行
        });
    }
    
}

/**
 * タブ切り替え機能
 */
function switchTab(tabName) {
    currentTab = tabName;
    currentPage = 1; // ページをリセット
    
    // すべてのタブを非アクティブにする
    const allTabs = ['architect-tab', 'building-tab', 'prefecture-tab', 'text-tab'];
    allTabs.forEach(tabId => {
        const tab = document.getElementById(tabId);
        if (tab) {
            tab.classList.remove('active');
            tab.setAttribute('aria-selected', 'false');
        }
    });
    
    // 選択されたタブをアクティブにする
    const activeTab = document.getElementById(tabName + '-tab');
    if (activeTab) {
        activeTab.classList.add('active');
        activeTab.setAttribute('aria-selected', 'true');
    }
    
    // データを読み込み
    loadPopularSearchesData();
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
            // 検索フィルタを再初期化
            initializeSearchFilters();
        });
        
        // モーダルが閉じられるときのイベント
        modal.addEventListener('hidden.bs.modal', function() {
            // 必要に応じてクリーンアップ処理
        });
    }
    
    // タブクリックイベントを設定
    const tabButtons = ['architect-tab', 'building-tab', 'prefecture-tab', 'text-tab'];
    tabButtons.forEach(tabId => {
        const tab = document.getElementById(tabId);
        if (tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const tabName = tabId.replace('-tab', '');
                switchTab(tabName);
            });
        }
    });
}

/**
 * サイドバーの人気検索データを読み込む
 */
function loadSidebarPopularSearches() {
    const container = document.getElementById('sidebar-popular-searches-container');
    const loading = document.getElementById('sidebar-loading');
    
    if (!container || !loading) {
        console.error('Sidebar container or loading element not found');
        return;
    }
    
    // ローディング表示
    loading.style.display = 'block';
    
    // APIを呼び出し
    const deviceLimit = getDeviceLimit();
    const params = new URLSearchParams({
        page: 1,
        limit: deviceLimit,
        lang: getCurrentLanguage()
    });
    
    
    fetch(`/api/popular-searches.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none';
            
            if (data.success) {
                // サイドバー用のHTMLを生成
                const html = generateSidebarHTML(data.data.html, data.data.searches);
                container.innerHTML = html;
                
                // ツールチップを初期化
                initializeSidebarTooltips();
                
                // Lucideアイコンを再初期化
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                
                // 初期表示として「建築家」タブを表示
                filterSidebarSearches('architect');
            } else {
                container.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i data-lucide="alert-circle" class="me-2" style="width: 16px; height: 16px;"></i>
                        ${data.error.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading sidebar popular searches:', error);
            loading.style.display = 'none';
            container.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i data-lucide="alert-circle" class="me-2" style="width: 16px; height: 16px;"></i>
                    ${getCurrentLanguage() === 'ja' ? 'データの読み込みに失敗しました。' : 'Failed to load data.'}
                </div>
            `;
        });
}

/**
 * サイドバー用のHTMLを生成
 */
function generateSidebarHTML(modalHTML, searchesData) {
    const lang = getCurrentLanguage();
    
    // Create a temporary container to parse the HTML
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = modalHTML;
    
    // Process each search item and add data-search-type attribute
    if (searchesData && searchesData.length > 0) {
        searchesData.forEach((search, index) => {
            const searchType = search.search_type || 'text';
            const query = search.query;
            
            // Find the corresponding link element in the parsed HTML
            const links = tempContainer.querySelectorAll('a');
            links.forEach(link => {
                const span = link.querySelector('span');
                if (span && span.textContent.trim() === query.trim()) {
                    link.setAttribute('data-search-type', searchType);
                    link.setAttribute('data-debug-info', JSON.stringify({
                        search_type: searchType,
                        page_type: search.page_type || 'null'
                    }));
                }
            });
        });
    }
    
    // Get the processed HTML and remove pagination
    const processedHTML = removePaginationFromHtml(tempContainer.innerHTML);
    
    return `
        <!-- タブナビゲーション -->
        <ul class="nav nav-tabs nav-fill mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" 
                        id="sidebar-architect-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#sidebar-architect-content" 
                        type="button" 
                        role="tab" 
                        aria-controls="sidebar-architect-content" 
                        aria-selected="true" 
                        onclick="filterSidebarSearches('architect')"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="${lang === 'ja' ? '建築家' : 'Architect'}">
                    <i data-lucide="circle-user-round" style="width: 16px; height: 16px;"></i>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" 
                        id="sidebar-building-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#sidebar-building-content" 
                        type="button" 
                        role="tab" 
                        aria-controls="sidebar-building-content" 
                        aria-selected="false" 
                        onclick="filterSidebarSearches('building')"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="${lang === 'ja' ? '建築物' : 'Building'}">
                    <i data-lucide="building" style="width: 16px; height: 16px;"></i>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" 
                        id="sidebar-prefecture-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#sidebar-prefecture-content" 
                        type="button" 
                        role="tab" 
                        aria-controls="sidebar-prefecture-content" 
                        aria-selected="false" 
                        onclick="filterSidebarSearches('prefecture')"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="${lang === 'ja' ? '都道府県' : 'Prefecture'}">
                    <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" 
                        id="sidebar-text-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#sidebar-text-content" 
                        type="button" 
                        role="tab" 
                        aria-controls="sidebar-text-content" 
                        aria-selected="false" 
                        onclick="filterSidebarSearches('text')"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="${lang === 'ja' ? 'テキスト' : 'Text'}">
                    <i data-lucide="type" style="width: 16px; height: 16px;"></i>
                </button>
            </li>
        </ul>
        
        <!-- タブコンテンツ -->
        <div class="tab-content" id="sidebarPopularSearchesTabContent">
            <!-- 建築家 -->
            <div class="tab-pane fade show active" id="sidebar-architect-content" role="tabpanel" aria-labelledby="sidebar-architect-tab">
                <div class="list-group list-group-flush" id="sidebar-architect-list">
                    <!-- 建築家の検索結果はJavaScriptで動的に表示 -->
                </div>
            </div>
            
            <!-- 建築物 -->
            <div class="tab-pane fade" id="sidebar-building-content" role="tabpanel" aria-labelledby="sidebar-building-tab">
                <div class="list-group list-group-flush" id="sidebar-building-list">
                    <!-- 建築物の検索結果はJavaScriptで動的に表示 -->
                </div>
            </div>
            
            <!-- 都道府県 -->
            <div class="tab-pane fade" id="sidebar-prefecture-content" role="tabpanel" aria-labelledby="sidebar-prefecture-tab">
                <div class="list-group list-group-flush" id="sidebar-prefecture-list">
                    <!-- 都道府県の検索結果はJavaScriptで動的に表示 -->
                </div>
            </div>
            
            <!-- テキスト -->
            <div class="tab-pane fade" id="sidebar-text-content" role="tabpanel" aria-labelledby="sidebar-text-tab">
                <div class="list-group list-group-flush" id="sidebar-text-list">
                    <!-- テキストの検索結果はJavaScriptで動的に表示 -->
                </div>
            </div>
        </div>
    `;
}

// DOM読み込み完了後に初期化
document.addEventListener('DOMContentLoaded', function() {
    initializePopularSearchesModal();
    initializeSearchFilters();
    initializeSidebarTooltips();
    loadSidebarPopularSearches();
    
    // ウィンドウサイズ変更時の処理
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            // サイドバーのデータを再読み込み
            const activeSidebarTab = document.querySelector('#sidebarPopularSearchesTabContent .nav-link.active');
            if (activeSidebarTab) {
                const tabId = activeSidebarTab.id;
                let currentTab = 'architect';
                if (tabId === 'sidebar-architect-tab') {
                    currentTab = 'architect';
                } else if (tabId === 'sidebar-building-tab') {
                    currentTab = 'building';
                } else if (tabId === 'sidebar-prefecture-tab') {
                    currentTab = 'prefecture';
                } else if (tabId === 'sidebar-text-tab') {
                    currentTab = 'text';
                }
                filterSidebarSearches(currentTab);
            }
        }, 250); // 250ms後に実行（リサイズ完了を待つ）
    });
});

/**
 * デバイスサイズに基づいて表示件数を取得
 */
function getDeviceLimit() {
    const width = window.innerWidth;
    
    if (width >= 992) {
        // デスクトップ: 10件
        return 10;
    } else if (width >= 768) {
        // タブレット: 6件
        return 6;
    } else {
        // モバイル: 4件
        return 4;
    }
}

/**
 * HTMLからページネーション部分を削除
 */
function removePaginationFromHtml(html) {
    // 一時的なコンテナを作成してHTMLを解析
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = html;
    
    // ページネーション関連の要素を削除
    const paginationElements = tempContainer.querySelectorAll('.pagination, .pagination-wrapper, nav[aria-label="Page navigation"]');
    paginationElements.forEach(element => {
        element.remove();
    });
    
    // ページネーション関連のクラスを持つ要素も削除
    const paginationClassElements = tempContainer.querySelectorAll('[class*="pagination"]');
    paginationClassElements.forEach(element => {
        if (element.classList.contains('pagination') || 
            element.classList.contains('pagination-wrapper') ||
            element.textContent.includes('前へ') || 
            element.textContent.includes('次へ') ||
            element.textContent.includes('Previous') || 
            element.textContent.includes('Next')) {
            element.remove();
        }
    });
    
    return tempContainer.innerHTML;
}

/**
 * サイドバーの検索結果をフィルタリング
 */
function filterSidebarSearches(searchType) {
    // 各タブのリストを取得
    const architectList = document.getElementById('sidebar-architect-list');
    const buildingList = document.getElementById('sidebar-building-list');
    const prefectureList = document.getElementById('sidebar-prefecture-list');
    const textList = document.getElementById('sidebar-text-list');
    
    // 各リストをクリア
    if (architectList) architectList.innerHTML = '';
    if (buildingList) buildingList.innerHTML = '';
    if (prefectureList) prefectureList.innerHTML = '';
    if (textList) textList.innerHTML = '';
    
    // APIからデータを取得
    const deviceLimit = getDeviceLimit();
    const params = new URLSearchParams({
        page: 1,
        limit: deviceLimit,
        lang: getCurrentLanguage(),
        search_type: searchType
    });
    
    fetch(`/api/popular-searches.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.html) {
                let targetList;
                switch (searchType) {
                    case 'architect':
                        targetList = architectList;
                        break;
                    case 'building':
                        targetList = buildingList;
                        break;
                    case 'prefecture':
                        targetList = prefectureList;
                        break;
                    case 'text':
                        targetList = textList;
                        break;
                }
                
                if (targetList) {
                    // ページネーションを削除してHTMLを設定
                    const cleanHtml = removePaginationFromHtml(data.data.html);
                    targetList.innerHTML = cleanHtml;
                }
            }
        })
        .catch(error => {
            console.error(`Error loading ${searchType} sidebar data:`, error);
        });
    
    // タブのアクティブ状態を更新
    const tabButtons = document.querySelectorAll('#sidebarPopularSearchesTabContent .nav-link');
    tabButtons.forEach(button => {
        button.classList.remove('active');
        button.setAttribute('aria-selected', 'false');
    });
    
    const contentPanes = document.querySelectorAll('#sidebarPopularSearchesTabContent .tab-pane');
    contentPanes.forEach(pane => {
        pane.classList.remove('show', 'active');
    });
    
    // 選択されたタブをアクティブにする
    const activeTab = document.getElementById('sidebar-' + searchType + '-tab');
    const activeContent = document.getElementById('sidebar-' + searchType + '-content');
    
    if (activeTab) {
        activeTab.classList.add('active');
        activeTab.setAttribute('aria-selected', 'true');
    }
    
    if (activeContent) {
        activeContent.classList.add('show', 'active');
    }
    
    // Lucideアイコンを再初期化
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // ツールチップを再初期化
    initializeSidebarTooltips();
}

/**
 * サイドバーのツールチップを初期化
 */
function initializeSidebarTooltips() {
    // 既存のツールチップを破棄
    const existingTooltips = document.querySelectorAll('#sidebarPopularSearchesTabContent .nav-link[data-bs-toggle="tooltip"]');
    existingTooltips.forEach(element => {
        const tooltip = bootstrap.Tooltip.getInstance(element);
        if (tooltip) {
            tooltip.dispose();
        }
    });
    
    // 新しいツールチップを初期化
    const tooltipTriggerList = document.querySelectorAll('#sidebarPopularSearchesTabContent .nav-link[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// グローバル関数として公開
window.loadPopularSearchesModal = loadPopularSearchesModal;
window.loadPopularSearchesPage = loadPopularSearchesPage;
window.applySearchFilter = applySearchFilter;
window.switchTab = switchTab;
window.filterSidebarSearches = filterSidebarSearches;
window.initializeSidebarTooltips = initializeSidebarTooltips;
window.loadSidebarPopularSearches = loadSidebarPopularSearches;
