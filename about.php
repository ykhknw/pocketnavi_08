<?php
// PocketNavi - About Page
require_once 'config/database_unified.php';
require_once 'src/Views/includes/functions.php';
require_once 'src/Utils/SEOHelper.php';

// 言語設定（URLクエリパラメータから取得）
$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['ja', 'en']) ? $_GET['lang'] : 'ja';

// ページタイトル
$pageTitle = $lang === 'ja' ? 'このサイトについて' : 'About This Site';

// SEOメタタグの生成
$seoData = SEOHelper::generateMetaTags('about', [], $lang);
$structuredData = SEOHelper::generateStructuredData('about', [], $lang);
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
                        
                        <!-- About Content -->
                        <div class="mb-4">
                            <?php if ($lang === 'ja'): ?>
                                <p class="lead">このホームページは、建築を志して街歩きをする人たちのための、検索型建築作品データベースです。</p>
                                <p>作品名、設計者名、用途、所在地、現在地からの距離など、さまざまな条件で建築作品を探すことができます。</p>
                                <p>地図表示・写真・概要などの情報を通じて、自分だけの建築散歩ルートを組み立てる参考にもなります。</p>
                            <?php else: ?>
                                <p class="lead">This homepage is a searchable architectural works database for people who aspire to architecture and enjoy city walks.</p>
                                <p>You can search for architectural works using various criteria such as work name, architect name, purpose, location, and distance from your current location.</p>
                                <p>Through information such as map displays, photos, and overviews, it can also serve as a reference for creating your own architectural walking routes.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Operation Policy -->
                        <div class="mb-4">
                            <h3 class="h4 mb-3"><?php echo $lang === 'ja' ? '運営方針' : 'Operation Policy'; ?></h3>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i data-lucide="check-circle" class="text-success me-2" style="width: 16px; height: 16px;"></i>
                                    <?php if ($lang === 'ja'): ?>
                                        本サイトは非営利で運営されており、主に建築学習者や一般の街歩き愛好者向けに開かれています。
                                    <?php else: ?>
                                        This site is operated non-profit and is open primarily to architecture students and general city walk enthusiasts.
                                    <?php endif; ?>
                                </li>
                                <li class="mb-2">
                                    <i data-lucide="check-circle" class="text-success me-2" style="width: 16px; height: 16px;"></i>
                                    <?php if ($lang === 'ja'): ?>
                                        情報は随時更新され、不正確なデータがあれば<a href="contact.php?lang=<?php echo $lang; ?>" class="text-decoration-none">お問い合わせ</a>よりご連絡ください。
                                    <?php else: ?>
                                        Information is updated as needed. If there is any inaccurate data, please contact us via <a href="contact.php?lang=<?php echo $lang; ?>" class="text-decoration-none">Contact Us</a>.
                                    <?php endif; ?>
                                </li>
                                <li class="mb-2">
                                    <i data-lucide="check-circle" class="text-success me-2" style="width: 16px; height: 16px;"></i>
                                    <?php if ($lang === 'ja'): ?>
                                        リンクフリーです。事前・事後の連絡なくご自由にリンクしていただいて構いません。
                                    <?php else: ?>
                                        Linking is free. You are welcome to link to us without prior or post notification.
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Copyright and Citation -->
                        <div class="mb-4">
                            <h3 class="h4 mb-3"><?php echo $lang === 'ja' ? '著作権・引用について' : 'Copyright and Citation'; ?></h3>
                            <div class="text-muted">
                                <?php if ($lang === 'ja'): ?>
                                    <p>掲載されている文章・画像の著作権は、特記がない限り運営者または提供者に帰属します。</p>
                                    <p>営利目的での転載や再利用をご希望の場合は、事前に<a href="contact.php?lang=<?php echo $lang; ?>" class="text-decoration-none">ご連絡</a>をお願いします。</p>
                                <?php else: ?>
                                    <p>The copyright of the articles and images posted belongs to the operator or provider, unless otherwise specified.</p>
                                    <p>If you wish to reproduce or reuse for commercial purposes, please contact us in advance via <a href="contact.php?lang=<?php echo $lang; ?>" class="text-decoration-none">Contact Us</a>.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Back to Home -->
                        <div class="mt-4">
                            <a href="index.php?lang=<?php echo $lang; ?>" class="btn btn-primary">
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
</body>
</html>
