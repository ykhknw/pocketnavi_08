<?php
// PocketNavi - Contact Us Page
require_once 'config/database_unified.php';
// InputValidatorを先に読み込む（functions.phpが別のInputValidatorを読み込もうとするのを防ぐため）
if (!class_exists('InputValidator')) {
    require_once 'src/Utils/InputValidator.php';
}
require_once 'src/Views/includes/functions.php';
require_once 'src/Utils/SecurityHelper.php';
require_once 'src/Utils/SecurityHeaders.php';
require_once 'src/Utils/SEOHelper.php';

// セッションを開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// セキュリティヘッダーを設定
SecurityHeaders::setHeadersByEnvironment();

// 言語設定（URLクエリパラメータから取得）
$lang = InputValidator::validateLanguage($_GET['lang'] ?? 'ja');

// ページタイトル
$pageTitle = $lang === 'ja' ? 'お問い合わせ' : 'Contact Us';

// SEOメタタグの生成
$seoData = SEOHelper::generateMetaTags('contact', [], $lang);
$structuredData = SEOHelper::generateStructuredData('contact', [], $lang);

// フォーム送信処理
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // デバッグログは削除（本番環境での動作確認完了）
    
    // CSRFトークンの検証
    $csrfToken = $_POST['csrf_token'] ?? '';
    $csrfValidationEnabled = true; // ログ確認により正常動作が確認できたため有効化
    
    if ($csrfValidationEnabled && !SecurityHelper::validateCsrfToken($csrfToken)) {
        $message = $lang === 'ja' ? 'セキュリティエラーが発生しました。' : 'Security error occurred.';
        $messageType = 'danger';
    } else {
        // レート制限のチェック
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!SecurityHelper::checkRateLimit($clientIp, 5, 3600)) {
            $message = $lang === 'ja' ? '送信回数が多すぎます。しばらく時間をおいてから再度お試しください。' : 'Too many requests. Please try again later.';
            $messageType = 'danger';
        } else {
            $name = InputValidator::validateString($_POST['name'] ?? '', 100);
            $email = InputValidator::validateEmail($_POST['email'] ?? '');
            $message_content = InputValidator::validateString($_POST['message'] ?? '', 2000);
            
            // バリデーション
            if ($name === null || $email === null || $message_content === null) {
                $message = $lang === 'ja' ? 'すべての項目を正しく入力してください。' : 'Please fill in all fields correctly.';
                $messageType = 'danger';
            } else {
                // メール送信処理（実際の実装では適切なメール送信ライブラリを使用）
                // ここでは簡単な例として、ログファイルに記録
                $logData = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'name' => $name,
                    'email' => $email,
                    'message' => $message_content,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ];
                
                $logFile = 'logs/contact_' . date('Y-m') . '.log';
                if (!is_dir('logs')) {
                    mkdir('logs', 0755, true);
                }
                
                file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
                
                $message = $lang === 'ja' ? 'お問い合わせを受け付けました。ありがとうございます。' : 'Thank you for your inquiry. We have received your message.';
                $messageType = 'success';
                
                // フォームをクリア
                $name = $email = $message_content = '';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang === 'ja' ? 'ja' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Permissions Policy for Geolocation -->
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    
    <!-- SEO Meta Tags -->
    <?php echo SEOHelper::renderMetaTags($seoData); ?>
    
    <!-- Structured Data (JSON-LD) -->
    <?php echo SEOHelper::renderStructuredData($structuredData); ?>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.js" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
</head>
<body>
    <!-- Header -->
    <?php include 'src/Views/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-body">
                        <h1 class="h2 mb-4"><?php echo $pageTitle; ?></h1>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <?php 
                            // CSRFトークンを確実に生成
                            $csrfToken = SecurityHelper::generateCsrfToken();
                            ?>
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escapeAttribute($csrfToken); ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">
                                        <i data-lucide="user" class="me-1" style="width: 16px; height: 16px;"></i>
                                        <?php echo $lang === 'ja' ? 'お名前' : 'Your Name'; ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">
                                        <i data-lucide="mail" class="me-1" style="width: 16px; height: 16px;"></i>
                                        <?php echo $lang === 'ja' ? 'メールアドレス' : 'Email Address'; ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">
                                    <i data-lucide="message-square" class="me-1" style="width: 16px; height: 16px;"></i>
                                    <?php echo $lang === 'ja' ? 'お問い合わせ内容' : 'Inquiry Details'; ?>
                                    <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="message" name="message" rows="6" 
                                          placeholder="<?php echo $lang === 'ja' ? 'お問い合わせ内容をご記入ください...' : 'Please enter your inquiry details...'; ?>" required><?php echo htmlspecialchars($message_content ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">
                                    <i data-lucide="send" class="me-2" style="width: 16px; height: 16px;"></i>
                                    <?php echo $lang === 'ja' ? '送信' : 'Send'; ?>
                                </button>
                                <button type="reset" class="btn btn-outline-secondary ms-2">
                                    <i data-lucide="refresh-cw" class="me-2" style="width: 16px; height: 16px;"></i>
                                    <?php echo $lang === 'ja' ? 'リセット' : 'Reset'; ?>
                                </button>
                            </div>
                        </form>
                        
                        
                        <!-- Back to Home -->
                        <div class="mt-4">
                            <a href="index.php?lang=<?php echo $lang; ?>" class="btn btn-outline-primary">
                                <i data-lucide="home" class="me-2" style="width: 16px; height: 16px;"></i>
                                <?php echo $lang === 'ja' ? 'ホームに戻る' : 'Back to Home'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'src/Views/includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        lucide.createIcons();
        
        // 言語切り替え機能
        function initLanguageSwitch() {
            const languageSwitch = document.getElementById('languageSwitch');
            if (languageSwitch) {
                languageSwitch.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // 現在のURLを取得
                    const currentUrl = new URL(window.location);
                    const currentLang = currentUrl.searchParams.get('lang') || 'ja';
                    
                    // 言語を切り替え
                    const newLang = currentLang === 'ja' ? 'en' : 'ja';
                    currentUrl.searchParams.set('lang', newLang);
                    
                    // ページをリロード
                    window.location.href = currentUrl.toString();
                });
            }
        }
        
        // ページ読み込み時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            initLanguageSwitch();
        });
    </script>
    
    <!-- Photo Carousel Modal -->
    <?php include 'src/Views/includes/photo_carousel_modal.php'; ?>
</body>
</html>
