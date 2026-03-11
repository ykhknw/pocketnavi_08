<?php
session_start();

// 設定
$password = '0319';
$upload_dir = './pocketnavi/';
$max_file_size = 200 * 1024 * 1024; // 100MB
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];

// PHP設定値の取得
$php_max_filesize = ini_get('upload_max_filesize');
$php_max_post = ini_get('post_max_size');
$php_max_execution = ini_get('max_execution_time');
$php_memory_limit = ini_get('memory_limit');

// バイト変換関数
function convertToBytes($value) {
    $value = trim($value);
    $last = strtolower($value[strlen($value)-1]);
    $value = (int)$value;
    switch($last) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }
    return $value;
}

$php_max_filesize_bytes = convertToBytes($php_max_filesize);
$php_max_post_bytes = convertToBytes($php_max_post);

// 実際の制限値（PHPの設定と独自設定の小さい方）
$actual_max_filesize = min($max_file_size, $php_max_filesize_bytes, $php_max_post_bytes);

// アップロードディレクトリの作成
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// パスワード認証
$authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

if (isset($_POST['login'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['authenticated'] = true;
        $authenticated = true;
    } else {
        $error_message = 'パスワードが正しくありません。';
    }
}

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ファイルアップロード処理
if ($authenticated && isset($_POST['upload'])) {
    // POSTデータサイズチェック（PHPの制限値超過をチェック）
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $upload_error = 'ファイルサイズがサーバーの制限を超えています。<br>' .
                       'PHPの制限: ' . $php_max_post . '<br>' .
                       '送信されたサイズ: ' . number_format($_SERVER['CONTENT_LENGTH'] / 1024 / 1024, 2) . 'MB';
    } elseif (isset($_FILES['file'])) {
        $file_error = $_FILES['file']['error'];
        
        // PHPアップロードエラーの詳細処理
        switch ($file_error) {
            case UPLOAD_ERR_OK:
                // 正常 - 続行
                break;
            case UPLOAD_ERR_INI_SIZE:
                $upload_error = 'ファイルサイズがPHPの設定制限を超えています。<br>' .
                               'サーバー制限: ' . $php_max_filesize;
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $upload_error = 'ファイルサイズがフォームの制限を超えています。';
                break;
            case UPLOAD_ERR_PARTIAL:
                $upload_error = 'ファイルが部分的にしかアップロードされませんでした。<br>' .
                               'ネットワーク接続を確認してください。';
                break;
            case UPLOAD_ERR_NO_FILE:
                $upload_error = 'ファイルが選択されていません。';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $upload_error = 'サーバーの一時ディレクトリが見つかりません。';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $upload_error = 'サーバーへの書き込みに失敗しました。';
                break;
            case UPLOAD_ERR_EXTENSION:
                $upload_error = 'PHPの拡張機能によりアップロードが停止されました。';
                break;
            default:
                $upload_error = '不明なエラーが発生しました。(エラーコード: ' . $file_error . ')';
                break;
        }
        
        // エラーがない場合のみ処理続行
        if (!isset($upload_error)) {
            $file_name = $_FILES['file']['name'];
            $file_tmp = $_FILES['file']['tmp_name'];
            $file_size = $_FILES['file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // バリデーション
            if ($file_size > $actual_max_filesize) {
                $upload_error = 'ファイルサイズが制限を超えています。<br>' .
                               '最大サイズ: ' . number_format($actual_max_filesize / 1024 / 1024, 2) . 'MB<br>' .
                               'アップロードサイズ: ' . number_format($file_size / 1024 / 1024, 2) . 'MB';
            } elseif (!in_array($file_ext, $allowed_extensions)) {
                $upload_error = '許可されていないファイル形式です。<br>' .
                               '対応形式: ' . implode(', ', array_map('strtoupper', $allowed_extensions));
            } elseif (!is_uploaded_file($file_tmp)) {
                $upload_error = 'アップロードファイルが不正です。';
            } else {
                // セキュアなファイル名生成
                $safe_filename = date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
                $destination = $upload_dir . $safe_filename;
                
                // ファイル名重複チェック
                if (file_exists($destination)) {
                    $upload_error = 'ファイル名が重複しています。<br>' .
                                   '既存ファイル: ' . htmlspecialchars($safe_filename);
                } else {
                    // ディスク容量チェック
                    $free_space = disk_free_space($upload_dir);
                    if ($free_space !== false && $file_size > $free_space) {
                        $upload_error = 'サーバーの空き容量が不足しています。';
                    } else {
                        // ファイル移動実行
                        if (move_uploaded_file($file_tmp, $destination)) {
                            // 移動後のファイル存在確認
                            if (file_exists($destination) && filesize($destination) === $file_size) {
                                $upload_success = 'ファイルが正常にアップロードされました。<br>' .
                                                 'ファイル名: ' . htmlspecialchars($safe_filename) . '<br>' .
                                                 'サイズ: ' . number_format($file_size / 1024 / 1024, 2) . 'MB';
                            } else {
                                $upload_error = 'ファイルの保存に失敗しました。（保存後の検証エラー）';
                            }
                        } else {
                            $upload_error = 'ファイルのアップロードに失敗しました。<br>' .
                                           'サーバーの権限設定を確認してください。';
                        }
                    }
                }
            }
        }
    } else {
        $upload_error = 'ファイルデータが受信されませんでした。<br>' .
                       'ファイルサイズまたはネットワーク接続を確認してください。';
    }
}

// ファイル一覧取得
function getFileList($dir) {
    $files = [];
    if (is_dir($dir)) {
        $handle = opendir($dir);
        while (($file = readdir($handle)) !== false) {
            if ($file != '.' && $file != '..' && is_file($dir . $file)) {
                $files[] = [
                    'name' => $file,
                    'size' => filesize($dir . $file),
                    'date' => date('Y-m-d H:i:s', filemtime($dir . $file))
                ];
            }
        }
        closedir($handle);
    }
    // 日付順でソート（新しい順）
    usort($files, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    return $files;
}

// サムネイル表示
if ($authenticated && isset($_GET['thumbnail'])) {
    $filename = basename($_GET['thumbnail']);
    $filepath = $upload_dir . $filename;
    
    if (file_exists($filepath)) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
            // 画像のMIMEタイプを取得
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            
            header('Content-Type: ' . $mime_type);
            header('Cache-Control: public, max-age=3600'); // 1時間キャッシュ
            readfile($filepath);
            exit;
        }
    }
}

// ファイル削除処理（複数削除対応）
if ($authenticated && isset($_POST['delete'])) {
    $deleted_files = [];
    $failed_files = [];
    
    if (isset($_POST['delete_files']) && is_array($_POST['delete_files'])) {
        foreach ($_POST['delete_files'] as $filename) {
            $filename = basename($filename);
            $filepath = $upload_dir . $filename;
            
            if (file_exists($filepath)) {
                if (unlink($filepath)) {
                    $deleted_files[] = $filename;
                } else {
                    $failed_files[] = $filename;
                }
            } else {
                $failed_files[] = $filename . '（見つかりません）';
            }
        }
        
        if (!empty($deleted_files)) {
            $delete_success = count($deleted_files) . '個のファイルが正常に削除されました。';
        }
        if (!empty($failed_files)) {
            $delete_error = '削除に失敗したファイル: ' . implode(', ', $failed_files);
        }
    } else {
        $delete_error = '削除するファイルが選択されていません。';
    }
}

// ファイルダウンロード
if ($authenticated && isset($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filepath = $upload_dir . $filename;
    
    if (file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

$files = $authenticated ? getFileList($upload_dir) : [];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ファイル管理システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .file-icon {
            font-size: 2rem;
            margin-right: 10px;
        }
        .progress-container {
            display: none;
            margin-top: 20px;
        }
        .file-item {
            transition: background-color 0.2s;
        }
        .file-item:hover {
            background-color: #f8f9fa;
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: border-color 0.3s;
            position: relative;
        }
        .upload-area.dragover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .upload-area input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .upload-content {
            pointer-events: none;
        }
        .thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            transition: transform 0.2s;
        }
        .thumbnail:hover {
            transform: scale(1.1);
            border-color: #0d6efd;
        }
        .file-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .file-details {
            flex-grow: 1;
            min-width: 0; /* flexアイテムの収縮を許可 */
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .thumbnail {
                width: 40px;
                height: 40px;
            }
            
            .file-info {
                gap: 8px;
            }
            
            .file-details .fw-bold {
                max-width: 120px !important;
                font-size: 0.85rem;
            }
            
            .btn-sm {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            
            /* モバイル用のファイル情報カラム幅調整 */
            .mobile-checkbox {
                width: 8%;
            }
            
            .mobile-file-info {
                width: 37%;
            }
            
            .mobile-size {
                width: 15%;
            }
            
            .mobile-date {
                display: none; /* スマホでは日時を非表示 */
            }
            
            .mobile-actions {
                width: 40%;
            }
            
            /* モバイル用ボタンレイアウト */
            .mobile-buttons {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            
            .mobile-buttons .btn {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
            }
        }
        
        @media (max-width: 576px) {
            .file-details .fw-bold {
                max-width: 100px !important;
            }
            
            .mobile-size {
                display: none; /* 小さな画面ではサイズも非表示 */
            }
            
            .mobile-actions {
                width: 45%;
            }
            
            .mobile-file-info {
                width: 47%;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="bi bi-cloud-upload"></i> ファイル管理システム</h4>
                        <?php if ($authenticated): ?>
                            <form method="post" class="d-inline">
                                <button type="submit" name="logout" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-box-arrow-right"></i> ログアウト
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!$authenticated): ?>
                            <!-- パスワード入力フォーム -->
                            <div class="row justify-content-center">
                                <div class="col-md-6">
                                    <h5 class="text-center mb-4">認証が必要です</h5>
                                    <?php if (isset($error_message)): ?>
                                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                                    <?php endif; ?>
                                    <form method="post">
                                        <div class="mb-3">
                                            <label for="password" class="form-label">パスワード</label>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                        </div>
                                        <div class="d-grid">
                                            <button type="submit" name="login" class="btn btn-primary">
                                                <i class="bi bi-unlock"></i> ログイン
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- ファイルアップロード -->
                            <div class="mb-4">
                                <h5><i class="bi bi-upload"></i> ファイルアップロード</h5>
                                <?php if (isset($upload_success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle"></i> <?php echo $upload_success; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($upload_error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="bi bi-exclamation-triangle"></i> <?php echo $upload_error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($delete_success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($delete_success); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($delete_error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($delete_error); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form id="uploadForm" method="post" enctype="multipart/form-data">
                                    <div class="upload-area" id="uploadArea">
                                        <input type="file" class="form-control" id="file" name="file" accept=".jpg,.jpeg,.png,.gif,.bmp,.mp4,.avi,.mov,.wmv,.flv,.webm" required>
                                        <div class="upload-content">
                                            <i class="bi bi-cloud-upload file-icon text-muted"></i>
                                            <p class="mb-2">ファイルをドラッグ&ドロップするか、クリックして選択してください</p>
                                            <small class="text-muted">
                                                対応形式: JPG, PNG, GIF, BMP, MP4, AVI, MOV, WMV, FLV, WebM<br>
                                                最大サイズ: <?php echo number_format($actual_max_filesize / 1024 / 1024, 0); ?>MB
                                                <span class="text-info">
                                                    (PHP制限: <?php echo $php_max_filesize; ?>, POST制限: <?php echo $php_max_post; ?>)
                                                </span>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="progress-container">
                                        <div class="progress mb-3">
                                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <div class="upload-status text-center"></div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" name="upload" class="btn btn-success">
                                            <i class="bi bi-upload"></i> アップロード
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <hr>

                            <!-- ファイル一覧 -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><i class="bi bi-folder"></i> ファイル一覧 (<?php echo count($files); ?>件)</h5>
                                    <?php if (!empty($files)): ?>
                                        <div class="btn-group">
                                            <button type="button" id="selectAllBtn" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-check-square"></i> 全選択
                                            </button>
                                            <button type="button" id="deleteSelectedBtn" class="btn btn-danger btn-sm" disabled>
                                                <i class="bi bi-trash"></i> 選択削除
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (empty($files)): ?>
                                    <p class="text-muted">アップロードされたファイルはありません。</p>
                                <?php else: ?>
                                    <form id="deleteForm" method="post">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="mobile-checkbox">
                                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                                        </th>
                                                        <th class="mobile-file-info">ファイル情報</th>
                                                        <th class="mobile-size">サイズ</th>
                                                        <th class="mobile-date d-none d-md-table-cell">アップロード日時</th>
                                                        <th class="mobile-actions">操作</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($files as $file): ?>
                                                    <tr class="file-item">
                                                        <td class="mobile-checkbox">
                                                            <input type="checkbox" name="delete_files[]" 
                                                                   value="<?php echo htmlspecialchars($file['name']); ?>" 
                                                                   class="form-check-input file-checkbox">
                                                        </td>
                                                        <td class="mobile-file-info">
                                                            <div class="file-info">
                                                                <?php
                                                                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                                                $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
                                                                ?>
                                                                
                                                                <?php if ($is_image): ?>
                                                                    <img src="?thumbnail=<?php echo urlencode($file['name']); ?>" 
                                                                         alt="<?php echo htmlspecialchars($file['name']); ?>"
                                                                         class="thumbnail"
                                                                         loading="lazy">
                                                                <?php else: ?>
                                                                    <div class="thumbnail d-flex align-items-center justify-content-center bg-light">
                                                                        <i class="bi bi-camera-video text-primary" style="font-size: 24px;"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <div class="file-details">
                                                                    <div class="fw-bold text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($file['name']); ?>">
                                                                        <?php echo htmlspecialchars($file['name']); ?>
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        <?php echo strtoupper($ext); ?> 
                                                                        <?php if ($is_image): ?>
                                                                            <span class="badge bg-success ms-1">画像</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-info ms-1">動画</span>
                                                                        <?php endif; ?>
                                                                        <span class="d-md-none ms-1 text-muted">
                                                                            (<?php echo number_format($file['size'] / 1024 / 1024, 1); ?>MB)
                                                                        </span>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="mobile-size d-none d-sm-table-cell"><?php echo number_format($file['size'] / 1024 / 1024, 2); ?> MB</td>
                                                        <td class="mobile-date d-none d-md-table-cell"><?php echo $file['date']; ?></td>
                                                        <td class="mobile-actions">
                                                            <a href="?download=<?php echo urlencode($file['name']); ?>" 
                                                               class="btn btn-primary btn-sm">
                                                                <i class="bi bi-download"></i> 
                                                                <span class="d-none d-lg-inline">ダウンロード</span>
                                                                <span class="d-lg-none">DL</span>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 削除確認モーダル -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning"></i> ファイル削除確認</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>選択したファイルを本当に削除しますか？</p>
                    <div class="alert alert-warning">
                        <div id="selectedFilesList"></div>
                    </div>
                    <p class="text-danger"><i class="bi bi-exclamation-circle"></i> この操作は元に戻すことができません。</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> キャンセル
                    </button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="bi bi-trash"></i> 削除する
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($authenticated): ?>
    <script>
        // ドラッグ&ドロップ機能
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('file');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileDisplay();
            }
        });
        
        uploadArea.addEventListener('click', (e) => {
            // 既にファイル入力フィールドがあるので、追加のクリックイベントは不要
            // ファイル入力フィールドが透明でupload-area全体を覆っているため
        });
        
        fileInput.addEventListener('change', updateFileDisplay);
        
        function updateFileDisplay() {
            const file = fileInput.files[0];
            const uploadContent = document.querySelector('.upload-content');
            
            if (file) {
                const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
                const maxSizeMB = <?php echo floor($actual_max_filesize / 1024 / 1024); ?>;
                
                let sizeWarning = '';
                if (file.size > <?php echo $actual_max_filesize; ?>) {
                    sizeWarning = '<div class="text-danger mt-2"><i class="bi bi-exclamation-triangle"></i> ファイルサイズが制限を超えています！</div>';
                } else if (fileSizeMB > maxSizeMB * 0.8) {
                    sizeWarning = '<div class="text-warning mt-2"><i class="bi bi-exclamation-circle"></i> 大きなファイルです。アップロードに時間がかかる場合があります。</div>';
                }
                
                uploadContent.innerHTML = `
                    <i class="bi bi-file-earmark file-icon text-success"></i>
                    <p class="mb-2">選択されたファイル: <strong>${file.name}</strong></p>
                    <p class="text-muted">サイズ: ${fileSizeMB} MB / ${maxSizeMB} MB</p>
                    ${sizeWarning}
                `;
            }
        }
        
        // アップロード進捗表示
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('file');
            
            // ファイルが選択されているかチェック
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('ファイルを選択してください。');
                return false;
            }
            
            const selectedFile = fileInput.files[0];
            const maxSize = <?php echo $actual_max_filesize; ?>;
            
            // ファイルサイズチェック
            if (selectedFile.size > maxSize) {
                e.preventDefault();
                alert(`ファイルサイズが制限を超えています。\n最大: ${Math.floor(maxSize/1024/1024)}MB\n選択: ${(selectedFile.size/1024/1024).toFixed(2)}MB`);
                return false;
            }
            
            // 大きなファイルの警告
            if (selectedFile.size > maxSize * 0.7) {
                if (!confirm('大きなファイルです。アップロードに時間がかかる可能性があります。\n続行しますか？')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            const progressContainer = document.querySelector('.progress-container');
            const progressBar = document.querySelector('.progress-bar');
            const uploadStatus = document.querySelector('.upload-status');
            
            progressContainer.style.display = 'block';
            uploadStatus.textContent = 'アップロード中...';
            
            // ファイルサイズに応じた進捗表示調整
            const fileSize = selectedFile.size;
            const isLargeFile = fileSize > 10 * 1024 * 1024; // 10MB以上
            const progressSpeed = isLargeFile ? 5 : 15; // 大きなファイルは進捗を遅く
            
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * progressSpeed;
                if (progress > 85) progress = 85; // 大きなファイルは85%で停止
                
                progressBar.style.width = progress + '%';
                progressBar.textContent = Math.round(progress) + '%';
                
                if (progress >= 85) {
                    clearInterval(interval);
                    uploadStatus.innerHTML = `
                        <div>サーバーで処理中...</div>
                        <small class="text-muted">大きなファイルの場合、完了まで時間がかかります</small>
                    `;
                }
            }, isLargeFile ? 300 : 200);
            
            // タイムアウト警告（大きなファイル用）
            if (isLargeFile) {
                setTimeout(() => {
                    if (progressBar.style.width === '85%') {
                        uploadStatus.innerHTML = `
                            <div class="text-warning">処理に時間がかかっています...</div>
                            <small class="text-muted">サーバーでファイル保存中です。しばらくお待ちください。</small>
                        `;
                    }
                }, 30000); // 30秒後
            }
        });
        
        // ファイル削除確認機能
        function confirmDelete(filename) {
            document.getElementById('deleteFileName').textContent = filename;
            document.getElementById('deleteFileInput').value = filename;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
        // 複数選択・削除機能
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
            const fileCheckboxes = document.querySelectorAll('.file-checkbox');
            const deleteForm = document.getElementById('deleteForm');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            
            // 全選択チェックボックス
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    fileCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateDeleteButton();
                });
            }
            
            // 全選択ボタン
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    const allChecked = Array.from(fileCheckboxes).every(cb => cb.checked);
                    fileCheckboxes.forEach(checkbox => {
                        checkbox.checked = !allChecked;
                    });
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = !allChecked;
                    }
                    updateDeleteButton();
                });
            }
            
            // 個別チェックボックス
            fileCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectAllState();
                    updateDeleteButton();
                });
            });
            
            // 全選択状態の更新
            function updateSelectAllState() {
                if (selectAllCheckbox) {
                    const checkedCount = Array.from(fileCheckboxes).filter(cb => cb.checked).length;
                    selectAllCheckbox.checked = checkedCount === fileCheckboxes.length;
                    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < fileCheckboxes.length;
                }
            }
            
            // 削除ボタンの状態更新
            function updateDeleteButton() {
                const checkedCount = Array.from(fileCheckboxes).filter(cb => cb.checked).length;
                if (deleteSelectedBtn) {
                    deleteSelectedBtn.disabled = checkedCount === 0;
                    deleteSelectedBtn.innerHTML = `<i class="bi bi-trash"></i> 選択削除 (${checkedCount})`;
                }
            }
            
            // 削除確認モーダル表示
            if (deleteSelectedBtn) {
                deleteSelectedBtn.addEventListener('click', function() {
                    const selectedFiles = Array.from(fileCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.value);
                    
                    if (selectedFiles.length === 0) {
                        alert('削除するファイルを選択してください。');
                        return;
                    }
                    
                    const filesList = selectedFiles.map(file => `<div><i class="bi bi-file-earmark"></i> ${file}</div>`).join('');
                    document.getElementById('selectedFilesList').innerHTML = filesList;
                    
                    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                    deleteModal.show();
                });
            }
            
            // 削除実行
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', function() {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'delete';
                    hiddenInput.value = '1';
                    deleteForm.appendChild(hiddenInput);
                    
                    deleteForm.submit();
                });
            }
        });
        
        // サムネイル画像のエラーハンドリング
        document.addEventListener('DOMContentLoaded', function() {
            const thumbnails = document.querySelectorAll('.thumbnail');
            thumbnails.forEach(function(img) {
                if (img.tagName === 'IMG') {
                    img.addEventListener('error', function() {
                        this.style.display = 'none';
                        const placeholder = document.createElement('div');
                        placeholder.className = 'thumbnail d-flex align-items-center justify-content-center bg-light';
                        placeholder.innerHTML = '<i class="bi bi-image text-muted" style="font-size: 24px;"></i>';
                        this.parentNode.insertBefore(placeholder, this);
                    });
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>