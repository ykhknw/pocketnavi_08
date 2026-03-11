<!-- Search Form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="/index.php" class="row g-3" id="mainSearchForm">
            <input type="hidden" name="lang" value="<?php echo $lang; ?>">
            <?php if (isset($prefectures) && !empty($prefectures)): ?>
                <input type="hidden" name="prefectures" value="<?php echo htmlspecialchars($prefectures); ?>">
            <?php endif; ?>
            <?php if (isset($architectsSlug) && !empty($architectsSlug)): ?>
                <input type="hidden" name="architects_slug" value="<?php echo htmlspecialchars($architectsSlug); ?>">
            <?php endif; ?>
            <?php if (isset($completionYears) && !empty($completionYears)): ?>
                <input type="hidden" name="completionYears" value="<?php echo htmlspecialchars($completionYears); ?>">
            <?php endif; ?>
            
            <div class="col-md-8">
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           id="mainSearchInput"
                           value="<?php echo htmlspecialchars($query); ?>"
                           placeholder="<?php echo t('searchPlaceholder', $lang); ?>">
                    <button type="button" 
                            class="input-group-text search-submit-btn" 
                            id="searchSubmitBtn"
                            aria-label="<?php echo t('search', $lang); ?>"
                            style="cursor: pointer; background-color: transparent; transition: background-color 0.2s;">
                        <i data-lucide="search" style="width: 20px; height: 20px; color: #6c757d;"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="d-flex gap-2">
                    <button type="button" 
                            class="btn btn-outline-primary" 
                            id="getLocationBtn"
                            onclick="getCurrentLocation()">
                        <i data-lucide="locate-fixed" class="me-1" style="width: 16px; height: 16px;"></i>
                        <?php echo t('currentLocation', $lang); ?>
                    </button>
                    
                    <button type="button" 
                            class="btn btn-outline-secondary" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#advancedSearch"
                            aria-expanded="false">
                        <i data-lucide="funnel" class="me-1" style="width: 16px; height: 16px;"></i>
                        <?php echo t('detailedSearch', $lang); ?>
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Advanced Search -->
        <div class="collapse mt-3" id="advancedSearch">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="/index.php" id="advancedSearchForm">
                        <input type="hidden" name="lang" value="<?php echo $lang; ?>">
                        <input type="hidden" name="q" id="advancedSearchQuery" value="<?php echo isset($query) ? htmlspecialchars($query) : ''; ?>">
                        <?php if (isset($architectsSlug) && !empty($architectsSlug)): ?>
                            <input type="hidden" name="architects_slug" value="<?php echo htmlspecialchars($architectsSlug); ?>">
                        <?php endif; ?>
                        <?php if (isset($completionYears) && !empty($completionYears)): ?>
                            <input type="hidden" name="completionYears" value="<?php echo htmlspecialchars($completionYears); ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <select class="form-select" 
                                        name="prefectures" 
                                        id="prefectureSelect">
                                    <option value=""><?php echo $lang === 'ja' ? '都道府県を選択' : 'Select Prefecture'; ?></option>
                                    <?php
                                    // 都道府県マッピング（英語名 => 日本語名）
                                    $prefectureMap = [
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
                                    
                                    $currentPrefecture = isset($prefectures) ? $prefectures : '';
                                    
                                    foreach ($prefectureMap as $enName => $jaName) {
                                        $displayName = $lang === 'ja' ? $jaName : $enName;
                                        $selected = ($currentPrefecture === $enName) ? 'selected' : '';
                                        echo "<option value=\"{$enName}\" {$selected}>{$displayName}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="photos" 
                                                   id="hasPhotos"
                                                   value="1"
                                                   <?php echo $hasPhotos ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="hasPhotos">
                                                <?php echo t('withPhotos', $lang); ?>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="videos" 
                                                   id="hasVideos"
                                                   value="1"
                                                   <?php echo $hasVideos ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="hasVideos">
                                                <?php echo t('withVideos', $lang); ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-lg me-2 px-4">
                                <i data-lucide="search" class="me-1" style="width: 16px; height: 16px;"></i>
                                <?php echo t('search', $lang); ?>
                            </button>
                            
                            <a href="/index.php?lang=<?php echo $lang; ?>" class="btn btn-outline-secondary">
                                <i data-lucide="x" class="me-1" style="width: 16px; height: 16px;"></i>
                                <?php echo t('clearFilters', $lang); ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* 検索ボタンのスタイル */
.search-submit-btn {
    background-color: transparent !important;
    transition: background-color 0.2s ease, color 0.2s ease;
    padding: 0.375rem 0.75rem;
    min-width: 44px; /* タップしやすいサイズ（iOS推奨） */
    border-left: 1px solid #ced4da !important; /* テキストボックスとの間のボーダーを維持 */
    border-right: 1px solid #ced4da !important; /* 右側のボーダーを明示的に設定 */
    border-top: 1px solid #ced4da !important; /* 上側のボーダーを明示的に設定 */
    border-bottom: 1px solid #ced4da !important; /* 下側のボーダーを明示的に設定 */
}

.search-submit-btn:hover {
    background-color: #f8f9fa !important;
}

.search-submit-btn:active {
    background-color: #e9ecef !important;
}

.search-submit-btn i {
    transition: color 0.2s ease, transform 0.2s ease;
}

.search-submit-btn:hover i {
    color: #0d6efd !important;
    transform: scale(1.1);
}

/* 検索入力欄: クリックしてキャレットが左端に来ても左ボーダーを消さない */
#mainSearchInput:focus,
#mainSearchInput:active {
    border-left: 1px solid #ced4da !important;
}

/* フォーカス時のスタイル */
.search-submit-btn:focus {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
    background-color: #f8f9fa !important;
}
</style>

<script>
// メインのinputフォームの値を詳細検索フォームに反映
(function() {
    const mainSearchInput = document.getElementById('mainSearchInput');
    const advancedSearchQuery = document.getElementById('advancedSearchQuery');
    const advancedSearchForm = document.getElementById('advancedSearchForm');
    const mainSearchForm = document.getElementById('mainSearchForm');
    const searchSubmitBtn = document.getElementById('searchSubmitBtn');
    
    // 検索フォーム送信関数
    function submitSearchForm() {
        if (mainSearchForm) {
            // 空の検索クエリの場合は送信しない（オプション）
            const query = mainSearchInput ? mainSearchInput.value.trim() : '';
            // 空でも送信する場合は、このチェックを削除
            // if (!query) {
            //     return;
            // }
            mainSearchForm.submit();
        }
    }
    
    // 検索ボタンのクリックイベント
    if (searchSubmitBtn) {
        searchSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            submitSearchForm();
        });
        
        // タッチデバイスでのタップイベントもサポート
        searchSubmitBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            submitSearchForm();
        });
    }
    
    // Enterキーでの送信（既存機能を維持）
    if (mainSearchInput) {
        mainSearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitSearchForm();
            }
        });
    }
    
    if (mainSearchInput && advancedSearchQuery) {
        // メインのinputフォームの値が変更されたら、詳細検索フォームのhidden inputを更新
        mainSearchInput.addEventListener('input', function() {
            if (advancedSearchQuery) {
                advancedSearchQuery.value = this.value;
            }
        });
        
        // 詳細検索フォームの検索ボタンをクリックした時にも、最新の値を取得
        if (advancedSearchForm) {
            advancedSearchForm.addEventListener('submit', function(e) {
                if (mainSearchInput && advancedSearchQuery) {
                    advancedSearchQuery.value = mainSearchInput.value;
                }
            });
        }
    }
    
    // Lucideアイコンの初期化（検索ボタンのアイコン）
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
})();
</script>

