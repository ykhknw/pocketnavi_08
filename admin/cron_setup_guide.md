# キャッシュ自動化のcronジョブ設定ガイド

## 概要
このガイドでは、PocketNaviのキャッシュ管理を自動化するためのcronジョブの設定方法を説明します。

## 作成されたスクリプト

### 1. `cleanup_old_cache.php`
- **目的**: 7日以上古いキャッシュファイルを削除
- **実行頻度**: 毎日
- **推奨時間**: 午前2時

### 2. `limit_cache_files.php`
- **目的**: キャッシュファイル数を50,000件に制限
- **実行頻度**: 毎週
- **推奨時間**: 日曜日午前3時

### 3. `cache_report.php`
- **目的**: キャッシュの統計レポートを生成
- **実行頻度**: 毎週
- **推奨時間**: 月曜日午前4時

### 4. `cache_maintenance.php`
- **目的**: 統合メンテナンス（古いファイル削除 + ファイル数制限）
- **実行頻度**: 毎日
- **推奨時間**: 午前1時

## cronジョブの設定例

### HETEMLでの設定方法

1. **コントロールパネルにログイン**
2. **「cron設定」を選択**
3. **以下の設定を追加**

```bash
# 毎日午前1時: 統合メンテナンス
0 1 * * * /usr/local/bin/php /home/users/1/yukihiko/web/kenchikuka.com_new/admin/cache_maintenance.php

# 毎日午前2時: 古いキャッシュ削除
0 2 * * * /usr/local/bin/php /home/users/1/yukihiko/web/kenchikuka.com_new/admin/cleanup_old_cache.php

# 毎週日曜日午前3時: ファイル数制限
0 3 * * 0 /usr/local/bin/php /home/users/1/yukihiko/web/kenchikuka.com_new/admin/limit_cache_files.php

# 毎週月曜日午前4時: 統計レポート
0 4 * * 1 /usr/local/bin/php /home/users/1/yukihiko/web/kenchikuka.com_new/admin/cache_report.php
```

### 設定の説明

- `0 1 * * *`: 毎日午前1時
- `0 2 * * *`: 毎日午前2時
- `0 3 * * 0`: 毎週日曜日午前3時
- `0 4 * * 1`: 毎週月曜日午前4時

## ログファイルの確認

各スクリプトは以下のログファイルに実行結果を記録します：

- `admin/cache_maintenance.log`: 統合メンテナンスのログ
- `admin/cache_cleanup.log`: 古いキャッシュ削除のログ
- `admin/cache_limit.log`: ファイル数制限のログ
- `admin/cache_report.log`: 統計レポートのログ

## 手動実行のテスト

cronジョブを設定する前に、手動でスクリプトを実行してテストしてください：

```bash
# 統合メンテナンスのテスト
php admin/cache_maintenance.php

# 古いキャッシュ削除のテスト
php admin/cleanup_old_cache.php

# ファイル数制限のテスト
php admin/limit_cache_files.php

# 統計レポートのテスト
php admin/cache_report.php
```

## 注意事項

1. **PHPのパス**: HETEMLでは `/usr/local/bin/php` を使用
2. **ファイルパス**: 絶対パスを使用してください
3. **権限**: スクリプトファイルに実行権限があることを確認
4. **ログローテーション**: ログファイルが大きくなりすぎないよう定期的に確認

## トラブルシューティング

### よくある問題

1. **「command not found」エラー**
   - PHPのパスが正しいか確認
   - スクリプトファイルのパスが正しいか確認

2. **権限エラー**
   - ファイルの実行権限を確認
   - ディレクトリの書き込み権限を確認

3. **ログファイルが作成されない**
   - ディレクトリの書き込み権限を確認
   - ディスク容量を確認

### ログの確認方法

```bash
# 最新のログを確認
tail -f admin/cache_maintenance.log

# エラーのみを確認
grep "エラー" admin/cache_maintenance.log
```

## 推奨設定

### 本番環境での推奨設定

1. **統合メンテナンス**: 毎日午前1時
2. **古いキャッシュ削除**: 毎日午前2時
3. **ファイル数制限**: 毎週日曜日午前3時
4. **統計レポート**: 毎週月曜日午前4時

### 開発環境での推奨設定

1. **統合メンテナンス**: 毎日午前1時
2. **古いキャッシュ削除**: 毎日午前2時
3. **ファイル数制限**: 毎日午前3時（より頻繁に実行）
4. **統計レポート**: 毎日午前4時（より頻繁に実行）

## 監視とアラート

cronジョブの実行状況を監視するために、以下の方法を推奨します：

1. **ログファイルの定期確認**
2. **メール通知の設定**（HETEMLの機能を使用）
3. **Web管理画面での手動確認**

## 更新とメンテナンス

- スクリプトの更新時は、cronジョブを一時停止してから更新
- ログファイルの定期的なクリーンアップ
- 設定ファイルの定期的な見直し
