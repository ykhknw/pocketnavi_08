<?php
/**
 * 建築物コラムセクションコンポーネント
 * 建築物詳細ページでコラムがある場合に表示される（建築物情報カード内）
 */

// 言語別のタイトル/本文（buildings_table_4対応）
$lang = $lang ?? 'ja';
$rawColumnTitle = ($lang === 'en')
    ? ($building['column_titleEn'] ?? '')
    : ($building['column_title'] ?? '');
$rawColumnText = ($lang === 'en')
    ? ($building['building_column_textEn'] ?? '')
    : ($building['building_column_text'] ?? '');

// タイトル表示（未設定時はデフォルト文言）
$columnTitle = !empty($rawColumnTitle)
    ? htmlspecialchars($rawColumnTitle, ENT_QUOTES, 'UTF-8')
    : ($lang === 'ja' ? 'この建築について' : 'About This Building');
?>

<!-- 建築物コラムセクション -->
<div class="column-section" id="buildingColumnCard">
    <!-- 吹き出しラッパー -->
    <div class="speech-bubble-wrapper">
        <!-- タイトル + ロボット（吹き出しの上に表示） -->
        <?php if (!empty($rawColumnTitle)): ?>
            <div class="pattern6-header">
                <div class="catchphrase">
                    <?php echo $columnTitle; ?>
                </div>
                <div class="speaker-robot">
                    <img src="/assets/images/robot01.png" alt="話者">
                </div>
            </div>
        <?php endif; ?>

        <div class="speech-bubble-top-right column-content">
        <?php
        // コラム本文の取得
        $columnText = $rawColumnText;
        
        // Markdown形式: 空行（連続した改行）で段落を分割
        // \r\n（Windows）と\n（Unix）の両方に対応、複数の連続した改行にも対応
        $paragraphs = preg_split('/\r?\n\s*\r?\n/', $columnText);
        $columnTextFormatted = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // エスケープ処理
                $paragraph = htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8');
                // 段落内の改行は<br>に変換
                $paragraph = nl2br($paragraph);
                $columnTextFormatted .= '<p class="column-paragraph">' . $paragraph . '</p>';
            }
        }
        
        // スマホ用: 最初300文字を抽出
        $previewLength = 300;
        $isLongText = mb_strlen($columnText) > $previewLength;
        $previewText = $isLongText ? mb_substr($columnText, 0, $previewLength) : $columnText;
        
        // プレビューテキストも同様に段落処理
        $previewParagraphs = preg_split('/\r?\n\s*\r?\n/', $previewText);
        $previewTextFormatted = '';
        foreach ($previewParagraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // エスケープ処理
                $paragraph = htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8');
                // 段落内の改行は<br>に変換
                $paragraph = nl2br($paragraph);
                $previewTextFormatted .= '<p class="column-paragraph">' . $paragraph . '</p>';
            }
        }
        ?>
        
        <!-- PC/タブレット表示: 全文 -->
        <div class="column-text-full d-none d-md-block">
            <?php echo $columnTextFormatted; ?>
        </div>
        
        <!-- スマホ表示: プレビュー -->
        <div class="column-text-preview d-block d-md-none">
            <?php echo $previewTextFormatted; ?>
            <?php if ($isLongText): ?>
                <span class="text-muted">...</span>
            <?php endif; ?>
        </div>
        
        <!-- スマホ表示: 全文（初期は非表示） -->
        <div class="column-text-full-mobile d-none d-md-none">
            <?php echo $columnTextFormatted; ?>
        </div>
        
        <!-- スマホ用「続きを読む」ボタン -->
        <?php if ($isLongText): ?>
            <div class="d-block d-md-none mt-3">
                <button type="button" 
                        class="btn btn-outline-primary btn-sm read-more-btn" 
                        onclick="toggleColumnText()">
                    <i data-lucide="chevron-down" class="me-1" style="width: 16px; height: 16px;"></i>
                    <?php echo $lang === 'ja' ? '続きを読む' : 'Read More'; ?>
                </button>
                <button type="button" 
                        class="btn btn-outline-secondary btn-sm read-less-btn d-none" 
                        onclick="toggleColumnText()">
                    <i data-lucide="chevron-up" class="me-1" style="width: 16px; height: 16px;"></i>
                    <?php echo $lang === 'ja' ? '閉じる' : 'Close'; ?>
                </button>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* コラムセクションのスタイル（建築物情報カード内） */
.building-card .column-section {
    padding: 0.75rem 1.25rem 1.25rem; /* 上だけ詰めて「タイトル＋ロボット」の上余白を減らす */
    border-top: 1px solid rgba(0, 0, 0, 0.125);
    margin-top: 0;
}

/* キャッチフレーズ（吹き出しの上に表示） */
.building-card .column-section .catchphrase {
    font-weight: bold;
    font-size: 1.125rem;
    color: #333;
    margin-bottom: 0;
}

/* 吹き出しラッパー */
.building-card .column-section .speech-bubble-wrapper {
    position: relative;
    margin: 0;
}

/* タイトル + ロボット（最終案: 近め配置 = パターン6） */
.building-card .column-section .pattern6-header {
    display: flex;
    align-items: flex-end; /* 下端を揃える */
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 14px; /* 吹き出しとの間隔（近め） */
}

.building-card .column-section .pattern6-header .speaker-robot {
    position: relative;
    bottom: auto;
    right: auto;
    width: 60px;
    height: 60px;
    flex-shrink: 0;
    margin-right: 8px; /* 右端から少し左へ（右に寄せるため縮小） */
    top: 6px; /* ほんの少し下へ */
}

.building-card .column-section .pattern6-header .speaker-robot img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

/* 吹き出し本体（右上しっぽ） */
.building-card .column-section .speech-bubble-top-right {
    position: relative;
    background-color: #ffffff;
    border: 2px solid #d0d0d0;
    border-radius: 24px; /* 角丸を2倍に */
    padding: 20px;
    margin-bottom: 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* 吹き出しのしっぽ（内側・白） */
.building-card .column-section .speech-bubble-top-right::before {
    content: '';
    position: absolute;
    top: -20px; /* 吹き出しの上辺から上向き */
    right: 80px; /* ロボットの左側に配置（ロボット幅60px + 余白20px） */
    width: 0;
    height: 0;
    border-left: 30px solid transparent; /* 左側を長く（非対称） */
    border-right: 5px solid transparent; /* 右側をより垂直に */
    border-bottom: 20px solid #ffffff; /* 上向きの三角形 */
    z-index: 2;
}

/* 吹き出しのしっぽ（外側・ボーダー） */
.building-card .column-section .speech-bubble-top-right::after {
    content: '';
    position: absolute;
    top: -22px; /* 吹き出しの上辺から上向き */
    right: 80px; /* ロボットの左側に配置（ロボット幅60px + 余白20px） */
    width: 0;
    height: 0;
    border-left: 32px solid transparent; /* 左側を長く（非対称） */
    border-right: 7px solid transparent; /* 右側をより垂直に */
    border-bottom: 22px solid #d0d0d0; /* 上向きの三角形 */
    z-index: 1;
}

.building-card .column-section .column-content {
    font-size: 1rem; /* 写真ギャラリーカードのh6と同じサイズ */
    color: #6c757d; /* 住所と同じtext-mutedの色 */
    /* 注意: white-space: pre-wrap は使用しない（nl2br()のみ使用） */
}

.building-card .column-section .column-paragraph {
    font-size: 1rem; /* 写真ギャラリーカードのh6と同じサイズ */
    color: #6c757d; /* 住所と同じtext-mutedの色 */
    line-height: 2.3;
    margin-bottom: 1.5rem; /* 段落間の間隔（0.5行分追加） */
}

.building-card .column-section .column-paragraph:last-child {
    margin-bottom: 0; /* 最後の段落は余白なし */
}

/* スマホ表示: 最初300文字のみ */
@media (max-width: 767.98px) {
    .building-card .column-section .pattern6-header {
        gap: 12px;
        margin-bottom: 12px; /* スマホは少し詰める */
    }

    .building-card .column-section .pattern6-header .speaker-robot {
        width: 50px;
        height: 50px;
        margin-right: 6px;
        top: 4px;
    }

    .building-card .column-section .speech-bubble-top-right::before {
        right: 70px; /* スマホ用に調整 */
    }
    
    .building-card .column-section .speech-bubble-top-right::after {
        right: 70px; /* スマホ用に調整 */
    }
    
    .building-card .column-section .column-text-preview {
        display: block;
    }
    
    .building-card .column-section .column-text-full-mobile {
        display: none;
    }
    
    .building-card .column-section.expanded .column-text-preview {
        display: none;
    }
    
    .building-card .column-section.expanded .column-text-full-mobile {
        display: block;
    }
    
    .building-card .column-section.expanded .read-more-btn {
        display: none;
    }
    
    .building-card .column-section.expanded .read-less-btn {
        display: inline-block;
    }
}
</style>

<script>
// スマホ用「続きを読む」機能
function toggleColumnText() {
    const section = document.getElementById('buildingColumnCard');
    if (section) {
        section.classList.toggle('expanded');
        
        // Lucideアイコンの再初期化
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

// ページ読み込み時にLucideアイコンを初期化
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>
