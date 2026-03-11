<?php
// ====== デバッグ用：エラー表示を有効化 ======
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// ====== DB設定 ======
$dbHost = 'mysql320.phy.heteml.lan';
$dbName = '_shinkenchiku_02';
$dbUser = '_shinkenchiku_02';
$dbPass = 'ipgdfahuqbg3';
$correctPassword = 'yuki11';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

// ====== テーブル名設定 ======
$buildings_table_name = 'buildings_table_3';  // ← ここを変更するだけでOK

// ====== 初期化 ======
$stage = 'input'; // input / select / done
$message = '';
$building = null;
$imageFiles = [];
$slug = '';

// ====== DB接続 ======
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("DB接続エラー: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// ====== POST処理 ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Step3: 画像選択完了 → UPDATE処理
    if (!empty($_POST['selected_photo']) && !empty($_POST['uid']) && !empty($_POST['slug'])) {
        try {
            $selected = $_POST['selected_photo'];
            $uid = $_POST['uid'];
            $slug = $_POST['slug'];

            $stmt = $pdo->prepare("UPDATE {$buildings_table_name} SET has_photo = :photo WHERE uid = :uid");
            $stmt->execute([':photo' => $selected, ':uid' => $uid]);

            $stage = 'done';
            $message = "代表画像「" . htmlspecialchars($selected, ENT_QUOTES, 'UTF-8') . "」を建物 slug " . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . " の has_photo に設定しました。";
        } catch (Exception $e) {
            $stage = 'input';
            $message = "エラー: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
    
    // Step2: slug入力 → 画像一覧表示へ
    elseif (!empty($_POST['slug_or_url']) && !empty($_POST['password'])) {
        
        try {
            // パスワード確認
            if ($_POST['password'] !== $correctPassword) {
                $stage = 'input';
                $message = 'パスワードが違います。';
            } else {
                $input = trim($_POST['slug_or_url']);

                // URLならslugを抽出
                if (strpos($input, '/buildings/') !== false) {
                    $parts = explode('/buildings/', $input);
                    if (isset($parts[1])) {
                        $slugPart = $parts[1];
                        $slug = explode('?', $slugPart)[0];
                    } else {
                        throw new Exception("URLからslugを抽出できませんでした");
                    }
                } else {
                    $slug = $input;
                }

                // 建物取得
                $stmt = $pdo->prepare("SELECT uid, title FROM {$buildings_table_name} WHERE slug = :slug");
                $stmt->execute([':slug' => $slug]);
                $building = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$building) {
                    $stage = 'input';
                    $message = "指定された建築物 slug " . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . " の建物が見つかりません。";
                } else {
                    $uid = $building['uid'];
                    
                    // ====== ここが重要：実際の環境に合わせてパスを調整してください ======
                    // 現在のファイルから見た相対パス
                    $pictureDir = __DIR__ . "/../pictures/{$uid}/";
                    
                    // デバッグ用：パスを表示
                    // echo "<!-- 確認用パス: " . $pictureDir . " -->";
                    
                    if (!is_dir($pictureDir)) {
                        $stage = 'input';
                        $message = "写真フォルダが見つかりません: " . htmlspecialchars($pictureDir, ENT_QUOTES, 'UTF-8');
                    } else {
                        // webp優先、webpがあればjpgは除外
                        $webpFiles = glob($pictureDir . "*.webp");
                        
                        if (!empty($webpFiles)) {
                            // webpがある場合はwebpのみ使用
                            $files = $webpFiles;
                        } else {
                            // webpがない場合のみjpgを使用
                            $files = glob($pictureDir . "*.jpg");
                        }

                        if (empty($files)) {
                            $stage = 'input';
                            $message = "画像ファイルが見つかりません（webp も jpg もありません）。フォルダ: " . htmlspecialchars($pictureDir, ENT_QUOTES, 'UTF-8');
                        } else {
                            $stage = 'select';
                            
                            // Webパス用に変換
                            foreach ($files as $filePath) {
                                $fileName = basename($filePath);
                                $imageFiles[] = [
                                    'fileName' => $fileName,
                                    'webUrl' => "/pictures/{$uid}/" . $fileName
                                ];
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $stage = 'input';
            $message = "エラーが発生しました: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>静止画像ファイル名 登録</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .photo-card {
      border: 3px solid #ddd;
      border-radius: 10px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      transition: all 0.2s ease;
      cursor: pointer;
      text-align: center;
      padding: 10px;
      height: 100%;
      position: relative;
    }

    .photo-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .photo-card img {
      width: 100%;
      height: 200px;
      object-fit: cover;
      border-radius: 6px;
      margin-bottom: 10px;
    }

    .radio-hidden {
      display: none;
    }

    /* ✅ 選択時のハイライト */
    .radio-hidden:checked + .photo-card {
      border-color: #0d6efd;
      box-shadow: 0 0 12px rgba(13, 110, 253, 0.5);
      background-color: #e7f1ff;
    }

    .photo-card-label {
      display: block;
      margin-bottom: 1.5rem;
    }

    .file-name {
      font-size: 13px;
      color: #555;
      word-break: break-all;
      margin-top: 8px;
    }

    /* 選択されたカードにチェックマークを表示 */
    .photo-card::after {
      content: '';
      display: none;
      position: absolute;
      top: 10px;
      right: 10px;
      width: 30px;
      height: 30px;
      background: #0d6efd;
      border-radius: 50%;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/%3E%3C/svg%3E");
      background-size: 20px;
      background-position: center;
      background-repeat: no-repeat;
    }

    .radio-hidden:checked + .photo-card::after {
      display: block;
    }

    /* 拡大アイコン */
    .zoom-icon {
      position: absolute;
      top: 10px;
      left: 10px;
      background: rgba(0, 0, 0, 0.6);
      color: white;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      z-index: 10;
      opacity: 0;
      transition: opacity 0.2s;
    }

    .photo-card:hover .zoom-icon {
      opacity: 1;
    }

    /* モーダル用スタイル */
    .modal-img {
      max-width: 100%;
      max-height: 80vh;
      object-fit: contain;
    }

    .modal-body {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 300px;
    }
  </style>
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-10 bg-white p-4 rounded shadow-sm">
      <h3 class="mb-4 text-center">🖼️ 建築物 静止画像ファイル名 登録</h3>

      <?php if ($message): ?>
        <div class="alert alert-<?= $stage === 'done' ? 'success' : 'danger' ?>">
          <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($stage === 'done'): ?>
        <div class="text-center mt-4">
          <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">別の建物を編集する</a>
        </div>
      <?php endif; ?>

      <?php if ($stage === 'input'): ?>
      <!-- ====== Step1: slug入力フォーム ====== -->
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">建築物slugまたは詳細ページURL</label>
          <input type="text" class="form-control" name="slug_or_url" 
                 placeholder="例: kushiro-castle-hotel または https://kenchikuka.com/buildings/kushiro-castle-hotel" 
                 required>
        </div>
        <div class="mb-3">
          <label class="form-label">パスワード</label>
          <input type="password" class="form-control" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">画像選択画面へ進む</button>
      </form>
      <?php endif; ?>

      <?php if ($stage === 'select'): ?>
      <!-- ====== Step2: 画像一覧から選択 ====== -->
      <h5 class="mb-3">代表画像を選択してください</h5>
      <p class="text-muted">建物名：<?= htmlspecialchars($building['title'], ENT_QUOTES, 'UTF-8') ?></p>
      <p class="text-muted small">💡 画像をクリックすると拡大表示されます</p>
      
      <form method="POST">
        <div class="row">
          <?php foreach ($imageFiles as $index => $image): ?>
          <div class="col-12 col-md-4">
            <label class="photo-card-label">
              <input type="radio" name="selected_photo" 
                     value="<?= htmlspecialchars($image['fileName'], ENT_QUOTES, 'UTF-8') ?>" 
                     class="radio-hidden" required>
              <div class="photo-card">
                <div class="zoom-icon">🔍</div>
                <img src="<?= htmlspecialchars($image['webUrl'], ENT_QUOTES, 'UTF-8') ?>" 
                     alt="<?= htmlspecialchars($image['fileName'], ENT_QUOTES, 'UTF-8') ?>"
                     onclick="showImageModal(event, '<?= htmlspecialchars($image['webUrl'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($image['fileName'], ENT_QUOTES, 'UTF-8') ?>')">
                <div class="file-name"><?= htmlspecialchars($image['fileName'], ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </label>
          </div>
          <?php endforeach; ?>
        </div>

        <input type="hidden" name="uid" value="<?= htmlspecialchars($building['uid'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
        
        <div class="mt-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">この画像を代表画像に設定する</button>
          <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">キャンセル</a>
        </div>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- ====== 画像拡大表示用モーダル ====== -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imageModalLabel">画像プレビュー</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <img id="modalImage" src="" alt="" class="modal-img">
      </div>
      <div class="modal-footer">
        <p class="text-muted small mb-0" id="modalFileName"></p>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showImageModal(event, imageUrl, fileName) {
  // イベントの伝播を停止（ラジオボタンの選択を防ぐ）
  event.stopPropagation();
  
  // モーダルに画像とファイル名をセット
  document.getElementById('modalImage').src = imageUrl;
  document.getElementById('modalImage').alt = fileName;
  document.getElementById('modalFileName').textContent = fileName;
  
  // モーダルを表示
  const modal = new bootstrap.Modal(document.getElementById('imageModal'));
  modal.show();
}
</script>
</body>
</html>