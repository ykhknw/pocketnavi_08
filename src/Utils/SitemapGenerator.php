<?php

/**
 * XMLサイトマップ生成クラス
 */
class SitemapGenerator {
    
    private $baseUrl;
    private $db;
    
    public function __construct() {
        $this->baseUrl = $this->getBaseUrl();
        $this->db = getDB();
    }
    
    /**
     * ベースURLを取得
     */
    private function getBaseUrl() {
        // コマンドライン実行の場合はデフォルトURLを使用
        if (php_sapi_name() === 'cli') {
            return 'https://kenchikuka.com'; // 本番環境のURLに変更してください
        }
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // ポート番号が含まれている場合は除去
        if (strpos($host, ':') !== false) {
            $host = explode(':', $host)[0];
        }
        
        return $protocol . '://' . $host;
    }
    
    /**
     * XMLサイトマップを生成
     */
    public function generateSitemap() {
        $urls = [];
        
        // 静的ページを追加
        $urls = array_merge($urls, $this->getStaticPages());
        
        // 建築物ページを追加
        $urls = array_merge($urls, $this->getBuildingPages());
        
        // 建築家ページを追加
        $urls = array_merge($urls, $this->getArchitectPages());
        
        return $this->generateXML($urls);
    }
    
    /**
     * 静的ページのURLを取得
     */
    private function getStaticPages() {
        $urls = [];
        
        // ホームページ（日本語・英語）
        $urls[] = [
            'loc' => $this->baseUrl . '/',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'daily',
            'priority' => '1.0'
        ];
        
        $urls[] = [
            'loc' => $this->baseUrl . '/?lang=en',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'daily',
            'priority' => '0.9'
        ];
        
        // Aboutページ（日本語・英語）
        $urls[] = [
            'loc' => $this->baseUrl . '/about.php',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'monthly',
            'priority' => '0.6'
        ];
        
        $urls[] = [
            'loc' => $this->baseUrl . '/about.php?lang=en',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'monthly',
            'priority' => '0.6'
        ];
        
        // Contactページ（日本語・英語）
        $urls[] = [
            'loc' => $this->baseUrl . '/contact.php',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'monthly',
            'priority' => '0.5'
        ];
        
        $urls[] = [
            'loc' => $this->baseUrl . '/contact.php?lang=en',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'monthly',
            'priority' => '0.5'
        ];
        
        return $urls;
    }
    
    /**
     * 建築物ページのURLを取得
     */
    private function getBuildingPages() {
        $urls = [];
        
        try {
            $sql = "
                SELECT slug, updated_at, created_at
                FROM buildings_table_3 
                WHERE slug IS NOT NULL AND slug != ''
                ORDER BY updated_at DESC, created_at DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $buildings = $stmt->fetchAll();
            
            foreach ($buildings as $building) {
                $lastmod = $building['updated_at'] ?: $building['created_at'];
                $lastmod = date('Y-m-d', strtotime($lastmod));
                
                // 日本語版
                $urls[] = [
                    'loc' => $this->baseUrl . "/buildings/{$building['slug']}/",
                    'lastmod' => $lastmod,
                    'changefreq' => 'weekly',
                    'priority' => '0.8'
                ];
                
                // 英語版
                $urls[] = [
                    'loc' => $this->baseUrl . "/buildings/{$building['slug']}/?lang=en",
                    'lastmod' => $lastmod,
                    'changefreq' => 'weekly',
                    'priority' => '0.7'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Building pages sitemap error: " . $e->getMessage());
        }
        
        return $urls;
    }
    
    /**
     * 建築家ページのURLを取得
     */
    private function getArchitectPages() {
        $urls = [];
        
        try {
            $sql = "
                SELECT slug, updated_at, created_at
                FROM individual_architects_3 
                WHERE slug IS NOT NULL AND slug != ''
                ORDER BY updated_at DESC, created_at DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $architects = $stmt->fetchAll();
            
            foreach ($architects as $architect) {
                $lastmod = $architect['updated_at'] ?: $architect['created_at'];
                $lastmod = date('Y-m-d', strtotime($lastmod));
                
                // 日本語版
                $urls[] = [
                    'loc' => $this->baseUrl . "/architects/{$architect['slug']}/",
                    'lastmod' => $lastmod,
                    'changefreq' => 'weekly',
                    'priority' => '0.8'
                ];
                
                // 英語版
                $urls[] = [
                    'loc' => $this->baseUrl . "/architects/{$architect['slug']}/?lang=en",
                    'lastmod' => $lastmod,
                    'changefreq' => 'weekly',
                    'priority' => '0.7'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Architect pages sitemap error: " . $e->getMessage());
        }
        
        return $urls;
    }
    
    /**
     * XMLを生成
     */
    private function generateXML($urls) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    /**
     * サイトマップをファイルに保存
     */
    public function saveSitemap($filename = 'sitemap.xml') {
        $xml = $this->generateSitemap();
        $filepath = __DIR__ . '/../../' . $filename;
        
        if (file_put_contents($filepath, $xml) === false) {
            throw new Exception("Failed to save sitemap to {$filepath}");
        }
        
        return $filepath;
    }
    
    /**
     * サイトマップを出力（HTTPヘッダー付き）
     */
    public function outputSitemap() {
        $xml = $this->generateSitemap();
        
        // HTTPヘッダーを設定
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Length: ' . strlen($xml));
        
        echo $xml;
    }
}
