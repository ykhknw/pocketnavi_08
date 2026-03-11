<?php
/**
 * 検索結果キャッシュシステム
 * 既存の検索機能をそのまま使用し、内部でキャッシュを追加
 */

class SearchResultCache {
    private $cacheDir;
    private $defaultTTL;
    private $enabled;
    
    public function __construct($cacheDir = 'cache/search', $defaultTTL = 3600, $enabled = true) {
        $this->cacheDir = $cacheDir;
        $this->defaultTTL = $defaultTTL;
        $this->enabled = $enabled;
        
        // キャッシュディレクトリの作成
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * キャッシュキーの生成
     */
    private function generateCacheKey($params) {
        // パラメータを正規化してキャッシュキーを生成
        $normalizedParams = $this->normalizeParams($params);
        return md5(json_encode($normalizedParams));
    }
    
    /**
     * パラメータの正規化
     */
    private function normalizeParams($params) {
        // パラメータを正規化（順序を統一、空文字を除去など）
        $normalized = [];
        
        // 検索クエリ
        $normalized['query'] = trim($params['query'] ?? '');
        
        // ページ番号
        $normalized['page'] = (int)($params['page'] ?? 1);
        
        // 言語
        $normalized['lang'] = $params['lang'] ?? 'ja';
        
        // 制限数
        $normalized['limit'] = (int)($params['limit'] ?? 10);
        
        // フィルター条件
        $normalized['hasPhotos'] = (bool)($params['hasPhotos'] ?? false);
        $normalized['hasVideos'] = (bool)($params['hasVideos'] ?? false);
        
        // 完成年
        $normalized['completionYears'] = $params['completionYears'] ?? '';
        
        // 都道府県
        $normalized['prefectures'] = $params['prefectures'] ?? '';
        
        // 建築種別
        $normalized['buildingTypes'] = $params['buildingTypes'] ?? '';
        
        // 位置情報
        if (isset($params['userLat']) && isset($params['userLng'])) {
            $normalized['userLat'] = (float)$params['userLat'];
            $normalized['userLng'] = (float)$params['userLng'];
            $normalized['radiusKm'] = (int)($params['radiusKm'] ?? 5);
        }
        
        // スラッグ
        if (isset($params['buildingSlug'])) {
            $normalized['buildingSlug'] = $params['buildingSlug'];
        }
        
        if (isset($params['architectSlug'])) {
            $normalized['architectSlug'] = $params['architectSlug'];
        }
        
        return $normalized;
    }
    
    /**
     * キャッシュから取得
     */
    public function get($params) {
        if (!$this->enabled) {
            return null;
        }
        
        $cacheKey = $this->generateCacheKey($params);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = file_get_contents($cacheFile);
        if ($cacheData === false) {
            return null;
        }
        
        $data = json_decode($cacheData, true);
        if ($data === null) {
            return null;
        }
        
        // TTLチェック
        if (isset($data['expires']) && $data['expires'] < time()) {
            unlink($cacheFile);
            return null;
        }
        
        // キャッシュヒット情報を追加
        $result = $data['result'] ?? null;
        if ($result !== null) {
            // 既存の_cache_infoを保持しつつ、キャッシュヒット情報で更新
            $existingCacheInfo = $result['_cache_info'] ?? [];
            $result['_cache_info'] = array_merge($existingCacheInfo, [
                'hit' => true,
                'created' => $data['created'] ?? time(),
                'expires' => $data['expires'] ?? time(),
                'age' => time() - ($data['created'] ?? time())
            ]);
        }
        
        return $result;
    }
    
    /**
     * キャッシュに保存
     */
    public function set($params, $result, $ttl = null) {
        if (!$this->enabled) {
            // キャッシュが無効でも、キャッシュミス情報を追加
            if (is_array($result)) {
                $result['_cache_info'] = [
                    'hit' => false,
                    'reason' => 'cache_disabled',
                    'created' => time(),
                    'expires' => time()
                ];
            }
            return false;
        }
        
        $ttl = $ttl ?? $this->defaultTTL;
        $cacheKey = $this->generateCacheKey($params);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';
        
        // キャッシュミス情報を追加
        if (is_array($result)) {
            $result['_cache_info'] = [
                'hit' => false,
                'reason' => 'cache_miss',
                'created' => time(),
                'expires' => time() + $ttl
            ];
        }
        
        $cacheData = [
            'result' => $result,
            'expires' => time() + $ttl,
            'created' => time(),
            'params' => $this->normalizeParams($params)
        ];
        
        $jsonData = json_encode($cacheData, JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            return false;
        }
        
        return file_put_contents($cacheFile, $jsonData) !== false;
    }
    
    /**
     * キャッシュの削除
     */
    public function delete($params) {
        $cacheKey = $this->generateCacheKey($params);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }
    
    /**
     * キャッシュのクリア
     */
    public function clear() {
        $files = glob($this->cacheDir . '/*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * キャッシュの統計情報
     */
    public function getStats() {
        $files = glob($this->cacheDir . '/*.cache');
        $totalFiles = count($files);
        $totalSize = 0;
        $expiredFiles = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            
            $cacheData = file_get_contents($file);
            if ($cacheData !== false) {
                $data = json_decode($cacheData, true);
                if ($data && isset($data['expires']) && $data['expires'] < time()) {
                    $expiredFiles++;
                }
            }
        }
        
        return [
            'totalFiles' => $totalFiles,
            'totalSize' => $totalSize,
            'expiredFiles' => $expiredFiles,
            'enabled' => $this->enabled
        ];
    }
    
    /**
     * キャッシュの有効/無効切り替え
     */
    public function setEnabled($enabled) {
        $this->enabled = $enabled;
    }
    
    /**
     * キャッシュが有効かどうか
     */
    public function isEnabled() {
        return $this->enabled;
    }
}
