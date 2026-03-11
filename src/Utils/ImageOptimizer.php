<?php

/**
 * 画像最適化システム
 * WebP変換、サムネイル生成、遅延読み込みを提供
 */
class ImageOptimizer {
    
    private static $instance = null;
    private $cache;
    private $config;
    private $gdAvailable;
    
    private function __construct() {
        $this->cache = CacheManager::getInstance();
        $this->gdAvailable = extension_loaded('gd');
        $this->config = [
            'webp_quality' => 80,
            'jpeg_quality' => 85,
            'thumbnail_sizes' => [
                'small' => [150, 150],
                'medium' => [300, 300],
                'large' => [600, 600]
            ],
            'max_width' => 1920,
            'max_height' => 1080,
            'cache_duration' => 86400 // 24時間
        ];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 画像の最適化
     */
    public function optimize($sourcePath, $outputPath, $options = []) {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        if (!$this->gdAvailable) {
            error_log("GD extension not available for image optimization");
            return false;
        }
        
        $options = array_merge([
            'format' => 'webp',
            'quality' => $this->config['webp_quality'],
            'width' => null,
            'height' => null,
            'crop' => false
        ], $options);
        
        $cacheKey = $this->generateCacheKey($sourcePath, $options);
        
        // キャッシュから取得
        $cachedPath = $this->cache->get($cacheKey);
        if ($cachedPath && file_exists($cachedPath)) {
            return $cachedPath;
        }
        
        // 画像の最適化を実行
        $optimizedPath = $this->performOptimization($sourcePath, $outputPath, $options);
        
        if ($optimizedPath) {
            // キャッシュに保存
            $this->cache->set($cacheKey, $optimizedPath, $this->config['cache_duration']);
            return $optimizedPath;
        }
        
        return false;
    }
    
    /**
     * 画像の遅延読み込み用HTMLを生成
     */
    public function generateLazyLoadHtml($src, $alt = '', $attributes = []) {
        $defaultAttributes = [
            'class' => 'lazy-load',
            'data-src' => $src,
            'alt' => $alt,
            'loading' => 'lazy'
        ];
        
        $attributes = array_merge($defaultAttributes, $attributes);
        
        // プレースホルダー画像（GDが利用できない場合はシンプルなプレースホルダー）
        $placeholder = $this->generatePlaceholder($attributes['width'] ?? 300, $attributes['height'] ?? 200);
        
        $html = '<img ';
        foreach ($attributes as $key => $value) {
            $html .= htmlspecialchars($key) . '="' . htmlspecialchars($value) . '" ';
        }
        $html .= 'src="' . htmlspecialchars($placeholder) . '"';
        $html .= '>';
        
        return $html;
    }
    
    /**
     * 画像の最適化を実行
     */
    private function performOptimization($sourcePath, $outputPath, $options) {
        if (!$this->gdAvailable) {
            return false;
        }
        
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $sourceType = $imageInfo[2];
        
        // 元画像の読み込み
        $sourceImage = $this->loadImage($sourcePath, $sourceType);
        if (!$sourceImage) {
            return false;
        }
        
        // サイズの計算
        $targetWidth = $options['width'] ?: $sourceWidth;
        $targetHeight = $options['height'] ?: $sourceHeight;
        
        // リサイズ
        $newImage = $this->resizeImage($sourceImage, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight);
        
        if (!$newImage) {
            imagedestroy($sourceImage);
            return false;
        }
        
        // 出力ディレクトリの作成
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // 画像の保存
        $success = $this->saveImage($newImage, $outputPath, $options);
        
        // メモリの解放
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        return $success ? $outputPath : false;
    }
    
    /**
     * 画像を読み込み
     */
    private function loadImage($path, $type) {
        if (!$this->gdAvailable) {
            return false;
        }
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($path);
            default:
                return false;
        }
    }
    
    /**
     * 画像を保存
     */
    private function saveImage($image, $path, $options) {
        if (!$this->gdAvailable) {
            return false;
        }
        
        $format = $options['format'] ?? 'webp';
        $quality = $options['quality'] ?? 80;
        
        switch ($format) {
            case 'webp':
                return imagewebp($image, $path, $quality);
            case 'jpeg':
            case 'jpg':
                return imagejpeg($image, $path, $quality);
            case 'png':
                return imagepng($image, $path, 9);
            default:
                return false;
        }
    }
    
    /**
     * 画像をリサイズ
     */
    private function resizeImage($sourceImage, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight) {
        if (!$this->gdAvailable) {
            return false;
        }
        
        // アスペクト比を維持
        $ratio = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $newWidth = (int)($sourceWidth * $ratio);
        $newHeight = (int)($sourceHeight * $ratio);
        
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // PNGの透明度を保持
        if (function_exists('imagealphablending')) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
        
        return $newImage;
    }
    
    /**
     * プレースホルダー画像を生成
     */
    private function generatePlaceholder($width, $height) {
        $cacheKey = "placeholder_{$width}x{$height}";
        $cachedPath = $this->cache->get($cacheKey);
        
        if ($cachedPath && file_exists($cachedPath)) {
            return $cachedPath;
        }
        
        // GDが利用できない場合はシンプルなプレースホルダーを返す
        if (!$this->gdAvailable) {
            return $this->getSimplePlaceholder($width, $height);
        }
        
        $placeholderPath = $this->getPlaceholderPath($width, $height);
        
        // プレースホルダー画像の生成
        $image = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, 240, 240, 240);
        $textColor = imagecolorallocate($image, 150, 150, 150);
        
        imagefill($image, 0, 0, $bgColor);
        
        // テキストの描画
        $text = "{$width} × {$height}";
        $fontSize = min($width, $height) / 10;
        $fontSize = max(12, min($fontSize, 24));
        
        $font = $this->getDefaultFont();
        if ($font) {
            $textBox = imagettfbbox($fontSize, 0, $font, $text);
            $textWidth = $textBox[4] - $textBox[0];
            $textHeight = $textBox[1] - $textBox[5];
            
            $x = ($width - $textWidth) / 2;
            $y = ($height + $textHeight) / 2;
            
            imagettftext($image, $fontSize, 0, $x, $y, $textColor, $font, $text);
        }
        
        // 画像の保存
        $outputDir = dirname($placeholderPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        imagepng($image, $placeholderPath);
        imagedestroy($image);
        
        // キャッシュに保存
        $this->cache->set($cacheKey, $placeholderPath, $this->config['cache_duration']);
        
        return $placeholderPath;
    }
    
    /**
     * シンプルなプレースホルダーを取得（GDが利用できない場合）
     */
    private function getSimplePlaceholder($width, $height) {
        // データURI形式のシンプルなプレースホルダー
        $svg = '<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#f0f0f0"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999" font-family="Arial, sans-serif" font-size="14">' . $width . ' × ' . $height . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    
    /**
     * デフォルトフォントのパスを取得
     */
    private function getDefaultFont() {
        // システムフォントのパスを返す
        $fonts = [
            'C:\Windows\Fonts\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Arial.ttf'
        ];
        
        foreach ($fonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }
        
        return null;
    }
    
    /**
     * キャッシュキーを生成
     */
    private function generateCacheKey($sourcePath, $options) {
        $key = 'image_' . md5($sourcePath . serialize($options));
        return $key;
    }
    
    /**
     * プレースホルダーファイルのパスを取得
     */
    private function getPlaceholderPath($width, $height) {
        return __DIR__ . '/../../cache/placeholders/' . $width . 'x' . $height . '.png';
    }
    
    /**
     * 画像の情報を取得
     */
    public function getImageInfo($path) {
        if (!file_exists($path)) {
            return false;
        }
        
        $imageInfo = getimagesize($path);
        if (!$imageInfo) {
            return false;
        }
        
        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => $imageInfo[2],
            'mime' => $imageInfo['mime'],
            'size' => filesize($path),
            'format' => $this->getImageFormat($imageInfo[2])
        ];
    }
    
    /**
     * 画像フォーマットを取得
     */
    private function getImageFormat($type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return 'jpeg';
            case IMAGETYPE_PNG:
                return 'png';
            case IMAGETYPE_GIF:
                return 'gif';
            case IMAGETYPE_WEBP:
                return 'webp';
            default:
                return 'unknown';
        }
    }
    
    /**
     * GD拡張機能が利用可能かチェック
     */
    public function isGdAvailable() {
        return $this->gdAvailable;
    }
}
