<?php
// アプリケーション設定ファイル

return [
    'name' => getenv('APP_NAME') ?: 'PocketNavi',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN) ?: false,
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Tokyo',
    'locale' => getenv('APP_LOCALE') ?: 'ja',
    'fallback_locale' => getenv('APP_FALLBACK_LOCALE') ?: 'en',
    
    // セッション設定
    'session' => [
        'lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 120),
        'secure' => filter_var(getenv('SESSION_SECURE'), FILTER_VALIDATE_BOOLEAN) ?: false,
        'http_only' => filter_var(getenv('SESSION_HTTP_ONLY'), FILTER_VALIDATE_BOOLEAN) ?: true,
        'same_site' => getenv('SESSION_SAME_SITE') ?: 'lax',
    ],
    
    // パフォーマンス設定
    'performance' => [
        'max_execution_time' => (int)(getenv('MAX_EXECUTION_TIME') ?: 30),
        'memory_limit' => getenv('MEMORY_LIMIT') ?: '256M',
        'upload_max_filesize' => getenv('UPLOAD_MAX_FILESIZE') ?: '10M',
        'post_max_size' => getenv('POST_MAX_SIZE') ?: '10M',
    ],
    
    // メール設定
    'mail' => [
        'mailer' => getenv('MAIL_MAILER') ?: 'smtp',
        'host' => getenv('MAIL_HOST') ?: 'localhost',
        'port' => (int)(getenv('MAIL_PORT') ?: 587),
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'encryption' => getenv('MAIL_ENCRYPTION') ?: null,
        'from' => [
            'address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@pocketnavi.com',
            'name' => getenv('MAIL_FROM_NAME') ?: 'PocketNavi',
        ],
    ],
    
    // 外部API設定
    'apis' => [
        'google_maps' => [
            'api_key' => getenv('GOOGLE_MAPS_API_KEY') ?: '',
        ],
        'youtube' => [
            'api_key' => getenv('YOUTUBE_API_KEY') ?: '',
        ],
    ],
    
    // アプリケーション固有の設定
    'features' => [
        'enable_search' => true,
        'enable_filters' => true,
        'enable_pagination' => true,
        'enable_caching' => true,
        'enable_logging' => true,
    ],
    
    // セキュリティ設定
    'security' => [
        'csrf_protection' => true,
        'xss_protection' => true,
        'content_security_policy' => true,
        'rate_limiting' => false, // 将来の拡張用
    ],
];
