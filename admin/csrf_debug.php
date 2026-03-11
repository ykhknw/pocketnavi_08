<?php
/**
 * CSRF Token Debug Page
 * 
 * CSRFトークンの状態を確認・デバッグするための管理画面
 * 
 * @package PocketNavi
 * @subpackage Admin
 */

// セッション開始
session_start();

// 必要なファイルを読み込み
require_once __DIR__ . '/../src/Utils/CSRFHelper.php';

// 基本的なセキュリティチェック
$isLocalhost = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost';
if (!$isLocalhost) {
    die('This debug page is only available on localhost');
}

// アクション処理
$action = $_GET['action'] ?? '';
$message = '';
$messageType = '';

switch ($action) {
    case 'clear_tokens':
        $csrf = CSRFProtection::getInstance();
        $csrf->clearAllTokens();
        $message = 'すべてのCSRFトークンをクリアしました';
        $messageType = 'success';
        break;
        
    case 'generate_token':
        $actionName = $_POST['action_name'] ?? 'test';
        $token = getCSRFToken($actionName);
        $message = "アクション '{$actionName}' のトークンを生成しました: " . substr($token, 0, 16) . '...';
        $messageType = 'success';
        break;
        
    case 'validate_token':
        $token = $_POST['token'] ?? '';
        $actionName = $_POST['action_name'] ?? 'test';
        $isValid = validateCSRFToken($token, $actionName);
        $message = $isValid ? 'トークンは有効です' : 'トークンは無効です';
        $messageType = $isValid ? 'success' : 'danger';
        break;
}

// デバッグ情報を取得
$debugInfo = getCSRFDebugInfo();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSRF Token Debug - PocketNavi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .token-display {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            word-break: break-all;
        }
        .status-badge {
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>CSRF Token Debug</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>現在のトークン状況</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>トークン数:</strong> <?php echo $debugInfo['token_count']; ?></p>
                        
                        <?php if (!empty($debugInfo['tokens'])): ?>
                            <h6>トークン詳細:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>アクション</th>
                                            <th>作成日時</th>
                                            <th>有効期限</th>
                                            <th>状態</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($debugInfo['tokens'] as $action => $info): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($action); ?></code></td>
                                                <td><?php echo htmlspecialchars($info['created']); ?></td>
                                                <td><?php echo htmlspecialchars($info['expires']); ?></td>
                                                <td>
                                                    <?php if ($info['is_expired']): ?>
                                                        <span class="badge bg-danger status-badge">期限切れ</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success status-badge">有効</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">トークンがありません</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>トークン操作</h5>
                    </div>
                    <div class="card-body">
                        <!-- トークン生成 -->
                        <form method="post" action="?action=generate_token" class="mb-3">
                            <div class="mb-2">
                                <label for="action_name" class="form-label">アクション名:</label>
                                <input type="text" class="form-control" id="action_name" name="action_name" value="test" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">トークンを生成</button>
                        </form>
                        
                        <!-- トークン検証 -->
                        <form method="post" action="?action=validate_token" class="mb-3">
                            <div class="mb-2">
                                <label for="validate_action_name" class="form-label">アクション名:</label>
                                <input type="text" class="form-control" id="validate_action_name" name="action_name" value="test" required>
                            </div>
                            <div class="mb-2">
                                <label for="validate_token" class="form-label">トークン:</label>
                                <input type="text" class="form-control" id="validate_token" name="token" placeholder="検証するトークンを入力" required>
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm">トークンを検証</button>
                        </form>
                        
                        <!-- 全トークンクリア -->
                        <form method="get" action="?action=clear_tokens" onsubmit="return confirm('すべてのトークンをクリアしますか？')">
                            <button type="submit" class="btn btn-danger btn-sm">全トークンをクリア</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>現在のセッション情報</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>セッションID:</h6>
                                <div class="token-display"><?php echo session_id(); ?></div>
                            </div>
                            <div class="col-md-6">
                                <h6>セッション開始時刻:</h6>
                                <div class="token-display"><?php echo date('Y-m-d H:i:s', $_SESSION['__CSRF_START_TIME'] ?? time()); ?></div>
                            </div>
                        </div>
                        
                        <h6 class="mt-3">セッション変数:</h6>
                        <div class="token-display">
                            <pre><?php print_r($_SESSION); ?></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="../index.php" class="btn btn-secondary">メインページに戻る</a>
            <a href="index.php" class="btn btn-outline-secondary">管理画面に戻る</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
