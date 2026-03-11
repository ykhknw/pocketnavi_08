<?php
/**
 * SameSite Cookie Debug Page
 * 
 * SameSite Cookieの状態を確認・デバッグするための管理画面
 * 
 * @package PocketNavi
 * @subpackage Admin
 */

// セッション開始
session_start();

// 必要なファイルを読み込み
require_once __DIR__ . '/../src/Utils/SameSiteCookieHelper.php';

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
    case 'set_test_cookie':
        $cookieType = $_POST['cookie_type'] ?? 'default';
        $cookieValue = $_POST['cookie_value'] ?? 'test_value';
        
        switch ($cookieType) {
            case 'csrf':
                $result = setSameSiteCSRFTokenCookie($cookieValue);
                $message = $result ? 'CSRFトークンCookieを設定しました' : 'CSRFトークンCookieの設定に失敗しました';
                break;
            case 'auth':
                $result = setSameSiteAuthCookie('test_auth', $cookieValue);
                $message = $result ? '認証Cookieを設定しました' : '認証Cookieの設定に失敗しました';
                break;
            case 'analytics':
                $result = setSameSiteAnalyticsCookie('test_analytics', $cookieValue);
                $message = $result ? '分析Cookieを設定しました' : '分析Cookieの設定に失敗しました';
                break;
            case 'language':
                $result = setLanguageCookie($cookieValue);
                $message = $result ? '言語設定Cookieを設定しました' : '言語設定Cookieの設定に失敗しました';
                break;
            default:
                $result = getSameSiteCookieManager()->setCookie('test_cookie', $cookieValue);
                $message = $result ? 'テストCookieを設定しました' : 'テストCookieの設定に失敗しました';
        }
        
        $messageType = $result ? 'success' : 'danger';
        break;
        
    case 'delete_test_cookie':
        $cookieName = $_POST['cookie_name'] ?? 'test_cookie';
        $result = deleteSameSiteCookie($cookieName);
        $message = $result ? "Cookie '{$cookieName}' を削除しました" : "Cookie '{$cookieName}' の削除に失敗しました";
        $messageType = $result ? 'success' : 'danger';
        break;
        
    case 'regenerate_session':
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $message = 'セッションIDを再生成しました';
            $messageType = 'success';
        } else {
            $message = 'セッションが開始されていません';
            $messageType = 'warning';
        }
        break;
}

// デバッグ情報を取得
$debugInfo = getSameSiteCookieDebugInfo();
$settings = getSameSiteCookieSettings();
$validation = validateSameSiteCookieSettings();
$config = getSameSiteCookieConfig();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SameSite Cookie Debug - PocketNavi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .cookie-display {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            word-break: break-all;
            font-size: 0.9em;
        }
        .status-badge {
            font-size: 0.8em;
        }
        .config-section {
            margin-bottom: 20px;
        }
        .cookie-test {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>SameSite Cookie Debug</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- 現在の設定 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>現在のCookie設定</h5>
                    </div>
                    <div class="card-body">
                        <div class="config-section">
                            <h6>環境情報</h6>
                            <p><strong>本番環境:</strong> <?php echo $settings['is_production'] ? 'Yes' : 'No'; ?></p>
                            <p><strong>HTTPS:</strong> <?php echo $debugInfo['server_info']['https'] ? 'Yes' : 'No'; ?></p>
                            <p><strong>ドメイン:</strong> <?php echo htmlspecialchars($debugInfo['server_info']['host']); ?></p>
                        </div>
                        
                        <div class="config-section">
                            <h6>セッション設定</h6>
                            <p><strong>SameSite:</strong> 
                                <span class="badge bg-<?php echo $settings['session_settings']['cookie_samesite'] === 'Lax' ? 'success' : 'warning'; ?> status-badge">
                                    <?php echo htmlspecialchars($settings['session_settings']['cookie_samesite']); ?>
                                </span>
                            </p>
                            <p><strong>Secure:</strong> 
                                <span class="badge bg-<?php echo $settings['session_settings']['cookie_secure'] ? 'success' : 'danger'; ?> status-badge">
                                    <?php echo $settings['session_settings']['cookie_secure'] ? 'Yes' : 'No'; ?>
                                </span>
                            </p>
                            <p><strong>HttpOnly:</strong> 
                                <span class="badge bg-<?php echo $settings['session_settings']['cookie_httponly'] ? 'success' : 'warning'; ?> status-badge">
                                    <?php echo $settings['session_settings']['cookie_httponly'] ? 'Yes' : 'No'; ?>
                                </span>
                            </p>
                            <p><strong>Path:</strong> <?php echo htmlspecialchars($settings['session_settings']['cookie_path']); ?></p>
                            <p><strong>Domain:</strong> <?php echo htmlspecialchars($settings['session_settings']['cookie_domain'] ?: 'auto'); ?></p>
                        </div>
                        
                        <?php if (!$validation['valid']): ?>
                            <div class="alert alert-warning">
                                <h6>設定の問題:</h6>
                                <ul class="mb-0">
                                    <?php foreach ($validation['issues'] as $issue): ?>
                                        <li><?php echo htmlspecialchars($issue); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Cookie操作 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Cookie操作テスト</h5>
                    </div>
                    <div class="card-body">
                        <!-- テストCookie設定 -->
                        <div class="cookie-test">
                            <h6>テストCookie設定</h6>
                            <form method="post" action="?action=set_test_cookie">
                                <div class="mb-2">
                                    <label for="cookie_type" class="form-label">Cookieタイプ:</label>
                                    <select class="form-select" id="cookie_type" name="cookie_type" required>
                                        <option value="default">デフォルト (Lax)</option>
                                        <option value="csrf">CSRF (Strict)</option>
                                        <option value="auth">認証 (Strict)</option>
                                        <option value="analytics">分析 (None)</option>
                                        <option value="language">言語設定 (Lax)</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label for="cookie_value" class="form-label">値:</label>
                                    <input type="text" class="form-control" id="cookie_value" name="cookie_value" value="test_value_<?php echo time(); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Cookieを設定</button>
                            </form>
                        </div>
                        
                        <!-- Cookie削除 -->
                        <div class="cookie-test">
                            <h6>Cookie削除</h6>
                            <form method="post" action="?action=delete_test_cookie">
                                <div class="mb-2">
                                    <label for="cookie_name" class="form-label">Cookie名:</label>
                                    <input type="text" class="form-control" id="cookie_name" name="cookie_name" value="test_cookie" required>
                                </div>
                                <button type="submit" class="btn btn-danger btn-sm">Cookieを削除</button>
                            </form>
                        </div>
                        
                        <!-- セッション操作 -->
                        <div class="cookie-test">
                            <h6>セッション操作</h6>
                            <form method="get" action="?action=regenerate_session">
                                <button type="submit" class="btn btn-warning btn-sm">セッションID再生成</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 現在のCookie一覧 -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>現在のCookie一覧</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($_COOKIE)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Cookie名</th>
                                            <th>値</th>
                                            <th>推定タイプ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($_COOKIE as $name => $value): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($name); ?></code></td>
                                                <td>
                                                    <div class="cookie-display">
                                                        <?php echo htmlspecialchars(strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $type = 'unknown';
                                                    if (strpos($name, 'csrf') !== false) $type = 'CSRF';
                                                    elseif (strpos($name, 'auth') !== false) $type = 'Auth';
                                                    elseif (strpos($name, 'analytics') !== false) $type = 'Analytics';
                                                    elseif (strpos($name, 'language') !== false) $type = 'Language';
                                                    elseif (strpos($name, 'theme') !== false) $type = 'Theme';
                                                    elseif ($name === session_name()) $type = 'Session';
                                                    
                                                    $badgeClass = match($type) {
                                                        'CSRF' => 'bg-danger',
                                                        'Auth' => 'bg-warning',
                                                        'Analytics' => 'bg-info',
                                                        'Language', 'Theme' => 'bg-success',
                                                        'Session' => 'bg-primary',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?> status-badge"><?php echo $type; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Cookieが設定されていません</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 設定詳細 -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>設定詳細</h5>
                    </div>
                    <div class="card-body">
                        <div class="cookie-display">
                            <pre><?php print_r($config); ?></pre>
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
