<?php

/**
 * ホームコントローラー
 * メインページと建物・建築家の詳細ページを処理
 */
class HomeController {
    
    /**
     * メインページ（検索ページ）
     */
    public function index() {
        try {
            // 検索パラメータの取得
            $query = $_GET['q'] ?? '';
            $prefectures = $_GET['prefectures'] ?? '';
            $completionYears = $_GET['completionYears'] ?? '';
            $buildingTypes = $_GET['buildingTypes'] ?? '';
            $hasPhotos = isset($_GET['photos']);
            $hasVideos = isset($_GET['videos']);
            $page = (int)($_GET['page'] ?? 1);
            $lang = $_GET['lang'] ?? 'ja';
            $limit = 10;
            
            // データベース接続
            require_once __DIR__ . '/../../config/database_unified.php';
            $pdo = getDatabaseConnection();
            
            // 検索クエリの構築
            $sql = "SELECT * FROM buildings_table_3 WHERE 1=1";
            $params = [];
            
            if (!empty($query)) {
                $sql .= " AND (title LIKE ? OR description LIKE ?)";
                $params[] = "%$query%";
                $params[] = "%$query%";
            }
            
            if (!empty($prefectures)) {
                $sql .= " AND location LIKE ?";
                $params[] = "%$prefectures%";
            }
            
            if (!empty($completionYears)) {
                $sql .= " AND completionYear = ?";
                $params[] = $completionYears;
            }
            
            if (!empty($buildingTypes)) {
                $sql .= " AND buildingType LIKE ?";
                $params[] = "%$buildingTypes%";
            }
            
            if ($hasPhotos) {
                $sql .= " AND has_photo IS NOT NULL AND has_photo != '' AND has_photo != '0'";
            }
            
            if ($hasVideos) {
                $sql .= " AND has_video IS NOT NULL AND has_video != '' AND has_video != '0'";
            }
            
            // 総件数の取得
            $countSql = str_replace("SELECT *", "SELECT COUNT(*) as count", $sql);
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $totalCount = $stmt->fetch()['count'];
            
            // ページネーション
            $offset = ($page - 1) * $limit;
            $sql .= " ORDER BY completionYear DESC LIMIT $limit OFFSET $offset";
            
            // 検索実行
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $buildings = $stmt->fetchAll();
            
            // 翻訳関数の読み込み
            require_once __DIR__ . '/../../src/Utils/Translation.php';
            
            // 人気検索の取得
            $popularSearches = $this->getPopularSearches($lang);
            
            // ビューの表示
            $this->renderView('index', [
                'buildings' => $buildings,
                'totalCount' => $totalCount,
                'currentPage' => $page,
                'totalPages' => ceil($totalCount / $limit),
                'query' => $query,
                'prefectures' => $prefectures,
                'completionYears' => $completionYears,
                'buildingTypes' => $buildingTypes,
                'hasPhotos' => $hasPhotos,
                'hasVideos' => $hasVideos,
                'lang' => $lang,
                'popularSearches' => $popularSearches
            ]);
            
        } catch (Exception $e) {
            error_log("HomeController::index error: " . $e->getMessage());
            $this->renderError("検索中にエラーが発生しました。");
        }
    }
    
    /**
     * 建物詳細ページ
     */
    public function building($slug) {
        try {
            // データベース接続
            require_once __DIR__ . '/../../config/database_unified.php';
            $pdo = getDatabaseConnection();
            
            // 建物情報の取得
            $stmt = $pdo->prepare("SELECT * FROM buildings_table_3 WHERE uid = ?");
            $stmt->execute([$slug]);
            $building = $stmt->fetch();
            
            if (!$building) {
                $this->renderError("建物が見つかりません。");
                return;
            }
            
            // 建築物ページ閲覧ログを記録
            try {
                require_once __DIR__ . '/../../src/Services/SearchLogService.php';
                $searchLogService = new SearchLogService();
                $lang = $_GET['lang'] ?? 'ja';
                $searchLogService->logPageView('building', $slug, $building['title'] ?? $building['titleEn'] ?? $slug, [
                    'building_id' => $building['building_id'] ?? null,
                    'lang' => $lang
                ]);
            } catch (Exception $e) {
                error_log("Building page view log error: " . $e->getMessage());
            }
            
            // 翻訳関数の読み込み
            require_once __DIR__ . '/../../src/Utils/Translation.php';
            
            // ビューの表示
            $this->renderView('building_detail', [
                'building' => $building,
                'lang' => $_GET['lang'] ?? 'ja'
            ]);
            
        } catch (Exception $e) {
            error_log("HomeController::building error: " . $e->getMessage());
            $this->renderError("建物情報の取得中にエラーが発生しました。");
        }
    }
    
    /**
     * 建築家詳細ページ
     */
    public function architect($slug) {
        try {
            // データベース接続
            require_once __DIR__ . '/../../config/database_unified.php';
            $pdo = getDatabaseConnection();
            
            // 建築家情報の取得
            $stmt = $pdo->prepare("SELECT * FROM individual_architects_3 WHERE slug = ?");
            $stmt->execute([$slug]);
            $architect = $stmt->fetch();
            
            if (!$architect) {
                $this->renderError("建築家が見つかりません。");
                return;
            }
            
            // 建築家ページ閲覧ログを記録
            try {
                require_once __DIR__ . '/../../src/Services/SearchLogService.php';
                $searchLogService = new SearchLogService();
                $lang = $_GET['lang'] ?? 'ja';
                $searchLogService->logPageView('architect', $slug, $architect['name_ja'] ?? $architect['name_en'] ?? $slug, [
                    'architect_id' => $architect['individual_architect_id'] ?? null,
                    'lang' => $lang
                ]);
            } catch (Exception $e) {
                error_log("Architect page view log error: " . $e->getMessage());
            }
            
            // 翻訳関数の読み込み
            require_once __DIR__ . '/../../src/Utils/Translation.php';
            
            // ビューの表示
            $this->renderView('architect_detail', [
                'architect' => $architect,
                'lang' => $_GET['lang'] ?? 'ja'
            ]);
            
        } catch (Exception $e) {
            error_log("HomeController::architect error: " . $e->getMessage());
            $this->renderError("建築家情報の取得中にエラーが発生しました。");
        }
    }
    
    /**
     * 人気検索の取得
     */
    private function getPopularSearches($lang) {
        try {
            require_once __DIR__ . '/../../config/database_unified.php';
            $pdo = getDatabaseConnection();
            
            $stmt = $pdo->prepare("SELECT * FROM popular_searches ORDER BY search_count DESC LIMIT 10");
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("HomeController::getPopularSearches error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ビューの表示
     */
    private function renderView($viewName, $data = []) {
        // データを変数として展開
        extract($data);
        
        // ビューファイルのパス
        $viewPath = __DIR__ . '/../../src/Views/' . $viewName . '.php';
        
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            // ビューファイルが存在しない場合は簡単なHTMLを表示
            $this->renderSimpleView($viewName, $data);
        }
    }
    
    /**
     * 簡単なビューの表示
     */
    private function renderSimpleView($viewName, $data = []) {
        extract($data);
        
        echo '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PocketNavi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .building-card { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .search-form { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PocketNavi - 建築検索システム</h1>';
        
        if ($viewName === 'index') {
            echo '<div class="search-form">
                <h2>建物検索</h2>
                <form method="GET">
                    <div class="form-group">
                        <label for="q">キーワード</label>
                        <input type="text" id="q" name="q" value="' . htmlspecialchars($query ?? '') . '">
                    </div>
                    <div class="form-group">
                        <label for="prefectures">都道府県</label>
                        <input type="text" id="prefectures" name="prefectures" value="' . htmlspecialchars($prefectures ?? '') . '">
                    </div>
                    <div class="form-group">
                        <label for="completionYears">完成年</label>
                        <input type="number" id="completionYears" name="completionYears" value="' . htmlspecialchars($completionYears ?? '') . '">
                    </div>
                    <button type="submit">検索</button>
                </form>
            </div>';
            
            if (isset($buildings) && !empty($buildings)) {
                echo '<h2>検索結果 (' . count($buildings) . '件)</h2>';
                foreach ($buildings as $building) {
                    echo '<div class="building-card">
                        <h3>' . htmlspecialchars($building['title'] ?? '') . '</h3>
                        <p>場所: ' . htmlspecialchars($building['location'] ?? '') . '</p>
                        <p>完成年: ' . htmlspecialchars($building['completionYear'] ?? '') . '</p>
                        <p>建築タイプ: ' . htmlspecialchars($building['buildingType'] ?? '') . '</p>
                    </div>';
                }
            } else {
                echo '<p>検索結果が見つかりませんでした。</p>';
            }
        }
        
        echo '</div>
</body>
</html>';
    }
    
    /**
     * エラーページの表示
     */
    private function renderError($message) {
        echo '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>エラー - PocketNavi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .error-icon { font-size: 48px; color: #e74c3c; margin-bottom: 20px; }
        h1 { color: #2c3e50; margin-bottom: 20px; }
        p { color: #7f8c8d; line-height: 1.6; }
        .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; }
        .back-link:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">⚠️</div>
        <h1>エラーが発生しました</h1>
        <p>' . htmlspecialchars($message) . '</p>
        <a href="/" class="back-link">トップページに戻る</a>
    </div>
</body>
</html>';
    }
}
?>