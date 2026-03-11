<?php

/**
 * Robots.txt検証ユーティリティ
 */
class RobotsTxtValidator {
    
    /**
     * robots.txtの構文を検証
     */
    public static function validateRobotsTxt($robotsContent) {
        $errors = [];
        $warnings = [];
        
        $lines = explode("\n", $robotsContent);
        $lineNumber = 0;
        
        foreach ($lines as $line) {
            $lineNumber++;
            $line = trim($line);
            
            // 空行やコメント行はスキップ
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // 基本的な構文チェック
            if (!preg_match('/^(User-agent|Allow|Disallow|Crawl-delay|Sitemap):\s*(.*)$/i', $line, $matches)) {
                $errors[] = "Line {$lineNumber}: Invalid syntax - '{$line}'";
                continue;
            }
            
            $directive = strtolower(trim($matches[1]));
            $value = trim($matches[2]);
            
            // 各ディレクティブの検証
            switch ($directive) {
                case 'user-agent':
                    if (empty($value)) {
                        $errors[] = "Line {$lineNumber}: User-agent cannot be empty";
                    }
                    break;
                    
                case 'allow':
                case 'disallow':
                    // パスの構文チェック
                    if (!empty($value) && !preg_match('/^\/.*$/', $value) && $value !== '*') {
                        $warnings[] = "Line {$lineNumber}: Path should start with '/' - '{$value}'";
                    }
                    break;
                    
                case 'crawl-delay':
                    if (!is_numeric($value) || $value < 0) {
                        $errors[] = "Line {$lineNumber}: Crawl-delay must be a positive number - '{$value}'";
                    }
                    break;
                    
                case 'sitemap':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[] = "Line {$lineNumber}: Invalid URL format - '{$value}'";
                    }
                    break;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * robots.txtの内容を分析
     */
    public static function analyzeRobotsTxt($robotsContent) {
        $analysis = [
            'user_agents' => [],
            'disallow_rules' => [],
            'allow_rules' => [],
            'crawl_delays' => [],
            'sitemaps' => [],
            'total_rules' => 0
        ];
        
        $lines = explode("\n", $robotsContent);
        $currentUserAgent = '*';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            if (preg_match('/^(User-agent|Allow|Disallow|Crawl-delay|Sitemap):\s*(.*)$/i', $line, $matches)) {
                $directive = strtolower(trim($matches[1]));
                $value = trim($matches[2]);
                
                switch ($directive) {
                    case 'user-agent':
                        $currentUserAgent = $value;
                        if (!in_array($value, $analysis['user_agents'])) {
                            $analysis['user_agents'][] = $value;
                        }
                        break;
                        
                    case 'disallow':
                        $analysis['disallow_rules'][] = [
                            'user_agent' => $currentUserAgent,
                            'path' => $value
                        ];
                        $analysis['total_rules']++;
                        break;
                        
                    case 'allow':
                        $analysis['allow_rules'][] = [
                            'user_agent' => $currentUserAgent,
                            'path' => $value
                        ];
                        $analysis['total_rules']++;
                        break;
                        
                    case 'crawl-delay':
                        $analysis['crawl_delays'][] = [
                            'user_agent' => $currentUserAgent,
                            'delay' => $value
                        ];
                        break;
                        
                    case 'sitemap':
                        $analysis['sitemaps'][] = $value;
                        break;
                }
            }
        }
        
        return $analysis;
    }
    
    /**
     * robots.txtの推奨事項をチェック
     */
    public static function checkRecommendations($robotsContent) {
        $recommendations = [];
        
        // サイトマップの存在チェック
        if (strpos($robotsContent, 'Sitemap:') === false) {
            $recommendations[] = "Consider adding a Sitemap directive to help search engines find your sitemap";
        }
        
        // クロール遅延のチェック
        if (strpos($robotsContent, 'Crawl-delay:') === false) {
            $recommendations[] = "Consider adding a Crawl-delay directive to prevent server overload";
        }
        
        // セキュリティ関連のチェック
        $securityPatterns = [
            '/admin/' => 'Admin directories should be disallowed',
            '/config/' => 'Config directories should be disallowed',
            '/logs/' => 'Log directories should be disallowed',
            '/.htaccess' => 'System files should be disallowed'
        ];
        
        foreach ($securityPatterns as $pattern => $message) {
            if (strpos($robotsContent, "Disallow: {$pattern}") === false) {
                $recommendations[] = $message;
            }
        }
        
        return $recommendations;
    }
}
