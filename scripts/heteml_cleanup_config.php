<?php
/**
 * HETEML環境用の検索履歴クリーンアップ設定
 * 
 * HETEMLの制約に合わせた最適化設定
 */

return [
    // HETEMLの制約に合わせた設定
    'heteml' => [
        // データベース制限（HETEML 20GB容量での現実的制限）
        'max_table_size_mb' => 1024,  // 検索履歴テーブル用制限（20GBの5%）
        'max_records' => 2000000,     // レコード数制限（推定）
        
        // 実行時間制限
        'max_execution_time' => 30,  // HETEMLのPHP実行時間制限
        'memory_limit' => '128M',    // メモリ制限
        
        // クリーンアップ設定（HETEML 20GB容量での現実的設定）
        'retention_days' => 30,      // 30日保持（5%制限での現実的設定）
        'batch_size' => 500,         // バッチ処理サイズ（小さく調整）
        'archive_threshold' => 3,    // アーカイブ閾値（検索回数）
        
        // ログ設定
        'log_file' => 'logs/search_cleanup.log',
        'max_log_size' => 1024 * 1024, // 1MB
    ],
    
    // 推奨される定期実行設定（HETEML 20GB容量での現実的設定）
    'cron' => [
        // HETEMLのcron機能（最大1日1回、5分以内）
        'cleanup_schedule' => '0 2 * * 3',  // 毎週水曜日午前2時（5%制限での頻繁な実行）
        'stats_schedule' => '0 1 1 * *',    // 毎月1日午前1時（月1回監視）
    ],
    
    // アラート設定（HETEML 20GB容量での現実的設定）
    'alerts' => [
        'table_size_warning' => 614,   // 614MBで警告（60%使用率）
        'table_size_critical' => 819,  // 819MBで緊急（80%使用率）
        'record_count_warning' => 1200000,  // 120万件で警告（60%使用率）
        'record_count_critical' => 1600000, // 160万件で緊急（80%使用率）
    ],
    
    // パフォーマンス最適化（HETEML 20GB容量での現実的設定）
    'optimization' => [
        'enable_index_optimization' => true,
        'enable_table_optimization' => true,
        'cleanup_frequency' => 'weekly',  // 週1回実行（5%制限での頻繁な実行）
        'archive_frequency' => 'weekly',  // 週1回アーカイブ（5%制限での頻繁な実行）
    ]
];
