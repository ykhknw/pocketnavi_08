<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>YouTube メタデータ更新</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container">
  <div class="card shadow-lg p-4">
    <h2 class="mb-4">建築物と動画の情報を入力</h2>
    <form action="oauth_handler.php" method="post">
      <div class="mb-3">
        <label for="uid" class="form-label">建築物のUID <small class="text-muted">（例：SK_1975_05_189-0 またはURL形式）</small></label>
        <input type="text" class="form-control" id="uid" name="uid" required>
      </div>
      <div class="mb-3">
        <label for="videoId" class="form-label">YouTube動画ID <small class="text-muted">（例：VX91g9K_wto または https://youtube.com/shorts/VX91g9K_wto）</small></label>
        <input type="text" class="form-control" id="videoId" name="videoId" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">パスワード</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary">Googleで認証して開始</button>
    </form>
  </div>
</div>
</body>
</html>