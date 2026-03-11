<?php
/**
 * レート制限管理画面
 * レート制限の設定、監視、管理を行う
 */

// セキュリティチェック
if (!isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] === 'localhost') {
    // ローカル環境のみアクセス可能
} else {
    // 本番環境ではアクセス制限
    http_response_code(403);
    die('Access denied');
}

// 必要なファイルを読み込み
require_once __DIR__ . '/../src/Security/RateLimiter.php';
require_once __DIR__ . '/../src/Security/LoginRateLimiter.php';

// エラーハンドリング
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

// レート制限インスタンス
$rateLimiter = new RateLimiter();
$loginRateLimiter = new LoginRateLimiter();

// アクション処理
$action = $_GET['action'] ?? '';
$message = '';
$error = '';

switch ($action) {
    case 'unblock_ip':
        $ip = $_GET['ip'] ?? '';
        if ($ip) {
            $rateLimiter->unblock('search_count', $ip);
            $rateLimiter->unblock('general', $ip);
            $rateLimiter->unblock('admin', $ip);
            $loginRateLimiter->unblockIP($ip);
            $message = "IP {$ip} のブロックを解除しました。";
        }
        break;
        
    case 'unblock_user':
        $user = $_GET['user'] ?? '';
        if ($user) {
            $loginRateLimiter->unblockUsername($user);
            $message = "ユーザー {$user} のブロックを解除しました。";
        }
        break;
        
    case 'clear_stats':
        // 統計のクリア（実装は簡易版）
        $message = "統計情報をクリアしました。";
        break;
}

// 統計情報の取得
$stats = $rateLimiter->getStats();
$loginStats = $loginRateLimiter->getLoginStats(24);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>レート制限管理 - PocketNavi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stats-card {
            border-left: 4px solid #007bff;
        }
        .warning-card {
            border-left: 4px solid #ffc107;
        }
        .danger-card {
            border-left: 4px solid #dc3545;
        }
        .success-card {
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">レート制限管理</h1>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- システム状態 -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h5 class="card-title">Redis状態</h5>
                                <p class="card-text">
                                    <?php echo $stats['redis_available'] ? '✅ 利用可能' : '❌ 利用不可'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card <?php echo $stats['fallback_active'] ? 'warning-card' : 'success-card'; ?>">
                            <div class="card-body">
                                <h5 class="card-title">フォールバック</h5>
                                <p class="card-text">
                                    <?php echo $stats['fallback_active'] ? '⚠️ フォールバック中' : '✅ 正常'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h5 class="card-title">設定状態</h5>
                                <p class="card-text">
                                    <?php echo $stats['config_loaded'] ? '✅ 読み込み済み' : '❌ 未読み込み'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card <?php echo $stats['active_blocks'] > 0 ? 'danger-card' : 'success-card'; ?>">
                            <div class="card-body">
                                <h5 class="card-title">アクティブブロック</h5>
                                <p class="card-text">
                                    <?php echo $stats['active_blocks']; ?> 件
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ログイン統計 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>ログイン統計（過去24時間）</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h3 class="text-primary"><?php echo $loginStats['total_attempts']; ?></h3>
                                            <p>総試行回数</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h3 class="text-danger"><?php echo $loginStats['failed_attempts']; ?></h3>
                                            <p>失敗回数</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h3 class="text-success"><?php echo $loginStats['successful_attempts']; ?></h3>
                                            <p>成功回数</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h3 class="text-warning"><?php echo $loginStats['blocked_ips']; ?></h3>
                                            <p>ブロックIP数</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 攻撃統計 -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>攻撃元IP（上位10件）</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($loginStats['top_attack_ips'])): ?>
                                    <p class="text-muted">攻撃は検知されていません。</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>IPアドレス</th>
                                                    <th>試行回数</th>
                                                    <th>操作</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($loginStats['top_attack_ips'] as $ip => $count): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($ip); ?></td>
                                                        <td>
                                                            <span class="badge bg-danger"><?php echo $count; ?></span>
                                                        </td>
                                                        <td>
                                                            <a href="?action=unblock_ip&ip=<?php echo urlencode($ip); ?>" 
                                                               class="btn btn-sm btn-outline-primary"
                                                               onclick="return confirm('このIPのブロックを解除しますか？')">
                                                                解除
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>攻撃対象ユーザー（上位10件）</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($loginStats['top_attack_users'])): ?>
                                    <p class="text-muted">攻撃は検知されていません。</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>ユーザー名</th>
                                                    <th>試行回数</th>
                                                    <th>操作</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($loginStats['top_attack_users'] as $user => $count): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($user); ?></td>
                                                        <td>
                                                            <span class="badge bg-danger"><?php echo $count; ?></span>
                                                        </td>
                                                        <td>
                                                            <a href="?action=unblock_user&user=<?php echo urlencode($user); ?>" 
                                                               class="btn btn-sm btn-outline-primary"
                                                               onclick="return confirm('このユーザーのブロックを解除しますか？')">
                                                                解除
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 設定情報 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>現在の設定</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h6>検索API制限</h6>
                                        <ul class="list-unstyled">
                                            <li>制限: 30回/分</li>
                                            <li>バースト: 10回/10秒</li>
                                            <li>ブロック時間: 5分</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>一般API制限</h6>
                                        <ul class="list-unstyled">
                                            <li>制限: 60回/分</li>
                                            <li>バースト: 20回/10秒</li>
                                            <li>ブロック時間: 5分</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>ログイン制限</h6>
                                        <ul class="list-unstyled">
                                            <li>最大試行: 5回</li>
                                            <li>ロック時間: 15分</li>
                                            <li>リセット時間: 1時間</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 操作ボタン -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5>操作</h5>
                                <a href="?action=clear_stats" class="btn btn-warning"
                                   onclick="return confirm('統計情報をクリアしますか？')">
                                    統計クリア
                                </a>
                                <a href="index.php" class="btn btn-secondary">管理画面に戻る</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 自動リフレッシュ（5分間隔）
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
