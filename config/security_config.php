<?php
/**
 * セキュリティ設定ファイル
 * Phase 1: 緊急対応セキュリティ強化
 */
return [
    // 認証設定（開発段階では無効）
    'auth' => [
        'enabled' => false, // 開発段階では無効
        'session_timeout' => 3600, // 1時間
        'max_login_attempts' => 5,
        'lockout_time' => 900, // 15分
        'password_min_length' => 8,
        'password_require_special' => true,
        'mfa_enabled' => false, // 開発段階では無効
        'mfa_code_length' => 6
    ],
    
    // セッション設定
    'session' => [
        'cookie_httponly' => true,
        'cookie_secure' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'regenerate_id_interval' => 300 // 5分
    ],
    
    // セキュリティヘッダー設定
    'headers' => [
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'hsts_enabled' => true,
        'hsts_max_age' => 31536000,
        'hsts_include_subdomains' => true,
        'hsts_preload' => true
    ],
    
    // Content Security Policy設定（緊急対応版）
    'csp' => [
        'default_src' => ["'self'"],
        'script_src' => [
            "'self'", 
            "'unsafe-inline'", 
            "https://cdn.jsdelivr.net", 
            "https://unpkg.com", 
            "https://www.googletagmanager.com",
            "https://www.google-analytics.com"
        ],
        'style_src' => [
            "'self'", 
            "'unsafe-inline'", 
            "https://cdn.jsdelivr.net", 
            "https://unpkg.com",
            "https://fonts.googleapis.com"
        ],
        'img_src' => ["'self'", "data:", "https:", "http:"],
        'font_src' => [
            "'self'", 
            "https://cdn.jsdelivr.net", 
            "https://unpkg.com",
            "https://fonts.gstatic.com"
        ],
        'connect_src' => [
            "'self'", 
            "https://www.google-analytics.com", 
            "https://cdn.jsdelivr.net", 
            "https://unpkg.com",
            "https://analytics.google.com"
        ],
        'frame_ancestors' => ["'none'"],
        'base_uri' => ["'self'"],
        'form_action' => ["'self'"],
        'object_src' => ["'none'"],
        'media_src' => ["'self'"],
        'manifest_src' => ["'self'"],
        'worker_src' => ["'self'"],
        'child_src' => ["'self'"]
    ],
    
    // 入力検証設定
    'validation' => [
        'max_string_length' => 255,
        'max_array_items' => 100,
        'allowed_file_types' => ['image/jpeg', 'image/png', 'image/gif'],
        'max_file_size' => 5 * 1024 * 1024, // 5MB
        'sql_injection_protection' => true,
        'xss_protection' => true
    ],
    
    // ログ設定
    'logging' => [
        'security_events' => true,
        'failed_logins' => true,
        'admin_actions' => true,
        'error_logging' => true,
        'log_retention_days' => 90,
        'log_file_path' => __DIR__ . '/../logs/security.log'
    ],
    
    // レート制限設定
    'rate_limiting' => [
        'enabled' => true,
        'login_attempts_per_minute' => 5,
        'api_requests_per_minute' => 100,
        'cache_cleanup_per_hour' => 10
    ],
    
    // ファイルアップロード設定
    'file_upload' => [
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif'],
        'max_size' => 5 * 1024 * 1024, // 5MB
        'scan_for_malware' => false, // 本番環境では有効化を検討
        'quarantine_suspicious' => false
    ],
    
    // データベース設定
    'database' => [
        'connection_timeout' => 30,
        'query_timeout' => 60,
        'max_connections' => 100,
        'enable_query_logging' => false // 本番環境では無効
    ],
    
    // 環境設定
    'environment' => [
        'production_mode' => true,
        'debug_mode' => false,
        'error_reporting' => E_ALL,
        'display_errors' => false,
        'log_errors' => true
    ]
];
?>
