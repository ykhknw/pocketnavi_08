<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: upload_login.php');
    exit;
}

// ==== 設定 ====
$dbHost = 'mysql320.phy.heteml.lan';
$dbName = '_shinkenchiku_02';
$dbUser = '_shinkenchiku_02';
$dbPass = 'ipgdfahuqbg3';

// ==== ユーティリティ関数 ====
function getExifDateTime($filepath) {
    $exif = @exif_read_data($filepath);
    if (isset($exif['DateTimeOriginal'])) {
        $dt = DateTime::createFromFormat('Y:m:d H:i:s', $exif['DateTimeOriginal']);
        if ($dt) return $dt->format('Ymd_Hi');
    }
    return null;
}

// ====================================================
// [追加] EXIF Orientationを読み込み、GDリソースを正しい
// 向きに回転させる関数
// 戻り値: ['image' => GDリソース, 'width' => 幅, 'height' => 高さ]
// ====================================================
function correctImageOrientation($srcPath, $mime) {
    $image = ($mime === 'image/jpeg')
        ? imagecreatefromjpeg($srcPath)
        : imagecreatefrompng($srcPath);

    // JPEG以外はEXIF情報なしのためそのまま返す
    if ($mime !== 'image/jpeg') {
        return [
            'image'  => $image,
            'width'  => imagesx($image),
            'height' => imagesy($image),
        ];
    }

    // exif拡張が使えない環境ではそのまま返す
    if (!function_exists('exif_read_data')) {
        return [
            'image'  => $image,
            'width'  => imagesx($image),
            'height' => imagesy($image),
        ];
    }

    $exif        = @exif_read_data($srcPath);
    $orientation = $exif['Orientation'] ?? 1;

    // Orientationタグに応じてGDリソースを回転
    // 1: 正常（回転不要）
    // 3: 180度回転
    // 6: 時計回り90度（縦位置・右手上） ← α7C IIの縦撮りはここが多い
    // 8: 反時計回り90度（縦位置・左手上）
    switch ($orientation) {
        case 3:
            $image = imagerotate($image, 180, 0);
            break;
        case 6:
            $image = imagerotate($image, -90, 0);
            break;
        case 8:
            $image = imagerotate($image, 90, 0);
            break;
    }

    // 回転後のサイズを取得（縦横が入れ替わっている場合があるため再取得）
    return [
        'image'  => $image,
        'width'  => imagesx($image),
        'height' => imagesy($image),
    ];
}

// ====================================================
// [追加] 透かしテキストを画像右下に追加するヘルパー関数
// 縦長・横長を自動判定してフォントサイズを微調整
// ====================================================
function addWatermark($dst, $newW, $newH, $fontSize = 40) {
    $fontPath = __DIR__ . '/DejaVuSans.ttf';
    $text     = 'kenchikuka.com';

    // 縦長画像の場合はフォントサイズを少し小さくする
    $isPortrait = ($newH > $newW);
    if ($isPortrait) {
        $fontSize = intval($fontSize * 0.8);
    }

    $white = imagecolorallocatealpha($dst, 255, 255, 255, 60);
    $bbox  = imagettfbbox($fontSize, 0, $fontPath, $text);
    $textW = abs($bbox[2] - $bbox[0]);

    // 右下に配置（縦横共通ロジック）
    $x = $newW - $textW - 20;
    $y = $newH - 20;

    imagettftext($dst, $fontSize, 0, $x, $y, $white, $fontPath, $text);
}

// ====================================================
// [修正] resizeImage
// 変更点:
//   1. correctImageOrientation() でEXIF回転を適用してからリサイズ
//   2. 透かし処理を addWatermark() に切り出し（縦横自動対応）
// ====================================================
function resizeImage($srcPath, $destPath, $mime, $maxSize = 1500, $jpegQuality = 80) {
    // [変更] getimagesize + imagecreatefromjpeg の代わりに
    //        correctImageOrientation() で回転済みGDリソースを取得
    $corrected = correctImageOrientation($srcPath, $mime);
    $src    = $corrected['image'];
    $width  = $corrected['width'];   // 回転後の正しい幅
    $height = $corrected['height'];  // 回転後の正しい高さ

    $scale = min($maxSize / $width, $maxSize / $height, 1);
    $newW  = intval($width  * $scale);
    $newH  = intval($height * $scale);

    $dst = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

    // [変更] 透かしをヘルパー関数に変更（縦横自動対応）
    addWatermark($dst, $newW, $newH, 40);

    if ($mime === 'image/jpeg') imagejpeg($dst, $destPath, $jpegQuality);
    else imagepng($dst, $destPath, 9);

    imagedestroy($src);
    imagedestroy($dst);
}

// ====================================================
// [修正] saveWebp
// 変更点: correctImageOrientation() でEXIF回転を適用してからWebP変換
//         ※resizeImage済みのJPEGを渡す場合は回転不要だが、
//           $tmpを直接渡す場合に備えて対応
// ====================================================
function saveWebp($srcPath, $destPath, $mime, $quality = 80) {
    // [変更] 回転補正済みGDリソースを取得
    $corrected = correctImageOrientation($srcPath, $mime);
    $src = $corrected['image'];

    imagewebp($src, $destPath, $quality);
    imagedestroy($src);
}

// ====================================================
// [修正] createThumbnail
// 変更点:
//   1. correctImageOrientation() でEXIF回転を適用してからサムネイル生成
//   2. 透かし処理を addWatermark() に切り出し（縦横自動対応）
// ====================================================
function createThumbnail($srcPath, $thumbPath, $mime, $size = 300) {
    // [変更] getimagesize + imagecreatefromjpeg の代わりに
    //        correctImageOrientation() で回転済みGDリソースを取得
    $corrected = correctImageOrientation($srcPath, $mime);
    $src = $corrected['image'];
    $w   = $corrected['width'];   // 回転後の正しい幅
    $h   = $corrected['height'];  // 回転後の正しい高さ

    // 正方形クロップ（中央）
    $min  = min($w, $h);
    $srcX = intval(($w - $min) / 2);
    $srcY = intval(($h - $min) / 2);

    $thumb = imagecreatetruecolor($size, $size);
    imagecopyresampled($thumb, $src, 0, 0, $srcX, $srcY, $size, $size, $min, $min);

    // [変更] 透かしをヘルパー関数に変更（縦横自動対応）
    addWatermark($thumb, $size, $size, 14);

    imagejpeg($thumb, $thumbPath, 80);
    imagedestroy($src);
    imagedestroy($thumb);
}

// ==== POST処理 ====
$message = '';
$messageClass = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slugUrl  = trim($_POST['slugUrl'] ?? '');
    $uidInput = trim($_POST['uid'] ?? '');
    $uid      = '';
    $slug     = '';

    // URLまたは slug を解析して slug を取得
    if (!empty($slugUrl)) {
        if (!filter_var($slugUrl, FILTER_VALIDATE_URL)) {
            $message = 'エラー: URL形式が不正です。';
        } else {
            $path = parse_url($slugUrl, PHP_URL_PATH);
            if (preg_match('#^/buildings/([^/]+)#', $path, $matches)) {
                $slug = $matches[1];
            } else {
                $message = 'エラー: URLからslugを抽出できませんでした。';
            }
        }
    } elseif (!empty($uidInput)) {
        // [追加] UID欄にURL形式で入力された場合（例: https://kenchikuka.com/index_thumb.php?uid=SK_2007_04_094-0）
        //        クエリパラメータ "uid=" の値を抽出して使用する
        if (filter_var($uidInput, FILTER_VALIDATE_URL)) {
            $parsedQuery = [];
            parse_str(parse_url($uidInput, PHP_URL_QUERY), $parsedQuery);
            if (!empty($parsedQuery['uid'])) {
                $uid = $parsedQuery['uid'];
            } else {
                $message = 'エラー: URLにuidパラメータが見つかりません。';
            }
        } else {
            // 通常のUID文字列（SK_xxxx）としてそのまま使用
            $uid = $uidInput;
        }
    } else {
        $message = 'エラー: 建築物slug入りURLまたはUIDを入力してください。';
    }

    if (empty($message) && !empty($slug)) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            die("データベース接続エラー: " . $e->getMessage());
        }

        // slug に対応する uid を取得
        $stmt = $pdo->prepare("SELECT uid FROM buildings_table_3 WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $message = "指定された建築物slug <code>$slug</code> の建物が見つかりません。<br>
                        入力値: <code>$slugUrl</code>";
        } else {
            $uid = $row['uid'];
        }
    }

    // 画像処理（複数対応）
    if (empty($message) && $uid) {
        if (strpos($uid, 'SK_') !== 0) {
            $message = 'エラー: UIDは "SK_" で始まる必要があります。';
        } elseif (!isset($_FILES['photo'])) {
            $message = 'エラー: ファイルが選択されていません。';
        } else {
            $results = [];
            foreach ($_FILES['photo']['error'] as $i => $error) {
                if ($error !== UPLOAD_ERR_OK) {
                    $results[] = "❌ ファイル " . htmlspecialchars($_FILES['photo']['name'][$i]) . " のアップロードに失敗しました。";
                    continue;
                }

                $tmp      = $_FILES['photo']['tmp_name'][$i];
                $origName = basename($_FILES['photo']['name'][$i]);
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mime     = finfo_file($finfo, $tmp);
                finfo_close($finfo);

                if (!is_uploaded_file($tmp) || !in_array($mime, ['image/jpeg', 'image/png'])) {
                    $results[] = "❌ " . htmlspecialchars($origName) . " はJPEG/PNGではありません。";
                    continue;
                }

                $dateStr  = getExifDateTime($tmp) ?? date('Ymd_Hi');
                $baseName = $uid . '_' . $dateStr . '_' . $i;

                $saveDir  = dirname(__DIR__) . "/pictures/$uid/";
                $thumbDir = $saveDir . "thumbs/";
                if (!file_exists($thumbDir)) mkdir($thumbDir, 0755, true);

                $jpgPath      = $saveDir  . "$baseName.jpg";
                $webpPath     = $saveDir  . "$baseName.webp";
                $thumbPathJpg = $thumbDir . "{$baseName}_thumb.jpg";
                $thumbPathWebp= $thumbDir . "{$baseName}_thumb.webp";

                // [変更] 各関数がEXIF回転を内部で処理するため、
                //        呼び出し側は変更不要
                resizeImage($tmp, $jpgPath, $mime);
                saveWebp($jpgPath, $webpPath, 'image/jpeg');
                createThumbnail($tmp, $thumbPathJpg, $mime);
                saveWebp($thumbPathJpg, $thumbPathWebp, 'image/jpeg');

                $results[] = "✅ " . htmlspecialchars($origName) . " を保存しました<br>
                              ・JPEG: $baseName.jpg<br>
                              ・WebP: $baseName.webp<br>
                              ・サムネイル: {$baseName}_thumb.jpg / _thumb.webp";
            }

            $message      = implode("<hr>", $results) . '<br><a href="' . $_SERVER['PHP_SELF'] . '" class="btn btn-secondary mt-3">別の建物をアップロードする</a>';
            $messageClass = 'success';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>画像アップロード | 建築写真</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 bg-white p-4 rounded shadow-sm">
            <h2 class="mb-4 text-center">📷 建築写真アップロード</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageClass ?>"><?= $message ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="slugUrl" class="form-label">建築物slug入りURL（例：https://kenchikuka.com/buildings/kushiro-castle-hotel?lang=ja）</label>
                    <input type="url" class="form-control" id="slugUrl" name="slugUrl">
                </div>
                <div class="mb-3">
                    <label for="uid" class="form-label">UID（例：SK_12345）</label>
                    <input type="text" class="form-control" id="uid" name="uid">
                </div>
                <div class="mb-3">
                    <label for="photo" class="form-label">画像ファイル（JPEG/PNG 複数選択可）</label>
                    <input type="file" class="form-control" id="photo" name="photo[]" accept="image/jpeg,image/png" multiple required>
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
</body>
</html>
