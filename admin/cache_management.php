<?php
/**
 * キャッシュ管理画面
 * 人気検索キャッシュの状態確認と管理
 */

require_once __DIR__ . '/../config/database_unified.php';
require_once __DIR__ . '/../src/Services/PopularSearchCache.php';

// 管理者認証（必要に応じて実装）
// if (!isAdmin()) { die('Access denied'); }

$cacheService = new PopularSearchCache();
$message = '';
$error = '';

// アクション処理
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'clear_cache':
            try {
                $cacheService->clearCache();
                $message = 'キャッシュをクリアしました。';
            } catch (Exception $e) {
                $error = 'キャッシュクリアに失敗しました: ' . $e->getMessage();
            }
            break;
            
        case 'update_cache':
            try {
                // 各検索タイプのキャッシュを強制更新
                $searchTypes = ['', 'architect', 'building', 'prefecture', 'text'];
                foreach ($searchTypes as $searchType) {
                    $cacheService->getPopularSearches(1, 20, '', $searchType);
                }
                $message = 'キャッシュを更新しました。';
            } catch (Exception $e) {
                $error = 'キャッシュ更新に失敗しました: ' . $e->getMessage();
            }
            break;
    }
}

// キャッシュ状態を取得
$status = $cacheService->getCacheStatus();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キャッシュ管理 - PocketNavi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>人気検索キャッシュ管理</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>キャッシュ状態</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <th>状態</th>
                                <td>
                                    <span class="badge bg-<?php echo $status['status'] === 'valid' ? 'success' : 'warning'; ?>">
                                        <?php echo $status['status'] === 'valid' ? '有効' : '無効'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>最終更新</th>
                                <td><?php echo htmlspecialchars($status['last_update']); ?></td>
                            </tr>
                            <tr>
                                <th>データ数</th>
                                <td><?php echo $status['data_count']; ?> 件</td>
                            </tr>
                            <tr>
                                <th>経過時間</th>
                                <td><?php echo $status['age']; ?> 秒</td>
                            </tr>
                            <tr>
                                <th>有効期限</th>
                                <td><?php echo $status['max_age']; ?> 秒</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>キャッシュ操作</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="update_cache">
                            <button type="submit" class="btn btn-primary">キャッシュを更新</button>
                        </form>
                        
                        <form method="post" class="d-inline ms-2">
                            <input type="hidden" name="action" value="clear_cache">
                            <button type="submit" class="btn btn-warning" 
                                    onclick="return confirm('キャッシュをクリアしますか？')">
                                キャッシュをクリア
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>キャッシュ情報</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>キャッシュファイル:</strong><br>
                        <code>cache/popular_searches.php</code></p>
                        
                        <p><strong>有効期限:</strong><br>
                        30分</p>
                        
                        <p><strong>更新方法:</strong><br>
                        - 手動更新（この画面）<br>
                        - 定期更新（cron）<br>
                        <small class="text-muted">※自動更新（アクセス時）は無効化されています</small></p>
                        
                        <hr>
                        
                        <h6>定期更新の設定</h6>
                        <p>以下のコマンドをcronに設定してください：</p>
                        <code>*/30 * * * * php <?php echo __DIR__ . '/../scripts/update_popular_searches.php'; ?></code>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="/admin/" class="btn btn-secondary">管理画面に戻る</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
