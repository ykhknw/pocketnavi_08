<?php
/**
 * サイトマップ生成スクリプト（管理用）
 * コマンドラインまたはWebから実行可能
 */

// 必要なファイルを読み込み
require_once 'config/database.php';
require_once 'src/Utils/SitemapGenerator.php';

try {
    // サイトマップ生成器を初期化
    $sitemapGenerator = new SitemapGenerator();
    
    // サイトマップを生成してファイルに保存
    $filepath = $sitemapGenerator->saveSitemap('sitemap.xml');
    
    // 成功メッセージ
    if (php_sapi_name() === 'cli') {
        // コマンドライン実行の場合
        echo "Sitemap generated successfully: {$filepath}\n";
        echo "Total URLs: " . substr_count(file_get_contents($filepath), '<url>') . "\n";
    } else {
        // Web実行の場合
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => 'Sitemap generated successfully',
            'filepath' => $filepath,
            'url_count' => substr_count(file_get_contents($filepath), '<url>')
        ]);
    }
    
} catch (Exception $e) {
    // エラーログに記録
    error_log("Sitemap generation error: " . $e->getMessage());
    
    if (php_sapi_name() === 'cli') {
        // コマンドライン実行の場合
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    } else {
        // Web実行の場合
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Sitemap generation failed: ' . $e->getMessage()
        ]);
    }
}
