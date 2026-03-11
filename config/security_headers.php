<?php
/**
 * セキュリティヘッダー設定ファイル
 * 環境別のセキュリティヘッダー設定を管理
 */
return [
    'production' => [
        'permissions_policy' => [
            'geolocation' => ['*'],
            'microphone' => [],
            'camera' => [],
            'payment' => [],
            'usb' => [],
            'magnetometer' => [],
            'gyroscope' => [],
            'fullscreen' => []
        ],
        'csp' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "https://cdn.jsdelivr.net", "https://unpkg.com", "https://www.googletagmanager.com", "https://www.google-analytics.com"],
            'style-src' => ["'self'", "'unsafe-inline'", "https://cdn.jsdelivr.net", "https://unpkg.com"],
            'img-src' => ["'self'", "data:", "https:", "*.openstreetmap.org"],
            'font-src' => ["'self'", "https://cdn.jsdelivr.net", "https://unpkg.com"],
            'connect-src' => ["'self'", "https://www.google-analytics.com", "https://analytics.google.com"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"]
        ],
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'hsts' => [
            'enabled' => true,
            'max_age' => 31536000,
            'include_subdomains' => true,
            'preload' => true
        ]
    ],
    'development' => [
        'permissions_policy' => [
            'geolocation' => ['*'],
            'microphone' => [],
            'camera' => [],
            'payment' => [],
            'usb' => [],
            'magnetometer' => [],
            'gyroscope' => [],
            'fullscreen' => []
        ],
        'csp' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", "https:", "https://www.googletagmanager.com", "https://www.google-analytics.com"],
            'style-src' => ["'self'", "'unsafe-inline'", "https:"],
            'img-src' => ["'self'", "data:", "https:", "blob:"],
            'font-src' => ["'self'", "https:"],
            'connect-src' => ["'self'", "https:", "https://www.google-analytics.com", "https://analytics.google.com"],
            'frame-ancestors' => ["'none'"]
        ],
        'x_frame_options' => 'SAMEORIGIN',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'hsts' => [
            'enabled' => false
        ]
    ]
];
?>
