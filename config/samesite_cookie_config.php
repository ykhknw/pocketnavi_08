<?php
/**
 * SameSite Cookie Configuration
 * 
 * SameSite属性を持つCookieの設定ファイル
 * 
 * @package PocketNavi
 * @subpackage Config
 */

// 本番環境かどうかを判定
$isProduction = !isset($_SERVER['HTTP_HOST']) || 
               !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);

// SameSite Cookie設定
return [
    // 環境設定
    'environment' => [
        'is_production' => $isProduction,
        'is_https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    ],
    
    // デフォルト設定
    'defaults' => [
        'samesite' => 'Lax',           // デフォルトはLax
        'secure' => $isProduction,     // 本番環境ではSecure
        'httponly' => true,            // JavaScriptからのアクセスを禁止
        'path' => '/',                 // サイト全体で有効
        'domain' => null,              // 自動設定
    ],
    
    // セッション設定
    'session' => [
        'samesite' => 'Lax',           // セッションCookieはLax
        'secure' => $isProduction,     // HTTPS環境ではSecure
        'httponly' => true,            // XSS攻撃対策
        'path' => '/',
        'lifetime' => 0,               // ブラウザ終了まで
        'name' => 'PHPSESSID',         // セッション名
    ],
    
    // CSRFトークン設定
    'csrf' => [
        'samesite' => 'Strict',        // CSRFトークンはStrict
        'secure' => $isProduction,
        'httponly' => true,
        'path' => '/',
        'lifetime' => 3600,            // 1時間
        'name' => 'csrf_token',
    ],
    
    // 認証Cookie設定
    'auth' => [
        'samesite' => 'Strict',        // 認証CookieはStrict
        'secure' => $isProduction,
        'httponly' => true,
        'path' => '/',
        'lifetime' => 30 * 24 * 60 * 60, // 30日
    ],
    
    // 分析Cookie設定（Google Analytics等）
    'analytics' => [
        'samesite' => 'None',          // 分析CookieはNone（クロスサイト許可）
        'secure' => true,              // Noneの場合はSecure必須
        'httponly' => false,           // JavaScriptからアクセス可能
        'path' => '/',
        'lifetime' => 365 * 24 * 60 * 60, // 1年
    ],
    
    // 機能別Cookie設定
    'features' => [
        // 言語設定
        'language' => [
            'samesite' => 'Lax',
            'secure' => $isProduction,
            'httponly' => false,       // JavaScriptからアクセス可能
            'path' => '/',
            'lifetime' => 365 * 24 * 60 * 60, // 1年
        ],
        
        // テーマ設定
        'theme' => [
            'samesite' => 'Lax',
            'secure' => $isProduction,
            'httponly' => false,
            'path' => '/',
            'lifetime' => 365 * 24 * 60 * 60, // 1年
        ],
        
        // 検索履歴
        'search_history' => [
            'samesite' => 'Lax',
            'secure' => $isProduction,
            'httponly' => true,
            'path' => '/',
            'lifetime' => 7 * 24 * 60 * 60, // 7日
        ],
        
        // ユーザー設定
        'user_preferences' => [
            'samesite' => 'Lax',
            'secure' => $isProduction,
            'httponly' => true,
            'path' => '/',
            'lifetime' => 30 * 24 * 60 * 60, // 30日
        ],
    ],
    
    // セキュリティ設定
    'security' => [
        // 厳格モード（本番環境推奨）
        'strict_mode' => $isProduction,
        
        // セッション固定攻撃対策
        'regenerate_session_id' => true,
        
        // セッションタイムアウト
        'session_timeout' => 30 * 60, // 30分
        
        // 最大セッション数
        'max_sessions' => 5,
    ],
    
    // ブラウザ互換性設定
    'compatibility' => [
        // 古いブラウザ対応
        'legacy_browser_support' => !$isProduction,
        
        // SameSite=None対応ブラウザ
        'samesite_none_support' => [
            'chrome' => '51',
            'firefox' => '60',
            'safari' => '12',
            'edge' => '79',
        ],
    ],
    
    // デバッグ設定
    'debug' => [
        'enabled' => !$isProduction,
        'log_cookie_operations' => !$isProduction,
        'show_cookie_info' => !$isProduction,
    ],
];
?>
