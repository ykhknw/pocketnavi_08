<?php

/**
 * ビューシステム
 * テンプレートエンジンの簡易版
 */
class View {
    
    private $templatePath;
    private $data = [];
    
    public function __construct() {
        $this->templatePath = __DIR__ . '/../../src/Views/';
    }
    
    /**
     * テンプレートのレンダリング
     */
    public function render($template, $data = []) {
        $this->data = array_merge($this->data, $data);
        $templateFile = $this->templatePath . $template . '.php';
        
        if (!file_exists($templateFile)) {
            throw new Exception("Template not found: {$template}");
        }
        
        extract($this->data);
        ob_start();
        include $templateFile;
        return ob_get_clean();
    }
    
    /**
     * データの設定
     */
    public function set($key, $value) {
        $this->data[$key] = $value;
    }
    
    /**
     * データの取得
     */
    public function get($key, $default = null) {
        return $this->data[$key] ?? $default;
    }
    
    /**
     * データの取得
     */
    public function getData() {
        return $this->data;
    }
}
