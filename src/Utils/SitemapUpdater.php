<?php

/**
 * サイトマップ自動更新ユーティリティ
 */
class SitemapUpdater {
    
    private static $sitemapFile = 'sitemap.xml';
    private static $lastUpdateFile = 'logs/sitemap_last_update.txt';
    
    /**
     * サイトマップの更新が必要かチェック
     */
    public static function needsUpdate($forceUpdate = false) {
        if ($forceUpdate) {
            return true;
        }
        
        // ログディレクトリが存在しない場合は作成
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        
        // 最後の更新時刻をチェック
        if (!file_exists(self::$lastUpdateFile)) {
            return true;
        }
        
        $lastUpdate = file_get_contents(self::$lastUpdateFile);
        $lastUpdateTime = strtotime($lastUpdate);
        $currentTime = time();
        
        // 24時間以上経過している場合は更新
        return ($currentTime - $lastUpdateTime) > 86400; // 24時間 = 86400秒
    }
    
    /**
     * サイトマップを更新
     */
    public static function updateSitemap() {
        try {
            require_once __DIR__ . '/SitemapGenerator.php';
            
            $sitemapGenerator = new SitemapGenerator();
            $filepath = $sitemapGenerator->saveSitemap(self::$sitemapFile);
            
            // 更新時刻を記録
            file_put_contents(self::$lastUpdateFile, date('Y-m-d H:i:s'));
            
            error_log("Sitemap updated successfully: {$filepath}");
            return true;
            
        } catch (Exception $e) {
            error_log("Sitemap update failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 必要に応じてサイトマップを更新
     */
    public static function updateIfNeeded($forceUpdate = false) {
        if (self::needsUpdate($forceUpdate)) {
            return self::updateSitemap();
        }
        return false;
    }
}
