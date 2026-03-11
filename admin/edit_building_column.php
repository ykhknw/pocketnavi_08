<?php
/**
 * 建築物コラム編集ページ
 * Admin管理画面で建築物のコラム本文と小見出しを編集する
 */

session_start();

// 認証チェック
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// データベース接続
// .envファイルから設定を読み込む
require_once __DIR__ . '/../src/Utils/EnvironmentLoader.php';
$envLoader = new EnvironmentLoader();
$envConfig = $envLoader->load();

// 環境変数からデータベース設定を取得（.envファイルの設定を優先）
$dbHost = $envConfig['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$dbName = $envConfig['DB_NAME'] ?? $envConfig['DB_DATABASE'] ?? getenv('DB_NAME') ?: getenv('DB_DATABASE') ?: '_shinkenchiku_12';
$dbUsername = $envConfig['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
$dbPassword = $envConfig['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUsername,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection error in edit_building_column.php: " . $e->getMessage());
    error_log("Database connection config - Host: {$dbHost}, Database: {$dbName}, Username: {$dbUsername}");
    die("データベース接続エラーが発生しました。管理者にお問い合わせください。");
}

$message = '';
$messageClass = 'info';
$messageIsHtml = false; // メッセージにHTMLを含むかどうかのフラグ
$building = null;
$buildingSlug = '';
$columnTitle = '';
$columnText = '';

// POST処理: 保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $buildingSlug = trim($_POST['building_slug'] ?? '');
    $columnTitle = trim($_POST['column_title'] ?? '');
    $columnText = trim($_POST['building_column_text'] ?? '');
    
    if (empty($buildingSlug)) {
        $message = 'エラー: 建築物のslugまたはUIDを入力してください。';
        $messageClass = 'danger';
    } else {
        try {
            // 建築物の存在確認
            $stmt = $pdo->prepare("SELECT building_id, slug, uid, title FROM buildings_table_3 WHERE slug = ? OR uid = ? LIMIT 1");
            $stmt->execute([$buildingSlug, $buildingSlug]);
            $building = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$building) {
                $message = 'エラー: 建築物が見つかりませんでした。';
                $messageClass = 'danger';
            } else {
                // 文字数チェック
                if (mb_strlen($columnTitle) > 255) {
                    $message = 'エラー: 小見出しは255文字以内で入力してください。';
                    $messageClass = 'danger';
                } elseif (mb_strlen($columnText) > 16777215) {
                    $message = 'エラー: コラム本文が長すぎます（最大16MB）。';
                    $messageClass = 'danger';
                } else {
                    // 保存処理
                    $stmt = $pdo->prepare("
                        UPDATE buildings_table_3 
                        SET building_column_text = :building_column_text,
                            column_title = :column_title,
                            updated_at = NOW()
                        WHERE building_id = :building_id
                    ");
                    
                    $result = $stmt->execute([
                        ':building_column_text' => $columnText ?: null,
                        ':column_title' => $columnTitle ?: null,
                        ':building_id' => $building['building_id']
                    ]);
                    
                    if ($result) {
                        // 建築物詳細ページのURLを生成（キャッシュ無効化パラメータ付き）
                        $buildingSlugForUrl = $building['slug'] ?? '';
                        $baseUrl = isset($_SERVER['HTTP_HOST']) 
                            ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
                            : 'https://kenchikuka.com';
                        $buildingUrl = $baseUrl . '/buildings/' . urlencode($buildingSlugForUrl) . '?lang=ja&cache=0';
                        $buildingTitleEscaped = htmlspecialchars($building['title'], ENT_QUOTES, 'UTF-8');
                        $buildingUrlEscaped = htmlspecialchars($buildingUrl, ENT_QUOTES, 'UTF-8');
                        
                        $message = "✅ 保存成功: 「{$buildingTitleEscaped}」のコラムを更新しました。<br>" .
                                  "<small class='text-muted'>詳細ページ: <a href='{$buildingUrlEscaped}' target='_blank' class='text-decoration-none'>{$buildingUrlEscaped}</a> <i data-lucide='external-link' style='width: 14px; height: 14px; vertical-align: middle;'></i></small>";
                        $messageClass = 'success';
                        $messageIsHtml = true; // HTMLを含むメッセージであることを示すフラグ
                        // フォームをリセット
                        $buildingSlug = '';
                        $columnTitle = '';
                        $columnText = '';
                    } else {
                        $message = 'エラー: 保存に失敗しました。';
                        $messageClass = 'danger';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Column edit error: " . $e->getMessage());
            error_log("Column edit error trace: " . $e->getTraceAsString());
            
            // カラムが存在しない場合の特別なエラーメッセージ
            if (strpos($e->getMessage(), "Column not found") !== false || 
                strpos($e->getMessage(), "Unknown column") !== false) {
                $message = '⚠️ エラー: データベースにコラムカラムが存在しません。以下のSQLを実行してください：<br><br>' .
                          '<code style="background: #f5f5f5; padding: 10px; display: block; border-radius: 4px;">' .
                          'ALTER TABLE buildings_table_3<br>' .
                          'ADD COLUMN `building_column_text` MEDIUMTEXT NULL COMMENT \'建築物に関するコラム本文（1000文字前後、最大16MB）\',<br>' .
                          'ADD COLUMN `column_title` VARCHAR(255) NULL COMMENT \'コラムの小見出し（建築物ごとに個別設定）\';' .
                          '</code>';
                $messageClass = 'warning';
            } else {
                $message = 'エラー: データベースエラーが発生しました。詳細: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $messageClass = 'danger';
            }
        }
    }
}

// GET処理: 建築物の検索・読み込み
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['slug'])) {
    $buildingSlug = trim($_GET['slug'] ?? '');
    
    if (!empty($buildingSlug)) {
        try {
            $stmt = $pdo->prepare("
                SELECT building_id, slug, uid, title, building_column_text, column_title 
                FROM buildings_table_3 
                WHERE slug = ? OR uid = ? 
                LIMIT 1
            ");
            $stmt->execute([$buildingSlug, $buildingSlug]);
            $building = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($building) {
                $columnTitle = $building['column_title'] ?? '';
                $columnText = $building['building_column_text'] ?? '';
                
                // 建築物詳細ページのURLを生成（キャッシュ無効化パラメータ付き）
                $buildingSlugForUrl = $building['slug'] ?? '';
                $baseUrl = isset($_SERVER['HTTP_HOST']) 
                    ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
                    : 'https://kenchikuka.com';
                $buildingUrl = $baseUrl . '/buildings/' . urlencode($buildingSlugForUrl) . '?lang=ja&cache=0';
                $buildingTitleEscaped = htmlspecialchars($building['title'], ENT_QUOTES, 'UTF-8');
                $buildingUrlEscaped = htmlspecialchars($buildingUrl, ENT_QUOTES, 'UTF-8');
                
                // コラムデータが存在するかどうかでメッセージを分ける
                if (!empty($columnText)) {
                    $message = "建築物「{$buildingTitleEscaped}」のコラムを読み込みました。<br>" .
                              "<small class='text-muted'>詳細ページ: <a href='{$buildingUrlEscaped}' target='_blank' class='text-decoration-none'>{$buildingUrlEscaped}</a> <i data-lucide='external-link' style='width: 14px; height: 14px; vertical-align: middle;'></i></small>";
                    $messageClass = 'info';
                    $messageIsHtml = true; // HTMLを含むメッセージであることを示すフラグ
                } else {
                    $message = "建築物「{$buildingTitleEscaped}」を読み込みました。（コラムは未登録です）<br>" .
                              "<small class='text-muted'>詳細ページ: <a href='{$buildingUrlEscaped}' target='_blank' class='text-decoration-none'>{$buildingUrlEscaped}</a> <i data-lucide='external-link' style='width: 14px; height: 14px; vertical-align: middle;'></i></small>";
                    $messageClass = 'info';
                    $messageIsHtml = true; // HTMLを含むメッセージであることを示すフラグ
                }
            } else {
                $message = 'エラー: 建築物が見つかりませんでした。';
                $messageClass = 'danger';
            }
        } catch (PDOException $e) {
            error_log("Column load error: " . $e->getMessage());
            error_log("Column load error trace: " . $e->getTraceAsString());
            
            // カラムが存在しない場合の特別なエラーメッセージ
            if (strpos($e->getMessage(), "Column not found") !== false || 
                strpos($e->getMessage(), "Unknown column") !== false) {
                $message = '⚠️ エラー: データベースにコラムカラムが存在しません。以下のSQLを実行してください：<br><br>' .
                          '<code style="background: #f5f5f5; padding: 10px; display: block; border-radius: 4px;">' .
                          'ALTER TABLE buildings_table_3<br>' .
                          'ADD COLUMN `building_column_text` MEDIUMTEXT NULL COMMENT \'建築物に関するコラム本文（1000文字前後、最大16MB）\',<br>' .
                          'ADD COLUMN `column_title` VARCHAR(255) NULL COMMENT \'コラムの小見出し（建築物ごとに個別設定）\';' .
                          '</code>';
                $messageClass = 'warning';
            } else {
                $message = 'エラー: データベースエラーが発生しました。詳細: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $messageClass = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>建築物コラム編集 - PocketNavi Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .char-counter {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .char-counter.warning {
            color: #ffc107;
        }
        .char-counter.danger {
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i data-lucide="file-text" class="me-2" style="width: 24px; height: 24px;"></i>
                            建築物コラム編集
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageClass; ?> alert-dismissible fade show" role="alert">
                                <?php if (isset($messageIsHtml) && $messageIsHtml): ?>
                                    <?php echo $message; ?>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="columnForm">
                            <input type="hidden" name="action" value="save">
                            
                            <!-- 建築物識別 -->
                            <div class="mb-4">
                                <label for="building_slug" class="form-label fw-bold">
                                    <i data-lucide="search" class="me-1" style="width: 16px; height: 16px;"></i>
                                    建築物識別（slugまたはUID）
                                </label>
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control" 
                                           id="building_slug" 
                                           name="building_slug" 
                                           value="<?php echo htmlspecialchars($buildingSlug, ENT_QUOTES, 'UTF-8'); ?>"
                                           placeholder="例: kushiro-castle-hotel または SK_12345"
                                           required>
                                    <button type="button" 
                                            class="btn btn-outline-secondary" 
                                            onclick="loadBuilding()">
                                        <i data-lucide="search" class="me-1" style="width: 16px; height: 16px;"></i>
                                        検索
                                    </button>
                                </div>
                                <small class="form-text text-muted">
                                    建築物のslugまたはUIDを入力して「検索」ボタンをクリックすると、既存のコラムを読み込めます。
                                </small>
                            </div>
                            
                            <!-- 小見出し -->
                            <div class="mb-4">
                                <label for="column_title" class="form-label fw-bold">
                                    <i data-lucide="type" class="me-1" style="width: 16px; height: 16px;"></i>
                                    小見出し
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="column_title" 
                                       name="column_title" 
                                       value="<?php echo htmlspecialchars($columnTitle, ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="例: この建築について"
                                       maxlength="255">
                                <div class="char-counter mt-1">
                                    <span id="titleCharCount">0</span> / 255文字
                                </div>
                            </div>
                            
                            <!-- コラム本文 -->
                            <div class="mb-4">
                                <label for="building_column_text" class="form-label fw-bold">
                                    <i data-lucide="file-text" class="me-1" style="width: 16px; height: 16px;"></i>
                                    コラム本文
                                </label>
                                <textarea class="form-control" 
                                          id="building_column_text" 
                                          name="building_column_text" 
                                          rows="20"
                                          placeholder="コラム本文を入力してください（推奨: 1000文字前後）"><?php echo htmlspecialchars($columnText, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="char-counter mt-1">
                                    <span id="textCharCount">0</span> / 16,777,215文字
                                    <span class="ms-2">
                                        <small class="text-muted">（推奨: 1000文字前後）</small>
                                    </span>
                                </div>
                                <small class="form-text text-muted">
                                    Markdown形式で入力可能です。<br>
                                    <strong>改行の扱い：</strong>改行1回は段落内の改行（&lt;br&gt;）として表示されます。段落を分けたい場合は、空行（改行2回）を入れてください。空行で区切られた部分が&lt;p&gt;タグで囲まれ、段落間の間隔が広がります。
                                </small>
                            </div>
                            
                            <!-- ボタン -->
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i data-lucide="save" class="me-1" style="width: 16px; height: 16px;"></i>
                                    保存
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                    <i data-lucide="refresh-cw" class="me-1" style="width: 16px; height: 16px;"></i>
                                    リセット
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i data-lucide="arrow-left" class="me-1" style="width: 16px; height: 16px;"></i>
                                    管理画面に戻る
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Lucideアイコンの初期化
        lucide.createIcons();
        
        // 文字数カウンター
        function updateCharCount(elementId, counterId, maxLength) {
            const element = document.getElementById(elementId);
            const counter = document.getElementById(counterId);
            if (element && counter) {
                const length = element.value.length;
                counter.textContent = length.toLocaleString();
                
                // 警告表示
                const counterDiv = counter.parentElement;
                counterDiv.classList.remove('warning', 'danger');
                if (length > maxLength * 0.9) {
                    counterDiv.classList.add('danger');
                } else if (length > maxLength * 0.7) {
                    counterDiv.classList.add('warning');
                }
            }
        }
        
        // 小見出しの文字数カウンター
        document.getElementById('column_title').addEventListener('input', function() {
            updateCharCount('column_title', 'titleCharCount', 255);
        });
        
        // コラム本文の文字数カウンター
        document.getElementById('building_column_text').addEventListener('input', function() {
            updateCharCount('building_column_text', 'textCharCount', 16777215);
        });
        
        // 初期表示時に文字数を更新
        updateCharCount('column_title', 'titleCharCount', 255);
        updateCharCount('building_column_text', 'textCharCount', 16777215);
        
        // 建築物の読み込み
        function loadBuilding() {
            const slug = document.getElementById('building_slug').value.trim();
            if (slug) {
                window.location.href = '?slug=' + encodeURIComponent(slug);
            } else {
                alert('建築物のslugまたはUIDを入力してください。');
            }
        }
        
        // フォームリセット
        function resetForm() {
            if (confirm('フォームをリセットしますか？')) {
                document.getElementById('columnForm').reset();
                updateCharCount('column_title', 'titleCharCount', 255);
                updateCharCount('building_column_text', 'textCharCount', 16777215);
            }
        }
        
        // Enterキーで検索（建築物識別フィールド）
        document.getElementById('building_slug').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadBuilding();
            }
        });
        
        // URLからslugまたはUIDを自動抽出する機能
        const buildingSlugInput = document.getElementById('building_slug');
        
        // ペーストイベント
        buildingSlugInput.addEventListener('paste', function(e) {
            // ペースト処理を少し遅らせて、クリップボードの内容を取得
            setTimeout(function() {
                extractSlugOrUidFromUrl();
            }, 10);
        });
        
        // 入力イベント（URLが入力された場合を検出）
        buildingSlugInput.addEventListener('input', function(e) {
            const value = e.target.value.trim();
            // URL形式かどうかを判定（http:// または https:// を含む場合）
            if (value.includes('http://') || value.includes('https://') || value.includes('/buildings/') || value.includes('?uid=')) {
                extractSlugOrUidFromUrl();
            }
        });
        
        // URLからslugまたはUIDを抽出する関数
        function extractSlugOrUidFromUrl() {
            const input = document.getElementById('building_slug');
            let value = input.value.trim();
            
            if (!value) {
                return;
            }
            
            let extracted = null;
            
            // パターン1: /buildings/{slug} の形式
            // 例: https://kenchikuka.com/buildings/tokyo-data-communication-bureau?lang=ja
            const buildingsMatch = value.match(/\/buildings\/([^\/\?]+)/);
            if (buildingsMatch && buildingsMatch[1]) {
                extracted = buildingsMatch[1];
            }
            
            // パターン2: ?uid={uid} の形式
            // 例: https://kenchikuka.com/index_thumb.php?uid=SK_2024_12_172-0
            if (!extracted) {
                const uidMatch = value.match(/[?&]uid=([^&]+)/);
                if (uidMatch && uidMatch[1]) {
                    extracted = decodeURIComponent(uidMatch[1]);
                }
            }
            
            // パターン3: 既にslugまたはUIDのみが入力されている場合（URL形式でない）
            // 例: tokyo-data-communication-bureau または SK_2024_12_172-0
            if (!extracted && !value.includes('http') && !value.includes('/')) {
                // URL形式でない場合はそのまま使用
                extracted = value;
            }
            
            // 抽出した値があれば、入力フィールドに設定
            if (extracted) {
                input.value = extracted;
                // 視覚的なフィードバック（少しハイライト）
                input.style.backgroundColor = '#e7f3ff';
                setTimeout(function() {
                    input.style.backgroundColor = '';
                }, 1000);
            }
        }
    </script>
</body>
</html>
