<?php
// ログ設定ファイル

return [
    'default_level' => getenv('LOG_LEVEL') ?: 'INFO',
    'channels' => [
        'application' => [
            'driver' => 'file',
            'path' => 'logs/application.log',
            'level' => 'DEBUG',
            'max_size' => '5MB',
            'max_files' => 10
        ],
        'database' => [
            'driver' => 'file',
            'path' => 'logs/database.log',
            'level' => 'INFO',
            'max_size' => '5MB',
            'max_files' => 10
        ],
        'security' => [
            'driver' => 'file',
            'path' => 'logs/security.log',
            'level' => 'WARNING',
            'max_size' => '5MB',
            'max_files' => 30
        ],
        'performance' => [
            'driver' => 'file',
            'path' => 'logs/performance.log',
            'level' => 'INFO',
            'max_size' => '5MB',
            'max_files' => 10
        ],
        'error' => [
            'driver' => 'file',
            'path' => 'logs/error.log',
            'level' => 'ERROR',
            'max_size' => '10MB',
            'max_files' => 30
        ]
    ],
    'fallback' => [
        'driver' => 'error_log',
        'level' => 'ERROR'
    ]
];
