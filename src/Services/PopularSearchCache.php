<?php

/**
 * 人気検索キャッシュ管理クラス
 */
class PopularSearchCache {
    private const CACHE_FILE = __DIR__ . '/../../cache/popular_searches.php';
    private const LOCK_FILE = __DIR__ . '/../../cache/popular_searches.php.lock';
    private const BACKUP_FILE = __DIR__ . '/../../cache/popular_searches_backup.php';
    private const CACHE_DURATION = 1800; // 30分
    private const MAX_LOCK_WAIT = 10; // 最大ロック待機時間（秒）
    
    // CRON設定により定期更新が行われるため、アクセス時の自動更新を無効化
    private const AUTO_UPDATE_ENABLED = false;
    
    private $searchLogService;
    private $databaseConnection = null;
    
    public function __construct() {
        // キャッシュディレクトリの作成
        $this->ensureCacheDirectory();
    }
    
    /**
     * データベース接続を設定（CRON環境などで使用）
     */
    public function setDatabase($database) {
        $this->databaseConnection = $database;
    }
    
    /**
     * 人気検索データを取得（キャッシュ優先）
     */
    public function getPopularSearches($page = 1, $limit = 20, $searchQuery = '', $searchType = '') {
        $cacheKey = $this->generateCacheKey($page, $limit, $searchQuery, $searchType);
        
        // キャッシュが有効な場合はキャッシュから取得
        if ($this->isCacheValid($cacheKey)) {
            $cachedData = $this->loadFromCache($cacheKey);
            if ($cachedData !== false) {
                return $cachedData;
            }
        }
        
        // 自動更新が無効化されている場合はフォールバックデータを返す
        if (!self::AUTO_UPDATE_ENABLED) {
            return $this->getFallbackData($searchType);
        }
        
        // キャッシュが無効または存在しない場合は更新
        return $this->updateCache($page, $limit, $searchQuery, $searchType);
    }
    
    /**
     * キャッシュが有効かチェック
     */
    private function isCacheValid($cacheKey) {
        if (!file_exists(self::CACHE_FILE)) {
            return false;
        }
        
        $cacheData = include self::CACHE_FILE;
        
        // キャッシュデータの構造チェック
        if (!is_array($cacheData) || !isset($cacheData['data']) || !isset($cacheData['timestamp'])) {
            return false;
        }
        
        // 指定されたキーのデータが存在するかチェック
        if (!isset($cacheData['data'][$cacheKey])) {
            return false;
        }
        
        // 有効期限チェック
        $cacheTime = $cacheData['timestamp'];
        $currentTime = time();
        
        return ($currentTime - $cacheTime) < self::CACHE_DURATION;
    }
    
    /**
     * キャッシュからデータを読み込み
     */
    private function loadFromCache($cacheKey) {
        try {
            $cacheData = include self::CACHE_FILE;
            
            if (isset($cacheData['data'][$cacheKey])) {
                return $cacheData['data'][$cacheKey];
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Cache load error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * キャッシュを強制的に更新（CRON用）
     * AUTO_UPDATE_ENABLED の設定に関係なく、データベースから取得して更新
     * 
     * @param int $page ページ番号
     * @param int $limit 取得件数
     * @param string $searchQuery 検索クエリ（フィルタ用）
     * @param string $searchType 検索タイプ（'architect', 'building', 'prefecture', 'text', ''）
     * @return array 更新されたデータ、またはエラー時はフォールバックデータ
     */
    public function forceUpdateCache($page = 1, $limit = 50, $searchQuery = '', $searchType = '') {
        // updateCache() のロジックを使用（AUTO_UPDATE_ENABLED を無視）
        return $this->updateCache($page, $limit, $searchQuery, $searchType);
    }
    
    /**
     * キャッシュを更新
     */
    protected function updateCache($page, $limit, $searchQuery, $searchType) {
        $lockFile = self::LOCK_FILE;
        $fp = fopen($lockFile, 'w');
        
        if (!$fp) {
            error_log("Failed to create lock file");
            return $this->getFallbackData($searchType);
        }
        
        // 非ブロッキングロックを試行
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            try {
                // データベースからデータを取得
                $data = $this->fetchFromDatabase($page, $limit, $searchQuery, $searchType);
                
                // キャッシュファイルを更新
                $this->saveToCache($page, $limit, $searchQuery, $searchType, $data);
                
                flock($fp, LOCK_UN);
                fclose($fp);
                
                return $data;
                
            } catch (Exception $e) {
                error_log("PopularSearchCache::updateCache error: " . $e->getMessage());
                flock($fp, LOCK_UN);
                fclose($fp);
                return $this->getFallbackData($searchType);
            }
        } else {
            // ロック取得に失敗した場合、既存のキャッシュを返すかフォールバック
            fclose($fp);
            
            // 少し待ってから既存のキャッシュをチェック
            usleep(100000); // 0.1秒待機
            
            $cachedData = $this->loadFromCache($this->generateCacheKey($page, $limit, $searchQuery, $searchType));
            if ($cachedData !== false) {
                return $cachedData;
            }
            
            return $this->getFallbackData($searchType);
        }
    }
    
    /**
     * データベースからデータを取得
     */
    private function fetchFromDatabase($page, $limit, $searchQuery, $searchType) {
        try {
            require_once __DIR__ . '/SearchLogService.php';
            
            // データベース接続が設定されている場合はそれを使用、そうでなければgetDB()を使用
            if ($this->databaseConnection !== null) {
                $searchLogService = new SearchLogService($this->databaseConnection);
            } else {
                $searchLogService = new SearchLogService();
            }
            
            $result = $searchLogService->getPopularSearchesForModal($page, $limit, $searchQuery, $searchType);
            
            // フォールバックデータではなく、実際のデータベース結果を返す
            return $result;
        } catch (Exception $e) {
            error_log("PopularSearchCache::fetchFromDatabase error: " . $e->getMessage());
            // エラーが発生した場合のみフォールバックデータを返す
            return $this->getFallbackData($searchType);
        }
    }
    
    /**
     * キャッシュファイルに保存
     */
    private function saveToCache($page, $limit, $searchQuery, $searchType, $data) {
        $cacheKey = $this->generateCacheKey($page, $limit, $searchQuery, $searchType);
        
        // 既存のキャッシュデータを読み込み
        $existingData = [];
        if (file_exists(self::CACHE_FILE)) {
            $existingData = include self::CACHE_FILE;
            if (!is_array($existingData)) {
                $existingData = [];
            }
        }
        
        // 新しいデータを追加
        $existingData['data'][$cacheKey] = $data;
        $existingData['timestamp'] = time();
        
        // バックアップを作成
        if (file_exists(self::CACHE_FILE)) {
            copy(self::CACHE_FILE, self::BACKUP_FILE);
        }
        
        // キャッシュファイルに書き込み
        $cacheContent = "<?php\nreturn " . var_export($existingData, true) . ";\n";
        
        if (file_put_contents(self::CACHE_FILE, $cacheContent, LOCK_EX) === false) {
            throw new Exception("Failed to write cache file");
        }
    }
    
    /**
     * キャッシュキーを生成
     */
    private function generateCacheKey($page, $limit, $searchQuery, $searchType) {
        return md5($page . '_' . $limit . '_' . $searchQuery . '_' . $searchType);
    }
    
    /**
     * フォールバックデータを取得
     */
    private function getFallbackData($searchType) {
        $sampleData = [
            'all' => [
                ['query' => '安藤忠雄', 'search_type' => 'architect', 'total_searches' => 15, 'unique_users' => 8],
                ['query' => '隈研吾', 'search_type' => 'architect', 'total_searches' => 12, 'unique_users' => 6],
                ['query' => '丹下健三', 'search_type' => 'architect', 'total_searches' => 9, 'unique_users' => 4],
                ['query' => '東京', 'search_type' => 'prefecture', 'total_searches' => 20, 'unique_users' => 10],
                ['query' => '大阪', 'search_type' => 'prefecture', 'total_searches' => 8, 'unique_users' => 4],
                ['query' => '京都', 'search_type' => 'prefecture', 'total_searches' => 6, 'unique_users' => 3],
                ['query' => '国立代々木競技場', 'search_type' => 'building', 'total_searches' => 5, 'unique_users' => 3],
                ['query' => '東京スカイツリー', 'search_type' => 'building', 'total_searches' => 4, 'unique_users' => 2],
                ['query' => '現代建築', 'search_type' => 'text', 'total_searches' => 10, 'unique_users' => 5],
                ['query' => '住宅', 'search_type' => 'text', 'total_searches' => 7, 'unique_users' => 3]
            ],
            'architect' => [
                ['query' => '安藤忠雄', 'search_type' => 'architect', 'total_searches' => 15, 'unique_users' => 8],
                ['query' => '隈研吾', 'search_type' => 'architect', 'total_searches' => 12, 'unique_users' => 6],
                ['query' => '丹下健三', 'search_type' => 'architect', 'total_searches' => 9, 'unique_users' => 4]
            ],
            'prefecture' => [
                ['query' => '東京', 'search_type' => 'prefecture', 'total_searches' => 20, 'unique_users' => 10],
                ['query' => '大阪', 'search_type' => 'prefecture', 'total_searches' => 8, 'unique_users' => 4],
                ['query' => '京都', 'search_type' => 'prefecture', 'total_searches' => 6, 'unique_users' => 3]
            ],
            'building' => [
                ['query' => '国立代々木競技場', 'search_type' => 'building', 'total_searches' => 5, 'unique_users' => 3],
                ['query' => '東京スカイツリー', 'search_type' => 'building', 'total_searches' => 4, 'unique_users' => 2]
            ],
            'text' => [
                ['query' => '現代建築', 'search_type' => 'text', 'total_searches' => 10, 'unique_users' => 5],
                ['query' => '住宅', 'search_type' => 'text', 'total_searches' => 7, 'unique_users' => 3]
            ]
        ];
        
        $searches = $sampleData[$searchType] ?? $sampleData['all'];
        
        return [
            'searches' => $searches,
            'total' => count($searches),
            'page' => 1,
            'limit' => count($searches),
            'totalPages' => 1
        ];
    }
    
    /**
     * キャッシュディレクトリの作成
     */
    private function ensureCacheDirectory() {
        $cacheDir = dirname(self::CACHE_FILE);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }
    
    /**
     * キャッシュをクリア
     */
    public function clearCache() {
        if (file_exists(self::CACHE_FILE)) {
            unlink(self::CACHE_FILE);
        }
        if (file_exists(self::BACKUP_FILE)) {
            unlink(self::BACKUP_FILE);
        }
    }
    
    /**
     * キャッシュの状態を取得
     */
    public function getCacheStatus() {
        if (!file_exists(self::CACHE_FILE)) {
            return ['status' => 'not_exists', 'message' => 'キャッシュファイルが存在しません'];
        }
        
        $cacheData = include self::CACHE_FILE;
        $currentTime = time();
        $cacheTime = $cacheData['timestamp'] ?? 0;
        $age = $currentTime - $cacheTime;
        
        return [
            'status' => $age < self::CACHE_DURATION ? 'valid' : 'expired',
            'age' => $age,
            'max_age' => self::CACHE_DURATION,
            'data_count' => count($cacheData['data'] ?? []),
            'last_update' => date('Y-m-d H:i:s', $cacheTime)
        ];
    }
}
