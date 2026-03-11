<?php
/**
 * XMLサイトマップ生成エンドポイント
 */

// 必要なファイルを読み込み
require_once 'config/database_unified.php';
require_once 'src/Utils/SitemapGenerator.php';

try {
    // サイトマップ生成器を初期化
    $sitemapGenerator = new SitemapGenerator();
    
    // サイトマップを出力
    $sitemapGenerator->outputSitemap();
    
} catch (Exception $e) {
    // エラーログに記録
    error_log("Sitemap generation error: " . $e->getMessage());
    
    // エラーレスポンス
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Sitemap generation failed. Please try again later.";
}
