<!-- Pagination -->
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <?php if ($currentPage > 1): ?>
            <li class="page-item">
                <a class="page-link pagination-arrow" 
                   href="/index.php?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>"
                   aria-label="Previous">
                    <i data-lucide="arrow-left" style="width: 21px; height: 21px;"></i>
                </a>
            </li>
        <?php endif; ?>
        
        <?php 
        $paginationRange = getPaginationRange($currentPage, $totalPages);
        // デバッグ情報を追加
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            echo "<!-- Debug: currentPage = $currentPage, totalPages = $totalPages -->";
            echo "<!-- Debug: paginationRange = " . implode(', ', $paginationRange) . " -->";
        }
        
        foreach ($paginationRange as $index => $pageNum): 
            // 「...」の場合は特別処理
            if ($pageNum === '...') {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                continue;
            }
            
            // 型を統一して比較（文字列と数値の比較問題を回避）
            $isActive = (int)$pageNum === (int)$currentPage;
            
            // デバッグ情報を追加
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                echo "<!-- Debug: pageNum = $pageNum (type: " . gettype($pageNum) . "), currentPage = $currentPage (type: " . gettype($currentPage) . "), isActive = " . ($isActive ? 'true' : 'false') . " -->";
            }
        ?>
            <li class="page-item <?php echo $isActive ? 'active' : ''; ?>">
                <?php if ($isActive): ?>
                    <span class="page-link active-page" aria-current="page">
                        <?php echo $pageNum; ?>
                    </span>
                <?php else: ?>
                    <a class="page-link" 
                       href="/index.php?<?php echo http_build_query(array_merge($_GET, ['page' => $pageNum])); ?>"
                       aria-label="Page <?php echo $pageNum; ?>">
                        <?php echo $pageNum; ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        
        <?php if ($currentPage < $totalPages): ?>
            <li class="page-item">
                <a class="page-link pagination-arrow" 
                   href="/index.php?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>"
                   aria-label="Next">
                    <i data-lucide="arrow-right" style="width: 21px; height: 21px;"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>

<div class="text-center text-muted mt-2">
    <?php echo $lang === 'ja' ? 'ページ' : 'Page'; ?> <?php echo $currentPage; ?> / <?php echo $totalPages; ?>
    (<?php echo $totalBuildings; ?> <?php echo $lang === 'ja' ? '件' : 'items'; ?>)
</div>

<style>
/* ページネーション矢印のサイズを数字ボタンと統一 */
nav[aria-label="Page navigation"] .pagination .page-link.pagination-arrow,
nav[aria-label="Page navigation"] .pagination li.page-item .page-link.pagination-arrow,
.pagination .page-link.pagination-arrow,
.pagination li.page-item .page-link.pagination-arrow,
body .container-fluid .pagination .page-link.pagination-arrow,
body .container-fluid .pagination li.page-item .page-link.pagination-arrow {
    padding: 0.75rem 1rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 48px !important; /* 数字ボタンと同じサイズ */
    min-height: 62px !important; /* 1.3倍に拡大（48px × 1.3 ≈ 62px） */
    height: 62px !important; /* 高さを明示的に62pxに設定（1.3倍） */
    box-sizing: border-box !important; /* パディングとボーダーを含めた高さ */
    line-height: 1 !important; /* 行の高さをリセット */
}

/* ページネーションの文字サイズを1.5倍に - より詳細度の高いセレクタで上書き */
nav[aria-label="Page navigation"] .pagination .page-link,
nav[aria-label="Page navigation"] .pagination .page-item.active .page-link,
nav[aria-label="Page navigation"] .pagination .page-item.active span.page-link,
body .container-fluid .pagination .page-link,
body .container-fluid .pagination .page-item.active .page-link,
body .container-fluid .pagination .page-item.active span.page-link,
.pagination .page-link,
.pagination .page-item.active .page-link,
.pagination .page-item.active span.page-link {
    font-size: 1.5rem !important;
}
</style>
