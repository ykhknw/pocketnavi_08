<?php

/**
 * 統一されたルーティングシステム
 * RESTful APIとMVCパターンをサポート
 */
class Router {
    
    private static $routes = [];
    private static $middlewares = [];
    private static $currentRoute = null;
    
    /**
     * ルートの登録
     */
    public static function addRoute($method, $path, $handler, $middlewares = []) {
        self::$routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares,
            'pattern' => self::convertToRegex($path)
        ];
    }
    
    /**
     * GETルートの登録
     */
    public static function get($path, $handler, $middlewares = []) {
        self::addRoute('GET', $path, $handler, $middlewares);
    }
    
    /**
     * パスを正規表現に変換
     */
    private static function convertToRegex($path) {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    /**
     * ルートの実行
     */
    public static function dispatch($method = null, $path = null) {
        $method = $method ?: $_SERVER['REQUEST_METHOD'];
        $path = $path ?: parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = strtok($path, '?');
        
        foreach (self::$routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                self::$currentRoute = $route;
                $params = array_slice($matches, 1);
                return self::executeHandler($route['handler'], $params);
            }
        }
        
        return self::handleNotFound();
    }
    
    /**
     * ハンドラーの実行
     */
    private static function executeHandler($handler, $params) {
        if (is_callable($handler)) {
            $result = call_user_func_array($handler, $params);
            if ($result !== null) {
                echo $result;
            }
            return true;
        }
        return false;
    }
    
    /**
     * 404エラーの処理
     */
    private static function handleNotFound() {
        http_response_code(404);
        echo "404 Not Found";
        return false;
    }
    
    /**
     * ルートの一覧を取得
     */
    public static function getRoutes() {
        return self::$routes;
    }
    
    /**
     * ルートのクリア
     */
    public static function clearRoutes() {
        self::$routes = [];
    }
}
