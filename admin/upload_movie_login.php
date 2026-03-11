<?php
session_start();

$correct_password = 'yuki11'; // ← 好きなパスワードに変更

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['password'] === $correct_password) {
        $_SESSION['logged_in'] = true;
        header('Location: upload_movie.php'); // ログイン後に動画アップロードへ
        exit;
    } else {
        $error = 'パスワードが違います。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>動画アップロードログイン</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-4 bg-white p-4 rounded shadow-sm">
      <h3 class="text-center mb-4">ログイン</h3>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label for="password" class="form-label">パスワード</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">ログイン</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
