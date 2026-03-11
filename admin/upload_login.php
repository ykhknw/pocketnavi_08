<?php
session_start();
$correct_user = 'admin';
$correct_pass = 'yuki11';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['username'] === $correct_user && $_POST['password'] === $correct_pass) {
        $_SESSION['logged_in'] = true;
        header('Location: upload.php');
        exit;
    } else {
        $error = 'ログインに失敗しました';
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ログイン</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5 bg-white p-4 rounded shadow-sm">
      <h3 class="mb-3 text-center">🔐 ログイン</h3>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">ユーザー名</label>
          <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">パスワード</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">ログイン</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
