<?php
/**
 * 管理画面ログイン
 */

// エラー表示を有効にする（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// 既にログイン済みの場合は管理画面にリダイレクト
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_POST) {
    try {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // 簡単な認証（本番環境では適切な認証システムを使用してください）
        if ($username === 'admin' && $password === 'yuki11') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header('Location: index.php');
            exit;
        } else {
            $error = 'ユーザー名またはパスワードが正しくありません。';
        }
    } catch (Exception $e) {
        $error = 'ログイン処理中にエラーが発生しました: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketNavi 管理画面 - ログイン</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #333;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #666;
            margin: 0;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: bold;
            width: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h1>
                <i data-lucide="shield-check" style="width: 32px; height: 32px; color: #667eea;"></i>
            </h1>
            <h1>PocketNavi</h1>
            <p>管理画面ログイン</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i data-lucide="alert-circle" class="me-2" style="width: 16px; height: 16px;"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">ユーザー名</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">パスワード</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login">
                <i data-lucide="log-in" class="me-2" style="width: 16px; height: 16px;"></i>
                ログイン
            </button>
        </form>
        
        <div class="text-center mt-4">
            <small class="text-muted">
                デモ用認証情報:<br>
                ユーザー名: admin<br>
                パスワード: pocketnavi2025
            </small>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Lucide Icons初期化
        lucide.createIcons();
    </script>
</body>
</html>
