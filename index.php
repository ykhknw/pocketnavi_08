<?php
// タイムアウト設定を短縮（外部リソースへの接続タイムアウト）
ini_set('default_socket_timeout', 10);

// === 詳細パフォーマンス測定 ===
$_perf = ['start' => microtime(true), 'points' => []];
function markTime($label) {
    global $_perf;
    $_perf['points'][] = [
        'label' => $label,
        'time' => microtime(true),
        'elapsed' => round((microtime(true) - $_perf['start']) * 1000, 2)
    ];
}
markTime('Start');

// 既存のコードはここから続く


/**
 * PocketNavi PHP版 - セキュリティ強化版
 * Phase 1: 緊急対応セキュリティ強化
 */

// 本番環境でのログ設定
//$isProduction = !isset($_GET['debug']); // デバッグモードでない場合は本番環境
//define('DEBUG_MODE', isset($_GET['debug'])); // デバッグパラメータがある場合はデバッグモード
// 変更後
$isProduction = true; // 常に本番モード
define('DEBUG_MODE', false); // デバッグ無効

// 統合されたセキュリティヘッダーの設定
if (file_exists(__DIR__ . '/src/Security/UnifiedSecurityHeaders.php')) {
    require_once __DIR__ . '/src/Security/UnifiedSecurityHeaders.php';
    $environment = $isProduction ? 'production' : 'development';
    $securityHeaders = new UnifiedSecurityHeaders($environment);
    $securityHeaders->sendHeaders();
} elseif (file_exists(__DIR__ . '/src/Security/SecurityHeaders.php')) {
    // フォールバック: 従来のSecurityHeadersクラス
    require_once __DIR__ . '/src/Security/SecurityHeaders.php';
    $securityHeaders = new SecurityHeaders();
    
    // 本番環境では本番モード、開発環境では開発モード
    if ($isProduction) {
        $securityHeaders->setProductionMode();
    } else {
        $securityHeaders->setDevelopmentMode();
    }
    
    $securityHeaders->sendHeaders();
}
markTime('After Security Headers');

// セキュアエラーハンドリングの設定（一時的に無効化）
if (file_exists(__DIR__ . '/src/Security/SecureErrorHandler.php') && false) {
    require_once __DIR__ . '/src/Security/SecureErrorHandler.php';
    $errorHandler = new SecureErrorHandler($isProduction);
}

// ============================================================================
// 環境変数と.envファイルの読み込み
// ============================================================================

// .envファイルの読み込み（簡易版）
function loadEnvFile($filePath = '.env') {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // コメント行をスキップ
        }
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // クォートを削除
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
            
            // DB_NAMEをDB_DATABASEとしても設定（互換性のため）
            if ($name === 'DB_NAME' && !array_key_exists('DB_DATABASE', $_ENV)) {
                $_ENV['DB_DATABASE'] = $value;
                putenv("DB_DATABASE=$value");
            }
        }
    }
    return true;
}

// .envファイルの読み込みを試行
$envLoaded = loadEnvFile('.env') || loadEnvFile('../.env') || loadEnvFile('../../.env');

if ($envLoaded) {
    if (!$isProduction) {
        error_log("Environment variables loaded from .env file");
        // デバッグ: 読み込まれた環境変数を確認
        error_log("Debug - Loaded DB_DATABASE from .env: " . ($_ENV['DB_DATABASE'] ?? 'not_found'));
        error_log("Debug - Loaded DB_NAME from .env: " . ($_ENV['DB_NAME'] ?? 'not_found'));
        
        // デバッグ: .envファイルの内容を確認
        if (file_exists('.env')) {
            $envContent = file_get_contents('.env');
            error_log("Debug - .env file content (first 200 chars): " . substr($envContent, 0, 200));
        }
    }
} else {
    error_log("No .env file found, using system environment variables");
}
markTime('After Env Load');

// ============================================================================
// データベース接続の設定
// ============================================================================

// 環境変数からデータベース設定を取得（修正版）
$dbConfig = [
    'host' => !empty($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : (!empty(getenv('DB_HOST')) ? getenv('DB_HOST') : 'localhost'),
    'port' => !empty($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : (!empty(getenv('DB_PORT')) ? getenv('DB_PORT') : '3306'),
    'database' => !empty($_ENV['DB_DATABASE']) ? $_ENV['DB_DATABASE'] : (!empty(getenv('DB_DATABASE')) ? getenv('DB_DATABASE') : '_shinkenchiku_02'),
    'username' => !empty($_ENV['DB_USERNAME']) ? $_ENV['DB_USERNAME'] : (!empty(getenv('DB_USERNAME')) ? getenv('DB_USERNAME') : 'root'),
    'password' => !empty($_ENV['DB_PASSWORD']) ? $_ENV['DB_PASSWORD'] : (!empty(getenv('DB_PASSWORD')) ? getenv('DB_PASSWORD') : ''),
    'charset' => !empty($_ENV['DB_CHARSET']) ? $_ENV['DB_CHARSET'] : (!empty(getenv('DB_CHARSET')) ? getenv('DB_CHARSET') : 'utf8')
];

// デバッグ: 環境変数の値をログ出力（開発環境のみ）
if (!$isProduction) {
    error_log("Debug - DB_HOST: " . ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'not_set'));
    error_log("Debug - DB_DATABASE: " . ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?? 'not_set'));
    error_log("Debug - DB_USERNAME: " . ($_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?? 'not_set'));
    error_log("Debug - Final dbConfig: " . json_encode($dbConfig));
}

// データベース接続関数（既存のfunctions.phpより先に定義）
function getDB() {
    global $dbConfig, $isProduction;
    
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
            ];
            
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
            if (!$isProduction) {
                error_log("Database connected successfully to: {$dbConfig['host']}:{$dbConfig['port']}/{$dbConfig['database']}");
            }
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("データベース接続に失敗しました: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// データベース接続を事前に確立し、グローバル変数として保持
try {
    $GLOBALS['pocketnavi_db_connection'] = getDB();
    if (!$isProduction) {
        error_log("Pre-connection test successful with database: " . $dbConfig['database']);
    }
} catch (Exception $e) {
    error_log("Pre-connection test failed: " . $e->getMessage());
}
markTime('After DB Connection');

// ============================================================================
// 既存システムとの互換性を保つための設定
// ============================================================================

// セキュリティヘッダーの設定
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
}

// 既存のfunctions.phpを読み込み
$functionsLoaded = false;
try {
    require_once 'src/Views/includes/functions.php';
    require_once 'src/Utils/CSRFHelper.php';
    require_once 'src/Utils/SameSiteCookieHelper.php';
    $functionsLoaded = true;
    if (!$isProduction) {
        error_log("Functions.php loaded successfully");
    }
    
    // SameSite Cookie設定を初期化
    startSecureSession();
    
    // functions.php読み込み後、確実に正しいデータベース接続を使用
    if (isset($GLOBALS['pocketnavi_db_connection'])) {
        // グローバル接続を再設定
        $GLOBALS['pocketnavi_db_connection'] = getDB();
        if (!$isProduction) {
            error_log("Database connection re-established after functions.php load");
        }
    }
    
    // データベース接続の最終確認
    try {
        $finalTest = getDB();
        $dbName = $finalTest->query("SELECT DATABASE()")->fetchColumn();
        if (!$isProduction) {
            error_log("Final database connection test - Current database: " . $dbName);
        }
    } catch (Exception $e) {
        error_log("Final database connection test failed: " . $e->getMessage());
    }
    markTime('After Functions Load');
    
} catch (Exception $e) {
    error_log("Functions.php loading failed: " . $e->getMessage());
}

// functions.phpが読み込めない場合のフォールバック関数
if (!$functionsLoaded) {
    function getPopularSearches($lang = 'ja') {
        return [];
    }
    
    function searchBuildingsWithMultipleConditions($query, $completionYears, $prefectures, $buildingTypes, $hasPhotos, $hasVideos, $page, $lang, $limit) {
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    }
    
    function getBuildingBySlug($slug, $lang) {
        return null;
    }
    
    function searchBuildingsByArchitectSlug($slug, $page, $lang, $limit, $completionYears, $prefectures, $query) {
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page,
            'architectInfo' => null
        ];
    }
    
    function searchBuildingsByLocation($lat, $lng, $radius, $page, $hasPhotos, $hasVideos, $lang, $limit) {
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    }
}

// ============================================================================
// 安全版リファクタリングメイン処理クラス
// ============================================================================

class PocketNaviSafeApp {
    
    private $lang;
    private $searchParams;
    private $searchResult;
    private $popularSearches;
    private $debugMode;
    private $cacheEnabled;
    private $cachedBuildingService;
    
    public function __construct() {

        global $_perf;  // 追加

        markTime('App: Constructor Start');
        
        $this->debugMode = isset($_GET['debug']) && ($_GET['debug'] === '1' || $_GET['debug'] === 'true');
        $this->cacheEnabled = isset($_GET['cache']) ? $_GET['cache'] === '1' : true; // デフォルトでキャッシュ有効
        
        // キャッシュ状態をログに出力
        error_log("Cache enabled: " . ($this->cacheEnabled ? 'true' : 'false') . " (cache parameter: " . ($_GET['cache'] ?? 'not_set') . ")");

        // デバッグモードの確認（ログ出力）
        if ($this->debugMode) {
            error_log("Debug mode activated via URL parameter: " . ($_GET['debug'] ?? 'not_set'));
        }
        
        markTime('App: Before Cache Service Init');
        
        // キャッシュ機能付きサービスを初期化
        try {
            require_once 'src/Services/CachedBuildingService.php';
            $this->cachedBuildingService = new CachedBuildingService($this->cacheEnabled, 3600);

            // デバッグ情報を追加
            if ($this->debugMode) {
                error_log("CachedBuildingService initialized successfully. Cache enabled: " . ($this->cacheEnabled ? 'true' : 'false'));
            }
        } catch (Exception $e) {
            error_log("CachedBuildingService initialization failed: " . $e->getMessage());
            if ($this->debugMode) {
                error_log("Debug - Cache service initialization error: " . $e->getTraceAsString());
            }
            $this->cachedBuildingService = null;
        }
        
        markTime('App: After Cache Service Init');
        
        markTime('App: Before Initialize Search Params');
        $this->initializeSearchParameters();
        markTime('App: After Initialize Search Params');

        markTime('App: Before Perform Search');
        $this->performSearch();
        markTime('App: After Perform Search');

        markTime('App: Before Get Popular Searches');
        $this->getPopularSearches();
        markTime('App: After Get Popular Searches');
        
        markTime('App: Constructor End');


    }
    
    /**
     * 検索パラメータの初期化
     */
    private function initializeSearchParameters() {
        $this->lang = $this->validateLanguage($_GET['lang'] ?? 'ja');
        
        $this->searchParams = [
            'query' => $this->validateSearchQuery($_GET['q'] ?? ''),
            'page' => $this->validatePage($_GET['page'] ?? 1),
            'hasPhotos' => isset($_GET['photos']) && $_GET['photos'] !== '',
            'hasVideos' => isset($_GET['videos']) && $_GET['videos'] !== '',
            'userLat' => $this->validateFloat($_GET['lat'] ?? null),
            'userLng' => $this->validateFloat($_GET['lng'] ?? null),
            'radiusKm' => $this->validateInteger($_GET['radius'] ?? 5, 1, 100),
            'buildingSlug' => $this->validateSlug($_GET['building_slug'] ?? ''),
            'prefectures' => $this->validateString($_GET['prefectures'] ?? '', 50),
            'architectsSlug' => $this->validateSlug($_GET['architects_slug'] ?? ''),
            'completionYears' => $this->validateString($_GET['completionYears'] ?? '', 50)
        ];
    }
    
    /**
     * バリデーション関数
     */
    private function validateLanguage($lang) {
        return in_array($lang, ['ja', 'en']) ? $lang : 'ja';
    }
    
    private function validateSearchQuery($query) {
        return htmlspecialchars(trim($query), ENT_QUOTES, 'UTF-8');
    }
    
    private function validatePage($page) {
        $page = (int)$page;
        return $page > 0 ? $page : 1;
    }
    
    private function validateFloat($value) {
        return $value !== null ? (float)$value : null;
    }
    
    private function validateInteger($value, $min = null, $max = null) {
        $value = (int)$value;
        if ($min !== null && $value < $min) return $min;
        if ($max !== null && $value > $max) return $max;
        return $value;
    }
    
    private function validateSlug($slug) {
        return preg_match('/^[a-zA-Z0-9\-_]+$/', $slug) ? $slug : '';
    }
    
    private function validateString($string, $maxLength = 255) {
        $string = htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
        return strlen($string) > $maxLength ? substr($string, 0, $maxLength) : $string;
    }
    
    /**
     * 検索の実行
     */
    private function performSearch() {
        $limit = 10;
        
        // パフォーマンス測定開始
        $startTime = microtime(true);
        
        try {
            if ($this->searchParams['buildingSlug']) {
                $this->searchResult = $this->searchByBuildingSlug($limit);
            } elseif ($this->searchParams['architectsSlug']) {
                $this->searchResult = $this->searchByArchitectSlug($limit);
            } elseif ($this->searchParams['userLat'] !== null && $this->searchParams['userLng'] !== null) {
                $this->searchResult = $this->searchByLocation($limit);
            } else {
                // フィルターなしの全件検索をチェック
                $hasFilters = !empty($this->searchParams['query']) || 
                             !empty($this->searchParams['prefectures']) || 
                             !empty($this->searchParams['completionYears']) ||
                             $this->searchParams['hasPhotos'] || 
                             $this->searchParams['hasVideos'];
                
                // フィルターなしの場合は、IDが大きい順に建築物を表示（ページネーション対応）
                if (!$hasFilters) {
                    // トップページ用：IDが小さい順に建築物を取得
                    if ($this->cachedBuildingService) {
                        // BuildingServiceを直接使用してID順に取得
                        require_once __DIR__ . '/src/Services/BuildingService.php';
                        $buildingService = new BuildingService();
                        $this->searchResult = $buildingService->getBuildingsOrderedById($this->searchParams['page'], $this->lang, $limit);
                        $this->searchResult['isHomePage'] = true;
                    } else {
                        // フォールバック：空の結果を返す
                        $this->searchResult = [
                            'buildings' => [],
                            'total' => 0,
                            'totalPages' => 0,
                            'currentPage' => 1,
                            'isHomePage' => true
                        ];
                    }
                } else {
                    $this->searchResult = $this->searchWithMultipleConditions($limit);
                }
            }
        } catch (Exception $e) {
            error_log("Search error: " . $e->getMessage());
            $this->searchResult = [
                'buildings' => [],
                'total' => 0,
                'totalPages' => 0,
                'currentPage' => 1
            ];
        }

        
        // パフォーマンス測定終了
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2); // ミリ秒
        
        // 実行時間をキャッシュ情報に追加
        if (isset($this->searchResult['_cache_info'])) {
            $this->searchResult['_cache_info']['execution_time_ms'] = $executionTime;
        } else {
            $this->searchResult['_cache_info'] = [
                'hit' => false,
                'reason' => 'no_cache_info',
                'execution_time_ms' => $executionTime,
                'created' => time(),
                'expires' => time()
            ];
        }
    }
    
    /**
     * 建築物スラッグによる検索
     */
    private function searchByBuildingSlug($limit) {
        if ($this->cachedBuildingService) {
            $currentBuilding = $this->cachedBuildingService->getBySlug($this->searchParams['buildingSlug'], $this->lang);
        } else {
            // フォールバック: 既存の関数を使用
            $currentBuilding = getBuildingBySlug($this->searchParams['buildingSlug'], $this->lang);
        }
        
        if ($currentBuilding) {
            // 建築物ページ閲覧ログを記録
            try {
                require_once __DIR__ . '/src/Services/SearchLogService.php';
                $searchLogService = new SearchLogService();
                $searchLogService->logPageView('building', $this->searchParams['buildingSlug'], $currentBuilding['title'] ?? $currentBuilding['titleEn'] ?? $this->searchParams['buildingSlug'], [
                    'building_id' => $currentBuilding['building_id'] ?? null,
                    'lang' => $this->lang
                ]);
            } catch (Exception $e) {
                error_log("Building page view log error: " . $e->getMessage());
            }
            
            return [
                'buildings' => [$currentBuilding],
                'total' => 1,
                'totalPages' => 1,
                'currentPage' => 1,
                'currentBuilding' => $currentBuilding
            ];
        }
        
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => 1,
            'currentBuilding' => null
        ];
    }
    
    /**
     * 建築家スラッグによる検索
     */
    private function searchByArchitectSlug($limit) {
        $searchResult = null;
        
        if ($this->cachedBuildingService) {
            $searchResult = $this->cachedBuildingService->searchByArchitectSlug(
                $this->searchParams['architectsSlug'], 
                $this->searchParams['page'], 
                $this->lang, 
                $limit,
                $this->searchParams['completionYears'], 
                $this->searchParams['prefectures'], 
                $this->searchParams['query']
            );
        } else {
            // フォールバック: 既存の関数を使用
            $searchResult = searchBuildingsByArchitectSlug(
                $this->searchParams['architectsSlug'], 
                $this->searchParams['page'], 
                $this->lang, 
                $limit, 
                $this->searchParams['completionYears'], 
                $this->searchParams['prefectures'], 
                $this->searchParams['query']
            );
        }
        
        // CachedBuildingService経由の場合はログ記録処理が実行されないため、ここで記録
        if ($this->cachedBuildingService && $searchResult && isset($searchResult['architectInfo']) && $searchResult['architectInfo']) {
            try {
                require_once __DIR__ . '/src/Services/SearchLogService.php';
                $searchLogService = new SearchLogService();
                $architectInfo = $searchResult['architectInfo'];
                $searchLogService->logPageView('architect', $this->searchParams['architectsSlug'], $architectInfo['name_ja'] ?? $architectInfo['name_en'] ?? $this->searchParams['architectsSlug'], [
                    'architect_id' => $architectInfo['individual_architect_id'] ?? null,
                    'lang' => $this->lang
                ]);
            } catch (Exception $e) {
                error_log("Architect page view log error: " . $e->getMessage());
            }
        }
        
        return $searchResult;
    }
    
    /**
     * 位置情報による検索
     */
    private function searchByLocation($limit) {
        if ($this->cachedBuildingService) {
            return $this->cachedBuildingService->searchByLocation(
                $this->searchParams['userLat'], 
                $this->searchParams['userLng'], 
                $this->searchParams['radiusKm'], 
                $this->searchParams['page'], 
                $this->searchParams['hasPhotos'], 
                $this->searchParams['hasVideos'], 
                $this->lang, 
                $limit
            );
        } else {
            // フォールバック: 既存の関数を使用
            return searchBuildingsByLocation(
                $this->searchParams['userLat'], 
                $this->searchParams['userLng'], 
                $this->searchParams['radiusKm'], 
                $this->searchParams['page'], 
                $this->searchParams['hasPhotos'], 
                $this->searchParams['hasVideos'], 
                $this->lang, 
                $limit
            );
        }
    }
    
    /**
     * 複数条件による検索
     */
    private function searchWithMultipleConditions($limit) {
        markTime('Search: Before Prefecture Log');
        
        // 都道府県ページ閲覧ログを記録（都道府県パラメータのみの場合）
        if (!empty($this->searchParams['prefectures']) && empty($this->searchParams['query']) && empty($this->searchParams['completionYears'])) {
            try {
                require_once __DIR__ . '/src/Services/SearchLogService.php';
                $searchLogService = new SearchLogService();
                
                // 都道府県名の英語→日本語変換
                $prefectureTranslations = $this->getPrefectureTranslations();
                $prefectureName = $this->searchParams['prefectures'];
                if ($this->lang === 'ja' && isset($prefectureTranslations[$this->searchParams['prefectures']])) {
                    $prefectureName = $prefectureTranslations[$this->searchParams['prefectures']];
                }
                
                $searchLogService->logPageView('prefecture', $this->searchParams['prefectures'], $prefectureName, [
                    'lang' => $this->lang,
                    'hasPhotos' => $this->searchParams['hasPhotos'],
                    'hasVideos' => $this->searchParams['hasVideos'],
                    'prefecture_ja' => $prefectureTranslations[$this->searchParams['prefectures']] ?? $this->searchParams['prefectures'],
                    'prefecture_en' => $this->searchParams['prefectures']
                ]);
            } catch (Exception $e) {
                error_log("Prefecture page view log error: " . $e->getMessage());
            }
        }
        
        markTime('Search: Before CachedBuildingService Search');
        
        if ($this->cachedBuildingService) {
            $result = $this->cachedBuildingService->searchWithMultipleConditions(
                $this->searchParams['query'], 
                $this->searchParams['completionYears'], 
                $this->searchParams['prefectures'], 
                '', 
                $this->searchParams['hasPhotos'], 
                $this->searchParams['hasVideos'], 
                $this->searchParams['page'], 
                $this->lang, 
                $limit
            );
            
            markTime('Search: After CachedBuildingService Search');
            
            return $result;
        } else {
            markTime('Search: Before Fallback Search');
            
            // フォールバック: 既存の関数を使用
            $result = searchBuildingsWithMultipleConditions(
                $this->searchParams['query'], 
                $this->searchParams['completionYears'], 
                $this->searchParams['prefectures'], 
                '', 
                $this->searchParams['hasPhotos'], 
                $this->searchParams['hasVideos'], 
                $this->searchParams['page'], 
                $this->lang, 
                $limit
            );
            
            // フォールバック時のキャッシュ情報を追加
            if (is_array($result)) {
                $result['_cache_info'] = [
                    'hit' => false,
                    'reason' => 'cache_service_unavailable',
                'created' => time(),
                'expires' => time()
            ];
        }
        
        markTime('Search: After Fallback Search');
            
        return $result;
        }
    }
    
    /**
     * 人気検索の取得
     */
    private function getPopularSearches() {
        try {
            $this->popularSearches = getPopularSearches($this->lang);
        } catch (Exception $e) {
            error_log("Popular searches error: " . $e->getMessage());
            $this->popularSearches = [];
        }
    }
    
    /**
     * キャッシュ統計情報の取得
     */
    public function getCacheStats() {
        if ($this->cachedBuildingService) {
            return $this->cachedBuildingService->getCacheStats();
        }
        return null;
    }
    
    /**
     * アプリケーションの実行
     */
    public function run() {

        // 変数をビューで使用できるように設定
        $buildings = $this->searchResult['buildings'];
        $totalBuildings = $this->searchResult['total'];
        $totalPages = $this->searchResult['totalPages'];
        $currentPage = $this->searchResult['currentPage'];
        $currentBuilding = $this->searchResult['currentBuilding'] ?? null;
        $architectInfo = $this->searchResult['architectInfo'] ?? null;
        $isHomePage = $this->searchResult['isHomePage'] ?? false;
        

        // 元のindex.phpと同じ変数名を使用
        $query = $this->searchParams['query'];
        $page = $this->searchParams['page'];
        $hasPhotos = $this->searchParams['hasPhotos'];
        $hasVideos = $this->searchParams['hasVideos'];
        $userLat = $this->searchParams['userLat'];
        $userLng = $this->searchParams['userLng'];
        $radiusKm = $this->searchParams['radiusKm'];
        $buildingSlug = $this->searchParams['buildingSlug'];
        $prefectures = $this->searchParams['prefectures'];
        $architectsSlug = $this->searchParams['architectsSlug'];
        $completionYears = $this->searchParams['completionYears'];
        $limit = 10;
        
        $popularSearches = $this->popularSearches;
        $lang = $this->lang;
        

        // SEOメタタグの生成
        $pageType = 'home';
        $seoData = [];
        $structuredData = [];
        

        // SEOHelperクラスの読み込み
        if (file_exists(__DIR__ . '/src/Utils/SEOHelper.php')) {
//        if (false && file_exists(__DIR__ . '/src/Utils/SEOHelper.php')) {  // ← false && を追加
            require_once __DIR__ . '/src/Utils/SEOHelper.php';
            

            if ($buildingSlug && $currentBuilding) {
                // 建築物ページ
                $pageType = 'building';
                $seoData = SEOHelper::generateMetaTags('building', $currentBuilding, $lang);
                $structuredData = SEOHelper::generateStructuredData('building', $currentBuilding, $lang);

            } elseif ($architectsSlug && $architectInfo) {
                // 建築家ページ
                $pageType = 'architect';
                $architectInfo['building_count'] = count($buildings);
                $seoData = SEOHelper::generateMetaTags('architect', $architectInfo, $lang);
                $structuredData = SEOHelper::generateStructuredData('architect', $architectInfo, $lang);
            } elseif (!empty($query) || !empty($prefectures) || !empty($completionYears) || $hasPhotos || $hasVideos) {
                // 検索結果ページ
                $pageType = 'search';
                $searchData = [
                    'query' => $query,
                    'total' => $totalBuildings,
                    'currentPage' => $currentPage,
                    'prefectures' => $prefectures,
                    'completionYears' => $completionYears,
                    'hasPhotos' => $hasPhotos,
                    'hasVideos' => $hasVideos
                ];
                $seoData = SEOHelper::generateMetaTags('search', $searchData, $lang);
                $structuredData = SEOHelper::generateStructuredData('search', $searchData, $lang);
            } else {
                // ホームページ
                $pageType = 'home';
                $seoData = SEOHelper::generateMetaTags('home', [], $lang);
                $structuredData = SEOHelper::generateStructuredData('home', [], $lang);
            }


        } else {
            // SEOHelperが存在しない場合のフォールバック
            $seoData = [
                'title' => 'PocketNavi - 建築物検索',
                'description' => '建築物を検索できるサイト',
                'keywords' => '建築物,検索,建築家'
            ];
        }
        

        // キャッシュ統計情報
//        $cacheStats = $this->getCacheStats();
        // 変更後（無効化）
        $cacheStats = null;  // getCacheStats()を呼ばない
        

        // ビューファイルの読み込み
        $viewFile = 'src/Views/includes/production_index_view.php';
        if (file_exists($viewFile)) {
            // ビューファイルが存在する場合は使用（デバッグモードでも使用）
            include $viewFile;

        } else {
            // ビューファイルが存在しない場合はフォールバックビューを使用
            $this->renderFallbackView($buildings, $totalBuildings, $totalPages, $currentPage, $currentBuilding, $architectInfo, $query, $page, $hasPhotos, $hasVideos, $userLat, $userLng, $radiusKm, $buildingSlug, $prefectures, $architectsSlug, $completionYears, $limit, $popularSearches, $lang, $seoData, $structuredData, $cacheStats);
        }
    }
    
    /**
     * 都道府県翻訳データの取得
     */
    private function getPrefectureTranslations() {
        return [
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
    }
    
    /**
     * 都道府県の表示名を取得
     */
    private function getPrefectureDisplayName($prefectures, $lang) {
        // 都道府県の英語名から日本語名への変換マップ
        $prefectureMap = $this->getPrefectureTranslations();
        
        // 言語に応じて表示名を返す
        if ($lang === 'ja') {
            return $prefectureMap[$prefectures] ?? $prefectures;
        } else {
            return $prefectures;
        }
    }
    
    /**
     * フォールバックビューのレンダリング
     */
    private function renderFallbackView($buildings, $totalBuildings, $totalPages, $currentPage, $currentBuilding, $architectInfo, $query, $page, $hasPhotos, $hasVideos, $userLat, $userLng, $radiusKm, $buildingSlug, $prefectures, $architectsSlug, $completionYears, $limit, $popularSearches, $lang, $seoData, $structuredData, $cacheStats) {
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo $lang; ?>">
        <head>


<!-- CSRF Token -->
<?php echo csrfTokenMeta('search'); ?>


            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0" value="0">
            <!-- Permissions Policy for Geolocation -->
            <meta http-equiv="Permissions-Policy" content="geolocation=*">
            <!-- CSRF Token -->
            <?php echo csrfTokenMeta('search'); ?>
            <!-- SameSite Cookie Debug Info -->
            <?php echo getSameSiteCookieInfoHTML(); ?>
            <!-- SEO Meta Tags -->
            <?php if (!empty($seoData)): ?>
                <title><?php echo htmlspecialchars($seoData['title'] ?? 'PocketNavi - 建築物検索'); ?></title>
                <meta name="description" content="<?php echo htmlspecialchars($seoData['description'] ?? '建築物を検索できるサイト'); ?>">
                <meta name="keywords" content="<?php echo htmlspecialchars($seoData['keywords'] ?? '建築物,検索,建築家'); ?>">
                
                <!-- Open Graph Tags -->
                <?php if (isset($seoData['og_title'])): ?>
                    <meta property="og:title" content="<?php echo htmlspecialchars($seoData['og_title']); ?>">
                <?php endif; ?>
                <?php if (isset($seoData['og_description'])): ?>
                    <meta property="og:description" content="<?php echo htmlspecialchars($seoData['og_description']); ?>">
                <?php endif; ?>
                <?php if (isset($seoData['og_image'])): ?>
                    <meta property="og:image" content="<?php echo htmlspecialchars($seoData['og_image']); ?>">
                <?php endif; ?>
                <?php if (isset($seoData['og_url'])): ?>
                    <meta property="og:url" content="<?php echo htmlspecialchars($seoData['og_url']); ?>">
                <?php endif; ?>
                <?php if (isset($seoData['og_type'])): ?>
                    <meta property="og:type" content="<?php echo htmlspecialchars($seoData['og_type']); ?>">
                <?php endif; ?>
                <meta property="og:site_name" content="PocketNavi">
                
                <!-- Twitter Card Tags -->
                <?php if (isset($seoData['twitter_card'])): ?>
                    <meta name="twitter:card" content="<?php echo htmlspecialchars($seoData['twitter_card']); ?>">
                <?php endif; ?>
                <?php if (isset($seoData['twitter_title'])): ?>
                    <meta name="twitter:title" content="<?php echo htmlspecialchars($seoData['twitter_title']); ?>">
                <?php endif; ?>
                <?php if (isset($seoData['twitter_description'])): ?>
                    <meta name="twitter:description" content="<?php echo htmlspecialchars($seoData['twitter_description']); ?>">
                <?php endif; ?>
                <?php if (isset($seoData['twitter_image'])): ?>
                    <meta name="twitter:image" content="<?php echo htmlspecialchars($seoData['twitter_image']); ?>">
                <?php endif; ?>
                
                <!-- Canonical URL -->
                <?php if (isset($seoData['canonical'])): ?>
                    <link rel="canonical" href="<?php echo htmlspecialchars($seoData['canonical']); ?>">
                <?php endif; ?>
            <?php else: ?>
                <title>PocketNavi - 建築物検索</title>
                <meta name="description" content="建築物を検索できるサイト">
                <meta name="keywords" content="建築物,検索,建築家">
            <?php endif; ?>
            
            <!-- Structured Data (JSON-LD) -->
            <?php if (!empty($structuredData)): ?>
                <script type="application/ld+json">
                <?php echo json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                </script>
            <?php endif; ?>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <script src="https://unpkg.com/lucide@latest"></script>
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <link rel="stylesheet" href="/assets/css/style.css">
            <link rel="icon" href="/assets/images/landmark.svg" type="image/svg+xml">

<!-- 早期エラーフィルタリング（最優先） -->
<script>
(function() {
    // 外部スクリプトエラーのフィルタリング（最早期版）
    window.addEventListener('error', function(event) {
        // 外部ブラウザ拡張機能のエラーを無視
        if (event.filename && (
            event.filename.includes('content.js') ||
            event.filename.includes('inject.js') ||
            event.filename.includes('main.js') ||
            event.filename.includes('chrome-extension://') ||
            event.filename.includes('moz-extension://') ||
            event.filename.includes('safari-extension://') ||
            event.filename.includes('extension://')
        )) {
            event.preventDefault();
            event.stopPropagation();
            return false;
        }
        
        // 特定のエラーメッセージを無視
        if (event.message && (
            event.message.includes('priceAreaElement is not defined') ||
            event.message.includes('Photo gallery card not found') ||
            event.message.includes('document.write()') ||
            event.message.includes('Avoid using document.write()') ||
            event.message.includes('Port connected')
        )) {
            event.preventDefault();
            event.stopPropagation();
            return false;
        }
    });
    
    // コンソール出力のフィルタリング（最早期版）
    const originalWarn = console.warn;
    const originalError = console.error;
    const originalLog = console.log;
    
    console.warn = function(...args) {
        const message = args.join(' ');
        if (message.includes('Avoid using document.write()') ||
            message.includes('document.write()') ||
            message.includes('Port connected') ||
            message.includes('コンテンツスクリプト実行中')) {
            return;
        }
        originalWarn.apply(console, args);
    };
    
    console.error = function(...args) {
        const message = args.join(' ');
        if (message.includes('priceAreaElement is not defined') ||
            message.includes('Photo gallery card not found') ||
            message.includes('Port connected') ||
            message.includes('コンテンツスクリプト実行中')) {
            return;
        }
        originalError.apply(console, args);
    };
    
    console.log = function(...args) {
        const message = args.join(' ');
        if (message.includes('Port connected') ||
            message.includes('コンテンツスクリプト実行中') ||
            message.includes('Initializing photo gallery') ||
            // Google Analytics関連のメッセージは除外（デバッグ用）
            (message.includes('Google Analytics') && !message.includes('initialized'))) {
            return;
        }
        originalLog.apply(console, args);
    };
})();
</script>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-9FY04VHM17"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-9FY04VHM17', {
//    'debug_mode': true,
    'debug_mode': false,  // ← falseに変更
    'send_page_view': true
  });

  // Google Analytics デバッグ用
//  console.log('Google Analytics initialized with ID: G-9FY04VHM17');
//  console.log('DataLayer:', window.dataLayer);
  
  // ページビューイベントの確認
//  gtag('event', 'page_view', {
//    'page_title': document.title,
//    'page_location': window.location.href
//  });
  
  // カスタムイベントのテスト
//  setTimeout(function() {
//    gtag('event', 'test_event', {
//      'event_category': 'debug',
//      'event_label': 'analytics_test'
//    });
//    console.log('Test event sent to Google Analytics');
//  }, 2000);
</script>

<!-- 動的件数更新用のJavaScript -->
<script>
// CSRFトークン管理
class CSRFManager {
    static getToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : null;
    }
    
    static addToRequest(options = {}) {
        const token = this.getToken();
        if (!token) return options;
        
        // ヘッダーに追加
        if (!options.headers) {
            options.headers = {};
        }
        options.headers['X-CSRF-Token'] = token;
        
        // POSTデータに追加
        if (options.method && options.method.toUpperCase() === 'POST') {
            if (!options.body) {
                options.body = new FormData();
            }
            if (options.body instanceof FormData) {
                options.body.append('csrf_token', token);
            } else if (typeof options.body === 'string') {
                try {
                    const data = JSON.parse(options.body);
                    data.csrf_token = token;
                    options.body = JSON.stringify(data);
                } catch (e) {
                    // JSONでない場合はFormDataに変換
                    const formData = new FormData();
                    formData.append('csrf_token', token);
                    formData.append('data', options.body);
                    options.body = formData;
                }
            }
        }
        
        return options;
    }
}

// 検索結果件数の動的更新機能
class SearchResultsUpdater {
    constructor() {
        this.updateTimeout = null;
        this.isUpdating = false;
        this.init();
    }
    
    init() {
        // フィルター変更イベントの監視
        this.observeFilterChanges();
        // 検索フォームの監視
        this.observeSearchForm();
    }
    
    // フィルター変更の監視
    observeFilterChanges() {
        // 詳細検索カード内の要素かどうかをチェックする関数
        const isInAdvancedSearch = (element) => {
            const advancedSearchCard = document.getElementById('advancedSearch');
            return advancedSearchCard && advancedSearchCard.contains(element);
        };
        
        // 都道府県選択の監視（詳細検索カード内は除外）
        const prefectureSelects = document.querySelectorAll('select[name="prefectures[]"], select[name="prefectures"]');
        prefectureSelects.forEach(select => {
            if (!isInAdvancedSearch(select)) {
                select.addEventListener('change', () => {
                    this.scheduleUpdate();
                });
            }
        });
        
        // 完成年選択の監視（詳細検索カード内は除外）
        const yearSelects = document.querySelectorAll('select[name="completionYears[]"], select[name="completionYears"]');
        yearSelects.forEach(select => {
            if (!isInAdvancedSearch(select)) {
                select.addEventListener('change', () => {
                    this.scheduleUpdate();
                });
            }
        });
        
        // 写真・動画チェックボックスの監視（詳細検索カード内は除外）
        const photoCheckboxes = document.querySelectorAll('input[name="photos"], input[name="hasPhotos"]');
        photoCheckboxes.forEach(checkbox => {
            if (!isInAdvancedSearch(checkbox)) {
                checkbox.addEventListener('change', () => {
                    this.scheduleUpdate();
                });
            }
        });
        
        const videoCheckboxes = document.querySelectorAll('input[name="videos"], input[name="hasVideos"]');
        videoCheckboxes.forEach(checkbox => {
            if (!isInAdvancedSearch(checkbox)) {
                checkbox.addEventListener('change', () => {
                    this.scheduleUpdate();
                });
            }
        });
    }
    
    // 検索フォームの監視
    observeSearchForm() {
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                this.scheduleUpdate();
            });
        }
    }
    
    // 更新のスケジュール（デバウンス）
    scheduleUpdate() {
        if (this.updateTimeout) {
            clearTimeout(this.updateTimeout);
        }
        
        this.updateTimeout = setTimeout(() => {
            this.updateResultsCount();
        }, 500); // 500ms後に実行
    }
    
    // 検索結果件数の更新
    async updateResultsCount() {
        if (this.isUpdating) return;
        
        this.isUpdating = true;
        this.showLoadingState();
        
        try {
            // 現在の検索パラメータを取得
            const searchParams = this.getCurrentSearchParams();
            
            // APIエンドポイントにリクエスト（CSRFトークン付き）
            const requestOptions = CSRFManager.addToRequest({
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(searchParams)
            });
            
            const response = await fetch('/api/search-count.php', requestOptions);
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateResultsDisplay(data.count);
                } else {
                    console.error('API returned error:', data.error);
                    this.showFallbackMessage();
                }
            } else {
                console.error('Failed to update results count');
                this.showFallbackMessage();
            }
        } catch (error) {
            console.error('Error updating results count:', error);
        } finally {
            this.isUpdating = false;
            this.hideLoadingState();
        }
    }
    
    // 現在の検索パラメータを取得
    getCurrentSearchParams() {
        const form = document.querySelector('form[method="get"]');
        if (!form) return {};
        
        const formData = new FormData(form);
        const params = {};
        
        for (let [key, value] of formData.entries()) {
            if (params[key]) {
                if (Array.isArray(params[key])) {
                    params[key].push(value);
                } else {
                    params[key] = [params[key], value];
                }
            } else {
                params[key] = value;
            }
        }
        
        return params;
    }
    
    // 結果表示の更新
    updateResultsDisplay(count) {
        const countElements = document.querySelectorAll('.search-results-summary strong');
        countElements.forEach(element => {
            element.textContent = count.toLocaleString() + '件';
        });
        
        // ページネーションの更新
        this.updatePagination(count);
    }
    
    // ページネーションの更新
    updatePagination(totalCount) {
        const pagination = document.querySelector('.pagination');
        if (!pagination) return;
        
        const itemsPerPage = 10; // デフォルトの1ページあたりの件数
        const totalPages = Math.ceil(totalCount / itemsPerPage);
        
        // ページネーション情報の更新
        const pageInfo = document.querySelector('.page-info');
        if (pageInfo) {
            pageInfo.textContent = `ページ 1 / ${totalPages} (${totalCount.toLocaleString()} 件)`;
        }
    }
    
    // ローディング状態の表示
    showLoadingState() {
        const countElements = document.querySelectorAll('.search-results-summary strong');
        countElements.forEach(element => {
            element.innerHTML = '<i class="spinner-border spinner-border-sm" role="status"></i>';
        });
    }
    
    // ローディング状態の非表示
    hideLoadingState() {
        // ローディング状態は updateResultsDisplay で上書きされる
    }
    
    // フォールバックメッセージの表示
    showFallbackMessage() {
        const countElements = document.querySelectorAll('.search-results-summary strong');
        countElements.forEach(element => {
            element.textContent = '更新中...';
        });
        
        // 3秒後に元の値に戻す
        setTimeout(() => {
            // ページを再読み込みして最新の件数を取得
            window.location.reload();
        }, 3000);
    }
}

// ページ読み込み完了後に初期化
document.addEventListener('DOMContentLoaded', function() {
    new SearchResultsUpdater();
    
    // Phase 3A: アニメーション効果の初期化
    initializeAnimations();
});

// Phase 3A: アニメーション効果の初期化
function initializeAnimations() {
    // アニメーション無効化の確認
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    
    if (prefersReducedMotion) {
        // アニメーションを無効化
        document.documentElement.style.setProperty('--animation-duration', '0.01ms');
        return;
    }
    
    // 建築物カードの段階的表示アニメーション
    const buildingCards = document.querySelectorAll('.building-card');
    buildingCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease-out';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100); // 100ms間隔で段階的に表示
    });
    
    // フィルターバッジのクリック効果
    const filterBadges = document.querySelectorAll('.filter-badge, .architect-badge, .building-type-badge, .prefecture-badge, .completion-year-badge');
    filterBadges.forEach(badge => {
        badge.addEventListener('click', function(e) {
            // クリック時のリップル効果
            const ripple = document.createElement('span');
            ripple.style.position = 'absolute';
            ripple.style.borderRadius = '50%';
            ripple.style.background = 'rgba(255, 255, 255, 0.6)';
            ripple.style.transform = 'scale(0)';
            ripple.style.animation = 'ripple 0.6s linear';
            ripple.style.left = e.offsetX + 'px';
            ripple.style.top = e.offsetY + 'px';
            ripple.style.width = ripple.style.height = '20px';
            ripple.style.pointerEvents = 'none';
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // 検索結果件数のカウントアップアニメーション
    const resultCounts = document.querySelectorAll('.search-results-summary strong');
    resultCounts.forEach(element => {
        const finalCount = parseInt(element.textContent.replace(/[^\d]/g, ''));
        if (finalCount > 0) {
            animateCountUp(element, finalCount);
        }
    });
    
    // ページネーションのホバー効果強化
    const pageLinks = document.querySelectorAll('.page-link');
    pageLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
}

// カウントアップアニメーション
function animateCountUp(element, finalCount) {
    const duration = 1000; // 1秒
    const startTime = performance.now();
    
    function updateCount(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // イージング関数（ease-out）
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const currentCount = Math.floor(finalCount * easeOut);
        
        element.textContent = currentCount.toLocaleString() + '件';
        
        if (progress < 1) {
            requestAnimationFrame(updateCount);
        }
    }
    
    requestAnimationFrame(updateCount);
}

// リップル効果のCSSアニメーション
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyle);
</script>

        </head>
        <body>
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <?php 
                        // デバッグ情報は非表示（debug=1パラメータでも表示しない）
                        // if ($this->debugMode): 
                        ?>
                            <?php /* 
                            <div class="alert alert-info">
                                <h4>🚀 PocketNavi リファクタリング版</h4>
                                <p>新しいアーキテクチャで動作しています。</p>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-success">REFACTORED</span>
                                    <?php if ($this->cacheEnabled): ?>
                                        <span class="badge bg-primary">キャッシュ有効</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">キャッシュ無効</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($cacheStats): ?>
                                    <div class="mt-2">
                                        <small>
                                            キャッシュ統計: 
                                            ファイル数: <?php echo $cacheStats['totalFiles']; ?>件, 
                                            サイズ: <?php echo round($cacheStats['totalSize'] / 1024, 2); ?>KB
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-2">
                                    <small class="text-muted">
                                        デバッグ情報:<br>
                                        - キャッシュサービス: <?php echo $this->cachedBuildingService ? '利用可能' : '利用不可'; ?><br>
                                        - キャッシュ有効: <?php echo $this->cacheEnabled ? 'true' : 'false'; ?><br>
                                        - 検索パラメータ: <?php echo htmlspecialchars(json_encode($this->searchParams)); ?>
                                    </small>
                                </div>
                            </div>
                            */ ?>
                        <?php // endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <!-- 検索フォーム -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">建築物検索</h5>
                                <form method="GET">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <input type="text" name="q" class="form-control" placeholder="キーワード" value="<?php echo htmlspecialchars($query); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" name="prefectures" class="form-control" placeholder="都道府県" value="<?php echo htmlspecialchars($prefectures); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-primary w-100">検索</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- フィルター適用済み -->
                        <?php if ($architectsSlug && $architectInfo): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">フィルター適用済み</h6>
                                    <div class="d-flex gap-2 mb-2">
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($architectInfo['name'] ?? $architectInfo['name_ja'] ?? $architectInfo['name_en'] ?? $architectsSlug); ?>
                                            <a href="?" class="text-white text-decoration-none ms-1">×</a>
                                        </span>
                                    </div>
                                    <!-- 検索結果件数表示 -->
                                    <div class="search-results-summary">
                                        <p class="mb-0 text-muted">
                                            <i class="bi bi-search me-1"></i>
                                            検索結果: <strong><?php echo number_format($totalBuildings); ?>件</strong>の建築物が見つかりました
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($prefectures): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">フィルター適用済み</h6>
                                    <div class="d-flex gap-2 mb-2">
                                        <span class="badge bg-primary">
                                            <i class="bi bi-geo-alt"></i>
                                            <?php echo htmlspecialchars($this->getPrefectureDisplayName($prefectures, $lang)); ?>
                                            <a href="?" class="text-white text-decoration-none ms-1">×</a>
                                        </span>
                                    </div>
                                    <!-- 検索結果件数表示 -->
                                    <div class="search-results-summary">
                                        <p class="mb-0 text-muted">
                                            <i class="bi bi-search me-1"></i>
                                            検索結果: <strong><?php echo number_format($totalBuildings); ?>件</strong>の建築物が見つかりました
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php elseif (!empty($query) || !empty($completionYears) || $hasPhotos || $hasVideos): ?>
                            <!-- 検索条件があるが、建築家・都道府県フィルターがない場合 -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">検索結果</h6>
                                    <div class="search-results-summary">
                                        <p class="mb-0 text-muted">
                                            <i class="bi bi-search me-1"></i>
                                            検索結果: <strong><?php echo number_format($totalBuildings); ?>件</strong>の建築物が見つかりました
                                        </p>
                                        <?php if (!empty($query)): ?>
                                            <p class="mb-0 small text-muted">
                                                <i class="bi bi-tag me-1"></i>
                                                検索キーワード: "<?php echo htmlspecialchars($query); ?>"
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 検索結果がない場合の件数表示 -->
                        <?php if (empty($buildings) && ($hasPhotos || $hasVideos || $completionYears || $prefectures || $query || $architectsSlug)): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="search-results-summary">
                                        <p class="mb-0 text-muted">
                                            <i class="bi bi-search me-1"></i>
                                            検索結果: <strong>0件</strong>の建築物が見つかりました
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 検索結果 -->
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    検索結果 
                                    <span class="badge bg-primary"><?php echo $totalBuildings; ?>件</span>
                                    
                                    <?php if ($this->debugMode && isset($this->searchResult['_cache_info'])): ?>
                                        <?php $cacheInfo = $this->searchResult['_cache_info']; ?>
                                        <?php if ($cacheInfo['hit']): ?>
                                            <span class="badge bg-success ms-2">
                                                <i class="bi bi-lightning-charge"></i>
                                                キャッシュヒット
                                            </span>
                                            <small class="text-muted d-block mt-1">
                                                キャッシュ作成: <?php echo date('H:i:s', $cacheInfo['created']); ?> 
                                                (<?php echo round($cacheInfo['age'] / 60, 1); ?>分前)
                                                <?php if (isset($cacheInfo['execution_time_ms'])): ?>
                                                    | 実行時間: <?php echo $cacheInfo['execution_time_ms']; ?>ms
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-warning ms-2">
                                                <i class="bi bi-database"></i>
                                                データベース検索
                                            </span>
                                            <small class="text-muted d-block mt-1">
                                                理由: <?php 
                                                    switch($cacheInfo['reason']) {
                                                        case 'cache_miss': echo 'キャッシュなし'; break;
                                                        case 'cache_disabled': echo 'キャッシュ無効'; break;
                                                        case 'cache_service_unavailable': echo 'キャッシュサービス利用不可'; break;
                                                        case 'no_cache_info': echo 'キャッシュ情報なし'; break;
                                                        default: echo $cacheInfo['reason']; break;
                                                    }
                                                ?>
                                                <?php if (isset($cacheInfo['execution_time_ms'])): ?>
                                                    | 実行時間: <?php echo $cacheInfo['execution_time_ms']; ?>ms
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </h5>
                                
                                <?php if (!empty($buildings)): ?>
                                    <?php foreach ($buildings as $index => $building): ?>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($building['title'] ?? ''); ?></h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        場所: <?php echo htmlspecialchars($building['location'] ?? ''); ?><br>
                                                        完成年: <?php echo htmlspecialchars($building['completionYears'] ?? ''); ?>
                                                    </small>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">検索結果が見つかりませんでした。</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- 人気検索 -->
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">人気検索</h5>
                                <?php if (!empty($popularSearches)): ?>
                                    <?php foreach ($popularSearches as $search): ?>
                                        <a href="?q=<?php echo urlencode($search['query']); ?>" class="btn btn-outline-secondary btn-sm me-2 mb-2">
                                            <?php echo htmlspecialchars($search['query']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
                // 外部スクリプトエラーのフィルタリング（強化版）
                window.addEventListener('error', function(event) {
                    // 外部ブラウザ拡張機能のエラーを無視
                    if (event.filename && (
                        event.filename.includes('content.js') ||
                        event.filename.includes('inject.js') ||
                        event.filename.includes('main.js') ||
                        event.filename.includes('chrome-extension://') ||
                        event.filename.includes('moz-extension://') ||
                        event.filename.includes('safari-extension://') ||
                        event.filename.includes('extension://')
                    )) {
                        event.preventDefault();
                        event.stopPropagation();
                        return false;
                    }
                    
                    // 特定のエラーメッセージを無視
                    if (event.message && (
                        event.message.includes('priceAreaElement is not defined') ||
                        event.message.includes('Photo gallery card not found') ||
                        event.message.includes('document.write()') ||
                        event.message.includes('Avoid using document.write()') ||
                        event.message.includes('Port connected')
                    )) {
                        event.preventDefault();
                        event.stopPropagation();
                        return false;
                    }
                    
                    // エラーのソースが外部拡張機能の場合は無視
                    if (event.target && event.target.tagName === 'SCRIPT' && 
                        event.target.src && (
                            event.target.src.includes('chrome-extension://') ||
                            event.target.src.includes('moz-extension://') ||
                            event.target.src.includes('safari-extension://')
                        )) {
                        event.preventDefault();
                        event.stopPropagation();
                        return false;
                    }
                });
                
                // 未処理のPromise拒否エラーをフィルタリング
                window.addEventListener('unhandledrejection', function(event) {
                    if (event.reason && event.reason.message && (
                        event.reason.message.includes('priceAreaElement is not defined') ||
                        event.reason.message.includes('Photo gallery card not found')
                    )) {
                        event.preventDefault();
                        return false;
                    }
                });
                
                // コンソール警告のフィルタリング（強化版）
                const originalWarn = console.warn;
                const originalError = console.error;
                const originalLog = console.log;
                
                console.warn = function(...args) {
                    const message = args.join(' ');
                    if (message.includes('Avoid using document.write()') ||
                        message.includes('document.write()') ||
                        message.includes('Port connected') ||
                        message.includes('コンテンツスクリプト実行中')) {
                        return; // 警告を無視
                    }
                    originalWarn.apply(console, args);
                };
                
                console.error = function(...args) {
                    const message = args.join(' ');
                    if (message.includes('priceAreaElement is not defined') ||
                        message.includes('Photo gallery card not found') ||
                        message.includes('Port connected') ||
                        message.includes('コンテンツスクリプト実行中')) {
                        return; // エラーを無視
                    }
                    originalError.apply(console, args);
                };
                
                console.log = function(...args) {
                    const message = args.join(' ');
                    if (message.includes('Port connected') ||
                        message.includes('コンテンツスクリプト実行中') ||
                        message.includes('Initializing photo gallery')) {
                        return; // ログを無視
                    }
                    originalLog.apply(console, args);
                };
                
                document.addEventListener("DOMContentLoaded", () => {
                    lucide.createIcons();
                });
            </script>
        </body>
        </html>
        <?php
    }
}

// ============================================================================
// アプリケーションの実行
// ============================================================================

try {
    markTime('Before App Init');
    $app = new PocketNaviSafeApp();
    markTime('After App Init');

    markTime('Before View Render');
    $app->run();
    markTime('After View Render');

} catch (Exception $e) {
    error_log("Application error: " . $e->getMessage());
    
    // エラーが発生した場合は元のindex.phpにフォールバック
    if (file_exists('index.php')) {
        require_once 'index.php';
    } else {
        // 最終的なフォールバック
        http_response_code(500);
        echo '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Permissions Policy for Geolocation -->
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    <title>システムエラー - PocketNavi</title>
</head>
<body>
    <h1>システムエラーが発生しました</h1>
    <p>申し訳ございませんが、システムに一時的な問題が発生しています。</p>
    <p>しばらく時間をおいてから再度お試しください。</p>
    <p>エラー詳細: ' . htmlspecialchars($e->getMessage()) . '</p>
</body>
</html>';
    }
}


// 7. 最後に統計を出力
register_shutdown_function(function() {
    global $_perf;
    $total = round((microtime(true) - $_perf['start']) * 1000, 2);
    
    error_log("========================================");
    error_log("TIMING BREAKDOWN");
    error_log("========================================");
    
    $prev = 0;
    foreach ($_perf['points'] as $point) {
        $delta = $point['elapsed'] - $prev;
        error_log(sprintf("[%6.2fms] (+%6.2fms) %s", $point['elapsed'], $delta, $point['label']));
        $prev = $point['elapsed'];
    }
    
    error_log("========================================");
    error_log("Total: {$total}ms");
    error_log("========================================");
});



?>
