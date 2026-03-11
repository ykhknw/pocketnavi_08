#!/bin/bash

# 検索履歴クリーンアップ用のcronジョブ設定スクリプト
# 
# 使用方法:
# chmod +x scripts/setup_cron_jobs.sh
# ./scripts/setup_cron_jobs.sh

# プロジェクトのルートディレクトリを取得
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CLEANUP_SCRIPT="$PROJECT_ROOT/scripts/cleanup_search_history.php"

echo "=== 検索履歴クリーンアップ用cronジョブ設定 ==="
echo "プロジェクトルート: $PROJECT_ROOT"
echo "クリーンアップスクリプト: $CLEANUP_SCRIPT"
echo ""

# スクリプトの存在確認
if [ ! -f "$CLEANUP_SCRIPT" ]; then
    echo "❌ エラー: クリーンアップスクリプトが見つかりません: $CLEANUP_SCRIPT"
    exit 1
fi

# スクリプトに実行権限を付与
chmod +x "$CLEANUP_SCRIPT"

echo "📋 推奨されるcronジョブ設定:"
echo ""
echo "# 検索履歴のクリーンアップ（毎週日曜日の午前2時）"
echo "0 2 * * 0 cd $PROJECT_ROOT && php $CLEANUP_SCRIPT 90 --archive >> /var/log/search_cleanup.log 2>&1"
echo ""
echo "# 統計情報の確認（毎月1日の午前1時）"
echo "0 1 1 * * cd $PROJECT_ROOT && php $CLEANUP_SCRIPT --stats >> /var/log/search_stats.log 2>&1"
echo ""

echo "🔧 cronジョブを設定するには:"
echo "1. crontab -e を実行"
echo "2. 上記の設定を追加"
echo "3. ログディレクトリの権限を確認:"
echo "   sudo mkdir -p /var/log"
echo "   sudo chown \$(whoami):\$(whoami) /var/log/search_*.log"
echo ""

echo "📊 手動実行の例:"
echo "# 統計情報を表示"
echo "cd $PROJECT_ROOT && php $CLEANUP_SCRIPT --stats"
echo ""
echo "# 90日より古いデータをアーカイブしてから削除"
echo "cd $PROJECT_ROOT && php $CLEANUP_SCRIPT 90 --archive"
echo ""
echo "# 30日より古いデータを削除（アーカイブなし）"
echo "cd $PROJECT_ROOT && php $CLEANUP_SCRIPT 30"
echo ""

echo "⚠️  注意事項:"
echo "- 初回実行前にデータベースのバックアップを取ることを推奨します"
echo "- 本番環境では、まず --stats オプションでデータ量を確認してください"
echo "- アーカイブ機能を使用する場合、十分なディスク容量を確保してください"
echo ""

# 現在のcronジョブを表示
echo "📋 現在のcronジョブ:"
crontab -l 2>/dev/null | grep -E "(search|cleanup)" || echo "検索履歴関連のcronジョブは設定されていません"
echo ""

echo "✅ 設定完了"
