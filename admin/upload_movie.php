<?php
session_start();

if (empty($_SESSION['logged_in'])) {
    header('Location: upload_movie_login.php');
    exit;
}

// ==== 設定 ====
$dbHost = 'mysql320.phy.heteml.lan';
$dbName = '_shinkenchiku_02';
$dbUser = '_shinkenchiku_02';
$dbPass = 'ipgdfahuqbg3';

$message = '';
$messageClass = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $slugUrl = trim($_POST['slugUrl'] ?? '');
    $uid = '';
    $slug = '';

    if (!empty($slugUrl)) {
        $slugUrl = strtok($slugUrl, '?');

        if (!filter_var($slugUrl, FILTER_VALIDATE_URL)) {
            $message = 'エラー: URL形式が不正です。';
        } else {
            $path = parse_url($slugUrl, PHP_URL_PATH);
            if (preg_match('#/buildings/([^/]+)/?$#', $path, $matches)) {
                $slug = $matches[1];
            } else {
                $message = 'エラー: URLからslugを抽出できませんでした。';
            }
        }
    } else {
        $message = 'エラー: 建築物slug入りURLを入力してください。';
    }

    if (empty($message) && !empty($slug)) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare("SELECT uid FROM buildings_table_3 WHERE slug = :slug");
            $stmt->execute(['slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $message = "指定された建築物slug <code>$slug</code> の建物が見つかりません。";
            } else {
                $uid = $row['uid'];
            }
        } catch (Exception $e) {
            die("データベース接続エラー: " . $e->getMessage());
        }
    }

    if (empty($message) && $uid) {
        if (!isset($_FILES['movie']) || $_FILES['movie']['error'] !== UPLOAD_ERR_OK) {
            $message = 'エラー: ファイルが選択されていないか、アップロードに失敗しました。';
        } else {
            $tmp = $_FILES['movie']['tmp_name'];
            $origName = basename($_FILES['movie']['name']);
            $fileType = $_FILES['movie']['type'];
            $fileExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);

            if (
                !is_uploaded_file($tmp) ||
                (
                    $fileExt !== 'mp4' &&
                    stripos($fileType, 'mp4') === false &&
                    $mime !== 'video/mp4'
                )
            ) {
                $message = "エラー: MP4動画のみ対応しています。<br>
                            (検出されたタイプ: fileType=$fileType, MIME=$mime)";
            } else {
                $saveDir = dirname(__DIR__) . "/movies/$uid/";
                if (!file_exists($saveDir)) mkdir($saveDir, 0755, true);

                $saveName = "movie_{$slug}.mp4";
                $savePath = $saveDir . $saveName;

                if (move_uploaded_file($tmp, $savePath)) {
                    $message = "✅ アップロード成功:<br>
                                ・保存先: <code>/movies/$uid/$saveName</code><br>
                                ・元ファイル名: <code>$origName</code><br>
                                <a href=\"" . $_SERVER['PHP_SELF'] . "\" class=\"btn btn-secondary mt-3\">別の動画をアップロードする</a>";
                    $messageClass = 'success';
                } else {
                    $message = 'エラー: サーバーへの保存に失敗しました。';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>建築動画アップロード</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 bg-white p-4 rounded shadow-sm">
            <h2 class="mb-4 text-center">🎥 建築動画アップロード</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageClass ?>"><?= $message ?></div>
            <?php endif; ?>

            <form id="uploadForm" method="POST" enctype="multipart/form-data" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <div class="mb-3">
                    <label for="slugUrl" class="form-label">建築物slug入りURL</label>
                    <input type="url" class="form-control" id="slugUrl" name="slugUrl" required>
                </div>

                <div class="mb-3">
                    <label for="movie" class="form-label">動画ファイル（MP4）</label>
                    <input type="file" class="form-control" id="movie" name="movie" accept="video/mp4" required>
                </div>

                <div class="progress mb-3" style="height: 25px; display: none;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%">0%</div>
                </div>

                <div class="row">
                    <div class="col">
                        <button type="submit" class="btn btn-primary w-100">アップロードする</button>
                    </div>
                    <div class="col">
                        <button type="reset" class="btn btn-secondary w-100">リセット</button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const xhr = new XMLHttpRequest();
    const progressBar = document.getElementById('progressBar');
    const progressContainer = document.querySelector('.progress');

    progressContainer.style.display = 'block';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';

    xhr.upload.addEventListener('progress', function(event) {
        if (event.lengthComputable) {
            const percent = Math.round((event.loaded / event.total) * 100);
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
        }
    });

    xhr.addEventListener('load', function() {
        form.submit(); // アップロード完了後に通常送信（PHP側で処理）
    });

    xhr.open('POST', form.action);
    xhr.send(formData);
});
</script>

</body>
</html>
