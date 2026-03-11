<?php
// キャッシュ設定ファイル

return [
    'default' => getenv('CACHE_DRIVER') ?: 'file',
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => 'cache/',
            'permission' => 0755,
        ],
        
        'array' => [
            'driver' => 'array',
        ],
        
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
        ],
        
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],
    ],
    
    'prefix' => getenv('CACHE_PREFIX') ?: 'pocketnavi_',
    
    // キャッシュ設定
    'ttl' => [
        'default' => 3600, // 1時間
        'search_results' => 1800, // 30分
        'building_data' => 7200, // 2時間
        'architect_data' => 7200, // 2時間
        'static_content' => 86400, // 24時間
    ],
];
