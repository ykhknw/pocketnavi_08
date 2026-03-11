<?php
// 設定
$dbHost = 'mysql320.phy.heteml.lan';
$dbName = '_shinkenchiku_02';
$dbUser = '_shinkenchiku_02';
$dbPass = 'ipgdfahuqbg3';
$correctPassword = 'yuki11';

$message = '';
$inputSlug = '';
$slug = '';
$youtubeUrl = '';
$stage = 'form';

// DB接続
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputSlug = trim($_POST['slug'] ?? '');
    $youtubeUrl = trim($_POST['youtubeUrl'] ?? '');
    $password = $_POST['password'] ?? '';

// URLかどうか判定
if (filter_var($inputSlug, FILTER_VALIDATE_URL)) {
    // URLを解析してパス部分を取得
    $path = parse_url($inputSlug, PHP_URL_PATH); // /buildings/kushiro-fishermans-wharfkitachiku-green-house
    // /buildings/ の後ろを取得
    if (preg_match('#^/buildings/([^/]+)#', $path, $matches)) {
        $slug = $matches[1]; // 正しい slug を取得
    }
} else {
    $slug = $inputSlug; // URLでなければそのまま slug
}

    if ($password !== $correctPassword) {
        $message = "パスワードが正しくありません。";
        $stage = 'form';
    } elseif (empty($slug) || empty($youtubeUrl)) {
        $message = "全ての項目を入力してください。";
        $stage = 'form';
    } else {
        // slugが存在するかチェック
        $stmt = $pdo->prepare("SELECT * FROM buildings_table_3 WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        $building = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$building) {
            $message = "指定された建築物slug <code>$slug</code> の建物が見つかりません。<br>
                        入力値: <code>$inputSlug</code>";
            $stage = 'form';
        } elseif (!preg_match('#^https://(www\.)?(youtube\.com/(watch\?v=|shorts/)|youtu\.be/)#', $youtubeUrl)) {
            $message = "正しい YouTube 動画URLを入力してください。";
            $stage = 'form';
        } else {
            // 更新処理
            $update = $pdo->prepare("UPDATE buildings_table_3 SET youtubeUrl = :youtubeUrl WHERE slug = :slug");
            $update->execute(['youtubeUrl' => $youtubeUrl, 'slug' => $slug]);
//            $message = "✅ 建物 slug <code>$slug</code> の YouTube URL を更新しました。";
$message = "✅ 建物 slug <code>$slug</code> の YouTube URL を更新しました。<br>
            <a href=\"" . $_SERVER['PHP_SELF'] . "\" class=\"btn btn-secondary mt-3\">別の建物を登録する</a>";

            $stage = 'done';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>YouTube URL 登録</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 bg-white p-4 rounded shadow-sm">
      <h3 class="mb-4 text-center">🏛️ 建築物 YouTube URL 登録</h3>

      <?php if ($message): ?>
        <div class="alert alert-<?= $stage === 'done' ? 'success' : 'danger' ?>"><?= $message ?></div>
      <?php endif; ?>

      <?php if ($stage !== 'done'): ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">建築物slugまたは詳細ページURL</label>
          <input type="text" class="form-control" name="slug" 
                 placeholder="例: kushiro-castle-hotel または https://kenchikuka.com/buildings/kushiro-castle-hotel" 
                 value="<?= htmlspecialchars($inputSlug) ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">YouTube動画URL</label>
          <input type="url" class="form-control" name="youtubeUrl" 
                 placeholder="例: https://youtu.be/abc123def" 
                 value="<?= htmlspecialchars($youtubeUrl) ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">パスワード</label>
          <input type="password" class="form-control" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">登録・更新する</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
