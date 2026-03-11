<?php
/**
 * 簡易キャッシュ管理画面 - セキュリティ強化版
 * 本番環境用（依存関係を最小限に）
 */

// セキュリティヘッダーの設定
if (file_exists(__DIR__ . '/../src/Security/SecurityHeaders.php')) {
    require_once __DIR__ . '/../src/Security/SecurityHeaders.php';
    $securityHeaders = new SecurityHeaders();
    $securityHeaders->setProductionMode();
    $securityHeaders->sendHeaders();
}

// セキュアエラーハンドリングの設定
if (file_exists(__DIR__ . '/../src/Security/SecureErrorHandler.php')) {
    require_once __DIR__ . '/../src/Security/SecureErrorHandler.php';
    $isProduction = !isset($_GET['debug']);
    $errorHandler = new SecureErrorHandler($isProduction);
}

// セキュリティ: 簡易認証
$adminPassword = 'yuki11'; // 本番環境では強力なパスワードに変更

// 認証チェック
if (!isset($_POST['password']) || $_POST['password'] !== $adminPassword) {
    if (isset($_POST['password'])) {
        $error = 'パスワードが正しくありません';
    }
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>検索キャッシュ管理 - 認証</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">検索キャッシュ管理</h5>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="password" class="form-label">パスワード</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">ログイン</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 認証成功後の処理
$message = '';
$error = '';

// キャッシュ設定ファイルの読み込み
$cacheConfigFile = '../config/cache_config.php';
$cacheConfig = [];
if (file_exists($cacheConfigFile)) {
    $cacheConfig = include $cacheConfigFile;
} else {
    // デフォルト設定
    $cacheConfig = [
        'default_ttl' => 3600,
        'ttl_options' => [
            900 => '15分',
            1800 => '30分',
            3600 => '1時間',
            7200 => '2時間',
            14400 => '4時間',
            28800 => '8時間',
            86400 => '24時間',
            604800 => '1週間'
        ],
        'cache_dir' => 'cache/search',
        'enabled' => true
    ];
}

// キャッシュクリア処理
if (isset($_POST['clear_cache'])) {
    try {
        // エラーレポートを一時的に抑制
        $oldErrorReporting = error_reporting(E_ERROR | E_PARSE);
        
        // キャッシュディレクトリのパスを動的に取得
        $cacheDir = null;
        $possiblePaths = [
            'cache/search',
            '../cache/search',
            './cache/search',
            'cache/search_results'
        ];
        
        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                $cacheDir = $path;
                break;
            }
        }
        
        if (!$cacheDir) {
            $cacheDir = $cacheConfig['cache_dir'] ?? 'cache/search';
        }
        
        $deletedCount = 0;
        
        if (is_dir($cacheDir)) {
            $cacheFiles = @glob($cacheDir . '/*.cache');
            if ($cacheFiles === false) {
                $cacheFiles = [];
            }
            foreach ($cacheFiles as $file) {
                if (file_exists($file) && @unlink($file)) {
                    $deletedCount++;
                }
            }
        }
        
        // エラーレポートを元に戻す
        error_reporting($oldErrorReporting);
        
        $message = "キャッシュをクリアしました。削除されたファイル数: {$deletedCount}件 (使用ディレクトリ: {$cacheDir})";
    } catch (Exception $e) {
        $error = "キャッシュクリア中にエラーが発生しました: " . $e->getMessage();
    }
}

// キャッシュ有効期限の更新処理
if (isset($_POST['update_ttl'])) {
    try {
        $newTTL = (int)$_POST['ttl'];
        
        // 設定ファイルの更新
        $configContent = "<?php\n/**\n * キャッシュ設定ファイル\n */\n\nreturn [\n";
        $configContent .= "    'default_ttl' => $newTTL,\n";
        $configContent .= "    'ttl_options' => [\n";
        foreach ($cacheConfig['ttl_options'] as $ttl => $label) {
            $configContent .= "        $ttl => '$label',\n";
        }
        $configContent .= "    ],\n";
        $configContent .= "    'cache_dir' => 'cache/search',\n";
        $configContent .= "    'enabled' => true,\n";
        $configContent .= "    'max_files' => 50000,\n";
        $configContent .= "    'max_size_mb' => 500\n";
        $configContent .= "];\n?>";
        
        if (file_put_contents($cacheConfigFile, $configContent)) {
            $cacheConfig['default_ttl'] = $newTTL;
            $message = "キャッシュの有効期限を更新しました。新しい有効期限: " . $cacheConfig['ttl_options'][$newTTL] ?? $newTTL . "秒";
        } else {
            $error = "設定ファイルの更新に失敗しました。";
        }
    } catch (Exception $e) {
        $error = "有効期限の更新中にエラーが発生しました: " . $e->getMessage();
    }
}

// 古いキャッシュの自動削除処理
if (isset($_POST['cleanup_old_cache'])) {
    try {
        // エラーレポートを一時的に抑制
        $oldErrorReporting = error_reporting(E_ERROR | E_PARSE);
        
        // キャッシュディレクトリのパスを動的に取得（分析処理と同じロジック）
        $cacheDir = null;
        $possiblePaths = [
            'cache/search',
            '../cache/search',
            './cache/search',
            'cache/search_results'
        ];
        
        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                $cacheDir = $path;
                break;
            }
        }
        
        if (!$cacheDir) {
            $cacheDir = $cacheConfig['cache_dir'] ?? 'cache/search';
        }
        
        $deletedCount = 0;
        $deletedSize = 0;
        
        if (is_dir($cacheDir)) {
            $cacheFiles = @glob($cacheDir . '/*.cache');
            if ($cacheFiles === false) {
                $cacheFiles = [];
            }
            $currentTime = time();
            
            foreach ($cacheFiles as $file) {
                if (!file_exists($file)) {
                    continue;
                }
                
                $fileTime = @filemtime($file);
                if ($fileTime === false) {
                    continue;
                }
                
                $fileAge = $currentTime - $fileTime;
                $fileSize = @filesize($file);
                if ($fileSize === false) {
                    $fileSize = 0;
                }
                
                // 7日以上古いファイルを削除
                if ($fileAge > 604800) { // 7日 = 604800秒
                    if (@unlink($file)) {
                        $deletedCount++;
                        $deletedSize += $fileSize;
                    }
                }
            }
        }
        
        // エラーレポートを元に戻す
        error_reporting($oldErrorReporting);
        
        $deletedSizeMB = round($deletedSize / 1024 / 1024, 2);
        if ($deletedCount > 0) {
            $message = "古いキャッシュを削除しました。削除されたファイル数: {$deletedCount}件、削除されたサイズ: {$deletedSizeMB}MB (使用ディレクトリ: {$cacheDir})";
        } else {
            $message = "削除対象の古いキャッシュはありませんでした。(使用ディレクトリ: {$cacheDir})";
        }
    } catch (Exception $e) {
        $error = "古いキャッシュの削除中にエラーが発生しました: " . $e->getMessage();
    }
}

// キャッシュファイル数の制限処理
if (isset($_POST['limit_cache_files'])) {
    try {
        // エラーレポートを一時的に抑制
        $oldErrorReporting = error_reporting(E_ERROR | E_PARSE);
        
        // キャッシュディレクトリのパスを動的に取得（分析処理と同じロジック）
        $cacheDir = null;
        $possiblePaths = [
            'cache/search',
            '../cache/search',
            './cache/search',
            'cache/search_results'
        ];
        
        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                $cacheDir = $path;
                break;
            }
        }
        
        if (!$cacheDir) {
            $cacheDir = $cacheConfig['cache_dir'] ?? 'cache/search';
        }
        
        $maxFiles = 50000; // 最大ファイル数
        $deletedCount = 0;
        $deletedSize = 0;
        
        if (is_dir($cacheDir)) {
            $cacheFiles = @glob($cacheDir . '/*.cache');
            if ($cacheFiles === false) {
                $cacheFiles = [];
            }
            $currentFileCount = count($cacheFiles);
            
            if ($currentFileCount > $maxFiles) {
                // ファイルを更新日時でソート（古い順）
                usort($cacheFiles, function($a, $b) {
                    $timeA = @filemtime($a);
                    $timeB = @filemtime($b);
                    if ($timeA === false) $timeA = 0;
                    if ($timeB === false) $timeB = 0;
                    return $timeA - $timeB;
                });
                
                // 古いファイルから削除
                $filesToDelete = $currentFileCount - $maxFiles;
                for ($i = 0; $i < $filesToDelete; $i++) {
                    if (isset($cacheFiles[$i]) && file_exists($cacheFiles[$i])) {
                        $fileSize = @filesize($cacheFiles[$i]);
                        if ($fileSize === false) {
                            $fileSize = 0;
                        }
                        if (@unlink($cacheFiles[$i])) {
                            $deletedCount++;
                            $deletedSize += $fileSize;
                        }
                    }
                }
            }
        }
        
        // エラーレポートを元に戻す
        error_reporting($oldErrorReporting);
        
        $deletedSizeMB = round($deletedSize / 1024 / 1024, 2);
        if ($deletedCount > 0) {
            $message = "キャッシュファイル数を制限しました。削除されたファイル数: {$deletedCount}件、削除されたサイズ: {$deletedSizeMB}MB (使用ディレクトリ: {$cacheDir})";
        } else {
            $message = "キャッシュファイル数は制限内です。削除されたファイル: 0件 (現在のファイル数: " . (isset($currentFileCount) ? $currentFileCount : 0) . "件、使用ディレクトリ: {$cacheDir})";
        }
    } catch (Exception $e) {
        $error = "キャッシュファイル数の制限中にエラーが発生しました: " . $e->getMessage();
    }
}

// キャッシュ分析処理（常に実行）
$cacheAnalysis = null;
$analysisError = null;
try {
    // エラーレポートを一時的に抑制
    $oldErrorReporting = error_reporting(E_ERROR | E_PARSE);
    
    // キャッシュディレクトリのパスを動的に取得
    $cacheDir = null;
    $possiblePaths = [
        'cache/search',
        '../cache/search',
        './cache/search',
        'cache/search_results'
    ];
    
    foreach ($possiblePaths as $path) {
        if (is_dir($path)) {
            $cacheDir = $path;
            break;
        }
    }
    
    if (!$cacheDir) {
        $cacheDir = $cacheConfig['cache_dir'] ?? 'cache/search';
    }
    
    if (is_dir($cacheDir)) {
        $cacheFiles = @glob($cacheDir . '/*.cache');
        if ($cacheFiles === false) {
            $cacheFiles = [];
        }
        $totalFiles = count($cacheFiles);
        
        if ($totalFiles > 0) {
            // 検索タイプの統計
            $searchTypes = [
                'prefecture' => 0,      // 都道府県検索
                'completion_year' => 0, // 完成年検索
                'architect_slug' => 0,  // 建築家検索
                'building_slug' => 0,   // 建築物詳細
                'free_text' => 0,       // フリーワード検索
                'location' => 0,        // 位置情報検索
                'mixed' => 0,           // 複合検索
                'unknown' => 0          // 不明
            ];
            
            // サンプル分析（最初の20ファイル）
            $sampleSize = min(20, $totalFiles);
            $sampleFiles = array_slice($cacheFiles, 0, $sampleSize);
            
            foreach ($sampleFiles as $file) {
                $cacheContent = file_get_contents($file);
                if ($cacheContent !== false) {
                    $cacheData = json_decode($cacheContent, true);
                    if ($cacheData !== null) {
                        $searchType = determineSearchType($cacheData);
                        $searchTypes[$searchType]++;
                        
                        // デバッグ用：最初のファイルの構造をログに記録
                        if ($searchType === 'unknown' && count($searchTypes) === 1) {
                            error_log("Cache file structure debug: " . json_encode($cacheData, JSON_PRETTY_PRINT));
                        }
                    }
                }
            }
            
            // 統計の計算
            $analysisResults = [];
            foreach ($searchTypes as $type => $count) {
                $percentage = $sampleSize > 0 ? round(($count / $sampleSize) * 100, 1) : 0;
                $estimatedTotal = $totalFiles > 0 ? round(($count / $sampleSize) * $totalFiles) : 0;
                $analysisResults[$type] = [
                    'count' => $count,
                    'percentage' => $percentage,
                    'estimated_total' => $estimatedTotal
                ];
            }
            
            $cacheAnalysis = [
                'total_files' => $totalFiles,
                'sample_size' => $sampleSize,
                'results' => $analysisResults
            ];
        } else {
            $analysisError = "キャッシュファイルが見つかりません";
        }
    } else {
        $currentDir = getcwd();
        $analysisError = "キャッシュディレクトリが存在しません: $cacheDir (現在のディレクトリ: $currentDir)";
    }
    
    // エラーレポートを元に戻す
    error_reporting($oldErrorReporting);
} catch (Exception $e) {
    $analysisError = "キャッシュ分析中にエラーが発生しました: " . $e->getMessage();
    // エラーレポートを元に戻す
    if (isset($oldErrorReporting)) {
        error_reporting($oldErrorReporting);
    }
}

/**
 * 検索タイプを判別する関数
 */
function determineSearchType($cacheData) {
    // 検索パラメータの取得（複数の可能性をチェック）
    $params = $cacheData['search_params'] ?? $cacheData['params'] ?? [];
    
    // デバッグ用：パラメータの内容をログに記録
    if (empty($params)) {
        error_log("No search parameters found in cache data. Available keys: " . implode(', ', array_keys($cacheData)));
        return 'unknown';
    }
    
    // 建築物スラッグ検索
    if (!empty($params['buildingSlug']) || !empty($params['building_slug'])) {
        return 'building_slug';
    }
    
    // 建築家スラッグ検索
    if (!empty($params['architectSlug']) || !empty($params['architect_slug']) || !empty($params['architectsSlug'])) {
        return 'architect_slug';
    }
    
    // 位置情報検索
    if ((isset($params['userLat']) && isset($params['userLng'])) || 
        (isset($params['user_lat']) && isset($params['user_lng']))) {
        return 'location';
    }
    
    // 複合検索の判定
    $conditionCount = 0;
    
    if (!empty($params['prefectures']) || !empty($params['prefecture'])) {
        $conditionCount++;
    }
    
    if (!empty($params['completionYears']) || !empty($params['completion_year'])) {
        $conditionCount++;
    }
    
    if (!empty($params['query']) || !empty($params['search_query'])) {
        $conditionCount++;
    }
    
    if (!empty($params['hasPhotos']) || !empty($params['has_photos']) || 
        !empty($params['hasVideos']) || !empty($params['has_videos'])) {
        $conditionCount++;
    }
    
    if ($conditionCount > 1) {
        return 'mixed';
    }
    
    // 単一条件の判定
    if (!empty($params['prefectures']) || !empty($params['prefecture'])) {
        return 'prefecture';
    }
    
    if (!empty($params['completionYears']) || !empty($params['completion_year'])) {
        return 'completion_year';
    }
    
    if (!empty($params['query']) || !empty($params['search_query'])) {
        return 'free_text';
    }
    
    if (!empty($params['hasPhotos']) || !empty($params['has_photos'])) {
        return 'has_photos';
    }
    
    if (!empty($params['hasVideos']) || !empty($params['has_videos'])) {
        return 'has_videos';
    }
    
    return 'unknown';
}

// キャッシュ情報の取得
$cacheInfo = [];
$debugInfo = [];
try {
    // エラーレポートを一時的に抑制
    $oldErrorReporting = error_reporting(E_ERROR | E_PARSE);
    
    // 複数のキャッシュディレクトリパスを試行
    $possibleCacheDirs = [
        'cache/search',
        '../cache/search',
        './cache/search',
        'cache/search_results'
    ];
    
    $cacheDir = null;
    foreach ($possibleCacheDirs as $dir) {
        if (is_dir($dir)) {
            $cacheDir = $dir;
            break;
        }
    }
    
    $debugInfo['checked_dirs'] = $possibleCacheDirs;
    $debugInfo['found_dir'] = $cacheDir;
    $debugInfo['current_dir'] = getcwd();
    
    if ($cacheDir) {
        $cacheFiles = @glob($cacheDir . '/*.cache');
        if ($cacheFiles === false) {
            $cacheFiles = [];
        }
        $totalSize = 0;
        
        foreach ($cacheFiles as $file) {
            if (file_exists($file)) {
                $fileSize = @filesize($file);
                if ($fileSize !== false) {
                    $totalSize += $fileSize;
                }
            }
        }
        
        $cacheInfo = [
            'fileCount' => count($cacheFiles),
            'totalSize' => $totalSize,
            'totalSizeMB' => round($totalSize / 1024 / 1024, 2),
            'cacheDir' => $cacheDir
        ];
    } else {
        $debugInfo['error'] = 'キャッシュディレクトリが見つかりません';
    }
    
    // エラーレポートを元に戻す
    error_reporting($oldErrorReporting);
} catch (Exception $e) {
    if (!$error) { // 既存のエラーがない場合のみ設定
        $error = "キャッシュ情報の取得中にエラーが発生しました: " . $e->getMessage();
    }
    $debugInfo['exception'] = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>検索キャッシュ管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2>検索キャッシュ管理</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <!-- キャッシュ情報 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">キャッシュ情報</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($cacheInfo)): ?>
                            <p><strong>ファイル数:</strong> <?php echo $cacheInfo['fileCount']; ?> 件</p>
                            <p><strong>総サイズ:</strong> <?php echo $cacheInfo['totalSizeMB']; ?> MB</p>
                            <p><strong>キャッシュディレクトリ:</strong> <?php echo htmlspecialchars($cacheInfo['cacheDir']); ?></p>
                        <?php else: ?>
                            <p class="text-muted">キャッシュ情報を取得できませんでした。</p>
                            
                            <!-- デバッグ情報 -->
                            <?php if (!empty($debugInfo)): ?>
                                <div class="mt-3">
                                    <h6>デバッグ情報:</h6>
                                    <ul class="small">
                                        <li><strong>現在のディレクトリ:</strong> <?php echo htmlspecialchars($debugInfo['current_dir']); ?></li>
                                        <li><strong>確認したディレクトリ:</strong> <?php echo implode(', ', $debugInfo['checked_dirs']); ?></li>
                                        <li><strong>見つかったディレクトリ:</strong> <?php echo $debugInfo['found_dir'] ? htmlspecialchars($debugInfo['found_dir']) : 'なし'; ?></li>
                                        <?php if (isset($debugInfo['error'])): ?>
                                            <li><strong>エラー:</strong> <?php echo htmlspecialchars($debugInfo['error']); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- キャッシュ有効期限設定 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">キャッシュ有効期限設定</h5>
                    </div>
                    <div class="card-body">
                        <p>検索結果キャッシュの有効期限を設定します。現在の設定: <strong><?php echo $cacheConfig['ttl_options'][$cacheConfig['default_ttl']] ?? $cacheConfig['default_ttl'] . '秒'; ?></strong></p>
                        <form method="POST">
                            <input type="hidden" name="password" value="<?php echo htmlspecialchars($_POST['password']); ?>">
                            <div class="row">
                                <div class="col-md-8">
                                    <select name="ttl" class="form-select" required>
                                        <?php foreach ($cacheConfig['ttl_options'] as $ttl => $label): ?>
                                            <option value="<?php echo $ttl; ?>" <?php echo $ttl == $cacheConfig['default_ttl'] ? 'selected' : ''; ?>>
                                                <?php echo $label; ?> (<?php echo $ttl; ?>秒)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="update_ttl" class="btn btn-primary">有効期限を更新</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- キャッシュ分析 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">キャッシュ分析結果</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($analysisError): ?>
                            <div class="alert alert-warning">
                                <strong>分析エラー:</strong> <?php echo htmlspecialchars($analysisError); ?>
                                <br><small class="text-muted">
                                    検索されたパス: cache/search, ../cache/search, ./cache/search, cache/search_results
                                </small>
                            </div>
                        <?php elseif ($cacheAnalysis): ?>
                            <p class="mb-3">
                                <strong>総ファイル数:</strong> <?php echo $cacheAnalysis['total_files']; ?>件<br>
                                <strong>分析サンプル:</strong> <?php echo $cacheAnalysis['sample_size']; ?>件<br>
                                <small class="text-muted">
                                    デバッグ情報: キャッシュファイルの構造をログに記録しています。
                                    すべて「不明」の場合は、キャッシュファイルの構造が想定と異なる可能性があります。
                                </small>
                            </p>
                            
                            <div class="row">
                                <?php 
                                $typeLabels = [
                                    'prefecture' => '都道府県検索',
                                    'completion_year' => '完成年検索',
                                    'architect_slug' => '建築家検索',
                                    'building_slug' => '建築物詳細表示',
                                    'free_text' => 'フリーワード検索',
                                    'location' => '位置情報検索',
                                    'mixed' => '複合検索',
                                    'unknown' => '不明'
                                ];
                                
                                $hasResults = false;
                                foreach ($cacheAnalysis['results'] as $type => $data): 
                                    if ($data['count'] > 0):
                                        $hasResults = true;
                                ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo $typeLabels[$type]; ?></span>
                                            <span>
                                                <strong><?php echo $data['count']; ?>件</strong>
                                                (<?php echo $data['percentage']; ?>%)
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $data['percentage']; ?>%"
                                                 aria-valuenow="<?php echo $data['percentage']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            推定総数: <?php echo $data['estimated_total']; ?>件
                                        </small>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                
                                if (!$hasResults):
                                ?>
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            分析可能なキャッシュファイルが見つかりませんでした。
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                キャッシュ分析を実行中...
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- キャッシュ管理 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">キャッシュ管理</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>古いキャッシュの削除</h6>
                                <p class="small text-muted">7日以上古いキャッシュファイルを削除します。</p>
                                <form method="POST" onsubmit="return confirm('古いキャッシュを削除しますか？')">
                                    <input type="hidden" name="password" value="<?php echo htmlspecialchars($_POST['password']); ?>">
                                    <button type="submit" name="cleanup_old_cache" class="btn btn-warning btn-sm">古いキャッシュを削除</button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <h6>ファイル数制限</h6>
                                <p class="small text-muted">キャッシュファイル数を50,000件に制限します。</p>
                                <form method="POST" onsubmit="return confirm('キャッシュファイル数を制限しますか？')">
                                    <input type="hidden" name="password" value="<?php echo htmlspecialchars($_POST['password']); ?>">
                                    <button type="submit" name="limit_cache_files" class="btn btn-info btn-sm">ファイル数を制限</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- キャッシュクリア -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">キャッシュクリア</h5>
                    </div>
                    <div class="card-body">
                        <p>検索結果のキャッシュをクリアします。これにより、次回の検索時にデータベースから最新のデータが取得されます。</p>
                        <form method="POST" onsubmit="return confirm('キャッシュをクリアしますか？')">
                            <input type="hidden" name="password" value="<?php echo htmlspecialchars($_POST['password']); ?>">
                            <button type="submit" name="clear_cache" class="btn btn-danger">キャッシュをクリア</button>
                        </form>
                    </div>
                </div>
                
                <!-- 戻るリンク -->
                <div class="mt-4">
                    <a href="/" class="btn btn-secondary">サイトに戻る</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
