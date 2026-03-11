# 検索履歴クリーンアップ用のタスクスケジューラー設定スクリプト (Windows)
# 
# 使用方法:
# PowerShell -ExecutionPolicy Bypass -File scripts/setup_cron_jobs.ps1

# プロジェクトのルートディレクトリを取得
$PROJECT_ROOT = Split-Path -Parent $PSScriptRoot
$CLEANUP_SCRIPT = Join-Path $PROJECT_ROOT "scripts\cleanup_search_history.php"

Write-Host "=== 検索履歴クリーンアップ用タスクスケジューラー設定 ===" -ForegroundColor Green
Write-Host "プロジェクトルート: $PROJECT_ROOT"
Write-Host "クリーンアップスクリプト: $CLEANUP_SCRIPT"
Write-Host ""

# スクリプトの存在確認
if (-not (Test-Path $CLEANUP_SCRIPT)) {
    Write-Host "❌ エラー: クリーンアップスクリプトが見つかりません: $CLEANUP_SCRIPT" -ForegroundColor Red
    exit 1
}

Write-Host "📋 推奨されるタスクスケジューラー設定:" -ForegroundColor Yellow
Write-Host ""
Write-Host "# 検索履歴のクリーンアップ（毎週日曜日の午前2時）" -ForegroundColor Cyan
Write-Host "schtasks /create /tn 'SearchHistoryCleanup' /tr 'php $CLEANUP_SCRIPT 90 --archive' /sc weekly /d SUN /st 02:00 /f"
Write-Host ""
Write-Host "# 統計情報の確認（毎月1日の午前1時）" -ForegroundColor Cyan
Write-Host "schtasks /create /tn 'SearchHistoryStats' /tr 'php $CLEANUP_SCRIPT --stats' /sc monthly /d 1 /st 01:00 /f"
Write-Host ""

Write-Host "🔧 タスクスケジューラーを設定するには:" -ForegroundColor Yellow
Write-Host "1. 管理者権限でPowerShellを開く"
Write-Host "2. 上記のschtasksコマンドを実行"
Write-Host "3. タスクスケジューラーで設定を確認"
Write-Host ""

Write-Host "📊 手動実行の例:" -ForegroundColor Yellow
Write-Host "# 統計情報を表示"
Write-Host "cd '$PROJECT_ROOT'; php '$CLEANUP_SCRIPT' --stats"
Write-Host ""
Write-Host "# 90日より古いデータをアーカイブしてから削除"
Write-Host "cd '$PROJECT_ROOT'; php '$CLEANUP_SCRIPT' 90 --archive"
Write-Host ""
Write-Host "# 30日より古いデータを削除（アーカイブなし）"
Write-Host "cd '$PROJECT_ROOT'; php '$CLEANUP_SCRIPT' 30"
Write-Host ""

Write-Host "⚠️  注意事項:" -ForegroundColor Red
Write-Host "- 初回実行前にデータベースのバックアップを取ることを推奨します"
Write-Host "- 本番環境では、まず --stats オプションでデータ量を確認してください"
Write-Host "- アーカイブ機能を使用する場合、十分なディスク容量を確保してください"
Write-Host ""

# 現在のタスクを表示
Write-Host "📋 現在の検索履歴関連タスク:" -ForegroundColor Yellow
try {
    $tasks = schtasks /query /fo csv | ConvertFrom-Csv | Where-Object { $_.TaskName -like "*Search*" -or $_.TaskName -like "*Cleanup*" }
    if ($tasks) {
        $tasks | ForEach-Object { Write-Host "  $($_.TaskName): $($_.Status)" }
    } else {
        Write-Host "検索履歴関連のタスクは設定されていません"
    }
} catch {
    Write-Host "タスクの確認中にエラーが発生しました"
}
Write-Host ""

Write-Host "✅ 設定完了" -ForegroundColor Green
