<?php
/**
 * レート制限設定ファイル
 * API呼び出し制限、ログイン試行制限の設定
 */

return [
    // API呼び出し制限設定
    'api' => [
        // 検索API制限
        'search' => [
            'limit' => 30,           // 1分間に30回
            'window' => 60,          // 60秒間
            'block_duration' => 300, // 5分間ブロック
            'burst_limit' => 10,     // 10秒間に10回まで
            'burst_window' => 10     // バースト制限の時間窓
        ],
        
        // 一般API制限
        'general' => [
            'limit' => 60,           // 1分間に60回
            'window' => 60,          // 60秒間
            'block_duration' => 300, // 5分間ブロック
            'burst_limit' => 20,     // 10秒間に20回まで
            'burst_window' => 10     // バースト制限の時間窓
        ],
        
        // 管理API制限
        'admin' => [
            'limit' => 20,           // 1分間に20回
            'window' => 60,          // 60秒間
            'block_duration' => 600, // 10分間ブロック
            'burst_limit' => 5,      // 10秒間に5回まで
            'burst_window' => 10     // バースト制限の時間窓
        ],
        
        // 検索件数API制限
        'search_count' => [
            'limit' => 50,           // 1分間に50回
            'window' => 60,          // 60秒間
            'block_duration' => 300, // 5分間ブロック
            'burst_limit' => 15,     // 10秒間に15回まで
            'burst_window' => 10     // バースト制限の時間窓
        ]
    ],
    
    // ログイン試行制限設定
    'login' => [
        'max_attempts' => 5,         // 5回まで
        'lockout_duration' => 900,   // 15分間ロック
        'admin_notification' => true, // 管理者通知
        'reset_attempts_after' => 3600 // 1時間後に試行回数をリセット
    ],
    
    // 環境別設定
    'environments' => [
        'production' => [
            'strict_mode' => true,   // 厳格モード
            'log_all_attempts' => true, // すべての試行をログ
            'auto_unblock' => false  // 自動ブロック解除なし
        ],
        'development' => [
            'strict_mode' => false,  // 緩いモード
            'log_all_attempts' => false, // ログを最小限に
            'auto_unblock' => true   // 自動ブロック解除あり
        ]
    ],
    
    // 警告レベル設定
    'warning_levels' => [
        'search_api' => [
            'warning' => 20,         // 20回/分で警告
            'critical' => 25,        // 25回/分でクリティカル
            'emergency' => 30        // 30回/分で緊急
        ],
        'general_api' => [
            'warning' => 40,         // 40回/分で警告
            'critical' => 50,        // 50回/分でクリティカル
            'emergency' => 60        // 60回/分で緊急
        ],
        'login' => [
            'warning' => 3,          // 3回失敗で警告
            'critical' => 4,         // 4回失敗でクリティカル
            'emergency' => 5         // 5回失敗で緊急
        ]
    ],
    
    // 日次制限設定
    'daily_limits' => [
        'search_api' => 1000,        // 1日1000回
        'general_api' => 2000,       // 1日2000回
        'login_attempts' => 50       // 1日50回
    ],
    
    // 特別なIP制限（ホワイトリスト・ブラックリスト）
    'ip_restrictions' => [
        'whitelist' => [
            // '127.0.0.1',           // ローカルホスト
            // '192.168.1.0/24'       // ローカルネットワーク
        ],
        'blacklist' => [
            // 攻撃元IPをここに追加
        ],
        'whitelist_bypass' => true,  // ホワイトリストは制限をバイパス
        'blacklist_deny' => true     // ブラックリストは完全拒否
    ],
    
    // 通知設定
    'notifications' => [
        'email' => [
            'enabled' => true,
            'admin_email' => 'admin@kenchikuka.com',
            'send_on_block' => true,     // ブロック時に通知
            'send_on_attack' => true,    // 攻撃検知時に通知
            'send_daily_report' => false // 日次レポート
        ],
        'log' => [
            'enabled' => true,
            'log_file' => 'logs/rate_limit.log',
            'log_level' => 'INFO',       // DEBUG, INFO, WARNING, ERROR
            'max_file_size' => 10485760, // 10MB
            'max_files' => 5
        ]
    ],
    
    // 統計・監視設定
    'monitoring' => [
        'collect_stats' => true,     // 統計収集
        'stats_retention' => 2592000, // 30日間保持
        'alert_thresholds' => [
            'high_block_rate' => 0.1,    // 10%以上のブロック率
            'high_error_rate' => 0.05,   // 5%以上のエラー率
            'unusual_traffic' => 2.0     // 通常の2倍以上のトラフィック
        ]
    ],
    
    // カスタム制限ルール
    'custom_rules' => [
        // 特定の時間帯での制限
        'time_based' => [
            'peak_hours' => [
                'start' => '09:00',
                'end' => '18:00',
                'multiplier' => 0.5      // ピーク時間は制限を半分に
            ],
            'off_hours' => [
                'start' => '22:00',
                'end' => '06:00',
                'multiplier' => 2.0      // オフ時間は制限を2倍に
            ]
        ],
        
        // 地域別制限
        'geo_restrictions' => [
            'enabled' => false,
            'allowed_countries' => ['JP'], // 日本からのアクセスのみ
            'blocked_countries' => []      // ブロックする国
        ]
    ]
];
