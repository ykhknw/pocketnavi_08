# HETEML環境でのcron設定ガイド

## HETEMLのcron機能について

HETEMLでは以下の制約があります：
- **実行頻度**: 最大1日1回
- **実行時間**: 5分以内
- **メモリ制限**: 128MB
- **実行時間制限**: 30秒

## 推奨設定

### 1. 基本的なcron設定

HETEMLの管理画面で以下の設定を行ってください：

```
# 毎日午前2時にクリーンアップ実行
0 2 * * * /usr/local/bin/php /home/your-account/public_html/scripts/heteml_cleanup_search_history.php 60 --archive
```

### 2. 設定手順

1. **HETEML管理画面にログイン**
2. **「サーバー管理」→「cron設定」** を選択
3. **新しいcronジョブを追加**
4. **以下の設定を入力**：
   - 実行時間: `0 2 * * *` (毎日午前2時)
   - 実行コマンド: `/usr/local/bin/php /home/your-account/public_html/scripts/heteml_cleanup_search_history.php 60 --archive`

### 3. ログファイルの設定

HETEMLでは標準出力がログとして保存されます。エラーログを確認するには：

```bash
# エラーログの確認
tail -f /home/your-account/logs/error.log
```

## 代替案: Web経由での定期実行

HETEMLのcron機能が制限的すぎる場合は、外部サービスを利用できます：

### 1. UptimeRobot を使用した定期実行

```
# 毎日午前2時にアクセス
https://your-domain.com/admin/heteml_cleanup.php?key=your_secret_key&action=cleanup
```

### 2. GitHub Actions を使用した定期実行

```yaml
name: Search History Cleanup
on:
  schedule:
    - cron: '0 17 * * *'  # 毎日午後5時（JST午前2時）
  workflow_dispatch:

jobs:
  cleanup:
    runs-on: ubuntu-latest
    steps:
      - name: Trigger Cleanup
        run: |
          curl -X POST "https://your-domain.com/admin/heteml_cleanup.php" \
            -H "Content-Type: application/x-www-form-urlencoded" \
            -d "key=your_secret_key&action=cleanup"
```

## 手動実行方法

### 1. SSH経由（可能な場合）

```bash
# SSHでサーバーに接続
ssh your-account@your-domain.com

# クリーンアップ実行
cd /home/your-account/public_html
php scripts/heteml_cleanup_search_history.php 60 --archive
```

### 2. Web経由

```
# 統計情報確認
https://your-domain.com/admin/heteml_cleanup.php?key=your_secret_key&action=stats

# クリーンアップ実行
https://your-domain.com/admin/heteml_cleanup.php?key=your_secret_key&action=cleanup
```

## 監視とアラート

### 1. ログ監視

HETEMLのログファイルを定期的に確認：

```bash
# 最新のログを確認
tail -20 /home/your-account/logs/error.log

# 検索履歴関連のログを確認
grep "search_cleanup" /home/your-account/logs/error.log
```

### 2. データベース監視

Web管理画面で定期的に統計情報を確認：

```
https://your-domain.com/admin/search_history_management.php?key=admin_search_history_2024
```

## トラブルシューティング

### よくある問題

1. **cronジョブが実行されない**
   - HETEMLのcron機能が有効になっているか確認
   - パスが正しいか確認
   - ログファイルでエラーメッセージを確認

2. **実行時間が30秒を超える**
   - バッチサイズを小さくする（500件に変更）
   - 保持期間を短くする（30日）
   - アーカイブ機能を無効にする

3. **メモリ不足エラー**
   - バッチサイズをさらに小さくする（250件）
   - 不要なデータを事前に削除

### 緊急時の対応

データベースサイズが制限に近づいた場合：

```bash
# 緊急クリーンアップ（30日保持）
php scripts/heteml_cleanup_search_history.php 30

# アーカイブなしでクリーンアップ
php scripts/heteml_cleanup_search_history.php 60
```

## 設定例

### 本番環境推奨設定

```bash
# 毎日午前2時、60日保持、アーカイブ有効
0 2 * * * /usr/local/bin/php /home/your-account/public_html/scripts/heteml_cleanup_search_history.php 60 --archive
```

### 開発環境推奨設定

```bash
# 毎日午前3時、30日保持、アーカイブ無効
0 3 * * * /usr/local/bin/php /home/your-account/public_html/scripts/heteml_cleanup_search_history.php 30
```

## 注意事項

1. **初回実行前のバックアップ**
   - データベースのバックアップを必ず取得
   - 重要なデータの確認

2. **段階的な実行**
   - まず統計情報でデータ量を確認
   - 短い保持期間から開始
   - 問題がないことを確認してから本格運用

3. **ログの定期確認**
   - 週1回はログファイルを確認
   - エラーが発生していないかチェック
