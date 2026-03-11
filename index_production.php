<?php
/**
 * 本番環境用メインエントリーポイント
 * PocketNavi - 本番環境
 */

// 本番環境設定の読み込み
require_once __DIR__ . '/src/Utils/ProductionConfig.php';
require_once __DIR__ . '/src/Utils/ProductionErrorHandler.php';

// 本番環境の初期化
$productionConfig = ProductionConfig::getInstance();
$errorHandler = ProductionErrorHandler::getInstance();

// 本番環境設定の適用
if ($productionConfig->isProduction()) {
    // エラー表示を無効にする
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    
    // エラーログを有効にする
    ini_set('log_errors', 1);
    
    // メモリ制限と実行時間制限の設定
    $perfConfig = $productionConfig->getPerformanceConfig();
    ini_set('memory_limit', $perfConfig['memory_limit']);
    ini_set('max_execution_time', $perfConfig['max_execution_time']);
}

// セキュリティ設定の適用
$securityConfig = $productionConfig->getSecurityConfig();
if ($securityConfig['security_headers']) {
    // 統合されたセキュリティヘッダーの設定
    if (file_exists(__DIR__ . '/src/Security/UnifiedSecurityHeaders.php')) {
        require_once __DIR__ . '/src/Security/UnifiedSecurityHeaders.php';
        $securityHeaders = new UnifiedSecurityHeaders('production');
        $securityHeaders->sendHeaders();
    } else {
        // フォールバック: 従来の設定
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=*, microphone=(), camera=(), payment=()');
        
        // HTTPS環境の場合のHSTS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none';");
}

// セッション設定の適用
if ($securityConfig['session_lifetime']) {
    ini_set('session.gc_maxlifetime', $securityConfig['session_lifetime']);
    ini_set('session.cookie_lifetime', $securityConfig['session_lifetime']);
}

// セッションの開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// データベース設定の適用
$dbConfig = $productionConfig->getDatabaseConfig();
define('DB_HOST', $dbConfig['host']);
define('DB_NAME', $dbConfig['dbname']);
define('DB_USERNAME', $dbConfig['username']);
define('DB_PASS', $dbConfig['password']);

// アプリケーション設定の適用
define('APP_NAME', $productionConfig->get('APP_NAME', 'PocketNavi'));
define('APP_ENV', $productionConfig->get('APP_ENV', 'production'));
define('APP_DEBUG', $productionConfig->isDebug());

// 統一されたデータベース接続の読み込み
require_once __DIR__ . '/config/database_unified.php';

// セキュリティシステムの初期化
if ($securityConfig['csrf_protection'] || $securityConfig['rate_limiting']) {
    require_once __DIR__ . '/src/Security/SecurityManager.php';
    $securityManager = SecurityManager::getInstance();
    $securityManager->initialize();
}

// キャッシュシステムの初期化
require_once __DIR__ . '/src/Cache/CacheManager.php';
$cacheManager = CacheManager::getInstance();

// パフォーマンス監視の開始
$startTime = microtime(true);
$startMemory = memory_get_usage();

// メインアプリケーションの実行
try {
    // ルーティングシステムの読み込み
    require_once __DIR__ . '/src/Core/Router.php';
    require_once __DIR__ . '/routes/web_minimal.php';
    
    // リクエストの処理
    Router::dispatch();
    
} catch (Exception $e) {
    // 本番環境でのエラーハンドリング
    if ($productionConfig->isProduction()) {
        // エラーログに記録
        error_log("Application Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        
        // 一般的なエラーページを表示
        http_response_code(500);
        echo '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>システムエラー - PocketNavi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-icon { font-size: 48px; color: #e74c3c; text-align: center; margin-bottom: 20px; }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 20px; }
        p { color: #7f8c8d; line-height: 1.6; text-align: center; }
        .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; }
        .back-link:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">⚠️</div>
        <h1>システムエラーが発生しました</h1>
        <p>申し訳ございませんが、システムに一時的な問題が発生しています。</p>
        <p>しばらく時間をおいてから再度お試しください。</p>
        <p>問題が解決しない場合は、管理者にお問い合わせください。</p>
        <a href="/" class="back-link">トップページに戻る</a>
    </div>
</body>
</html>';
    } else {
        // 開発環境では詳細なエラー情報を表示
        echo "Application Error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    exit;
}

// パフォーマンス監視の終了
$endTime = microtime(true);
$endMemory = memory_get_usage();

// パフォーマンスログの記録（本番環境では無効）
if (!$productionConfig->isProduction()) {
    $executionTime = ($endTime - $startTime) * 1000;
    $memoryUsage = ($endMemory - $startMemory) / 1024 / 1024;
    
    error_log("Performance: Execution time: {$executionTime}ms, Memory usage: {$memoryUsage}MB");
}

