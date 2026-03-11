<?php

/**
 * セキュリティログ監視システム
 */
class LogMonitor {
    
    private static $instance = null;
    private $logFile;
    private $config;
    
    private function __construct() {
        $this->logFile = 'logs/security.log';
        $this->config = [
            'monitoring' => [
                'enabled' => true,
                'real_time' => true,
                'alert_threshold' => 10, // 10分間に10回以上の異常
                'check_interval' => 300  // 5分間隔でチェック
            ],
            'patterns' => [
                'brute_force' => '/LOGIN_FAILED/',
                'csrf_attack' => '/CSRF_TOKEN_INVALID/',
                'malicious_input' => '/MALICIOUS_INPUT_DETECTED/',
                'rate_limit' => '/RATE_LIMIT_EXCEEDED/',
                'unauthorized_access' => '/ADMIN_ACCESS_DENIED/'
            ],
            'alerts' => [
                'email' => [
                    'enabled' => false,
                    'recipients' => ['admin@example.com']
                ],
                'webhook' => [
                    'enabled' => false,
                    'url' => 'https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK'
                ]
            ]
        ];
        
        $this->ensureLogDirectory();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * ログディレクトリの確保
     */
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * セキュリティイベントの分析
     */
    public function analyzeSecurityEvents($timeWindow = 3600) {
        if (!$this->config['monitoring']['enabled']) {
            return [];
        }
        
        $events = $this->getRecentEvents($timeWindow);
        $analysis = [
            'total_events' => count($events),
            'event_types' => [],
            'suspicious_ips' => [],
            'attack_patterns' => [],
            'risk_level' => 'LOW'
        ];
        
        foreach ($events as $event) {
            $data = json_decode($event, true);
            if (!$data) continue;
            
            // イベントタイプの集計
            $eventType = $data['event'] ?? 'UNKNOWN';
            $analysis['event_types'][$eventType] = ($analysis['event_types'][$eventType] ?? 0) + 1;
            
            // 疑わしいIPの特定
            $ip = $data['ip'] ?? 'unknown';
            if (!isset($analysis['suspicious_ips'][$ip])) {
                $analysis['suspicious_ips'][$ip] = 0;
            }
            $analysis['suspicious_ips'][$ip]++;
            
            // 攻撃パターンの検出
            foreach ($this->config['patterns'] as $patternName => $pattern) {
                if (preg_match($pattern, $eventType)) {
                    $analysis['attack_patterns'][$patternName] = ($analysis['attack_patterns'][$patternName] ?? 0) + 1;
                }
            }
        }
        
        // リスクレベルの判定
        $analysis['risk_level'] = $this->calculateRiskLevel($analysis);
        
        return $analysis;
    }
    
    /**
     * 最近のイベントの取得
     */
    private function getRecentEvents($timeWindow) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $events = [];
        $cutoffTime = time() - $timeWindow;
        
        $handle = fopen($this->logFile, 'r');
        if (!$handle) {
            return [];
        }
        
        while (($line = fgets($handle)) !== false) {
            $data = json_decode(trim($line), true);
            if ($data && isset($data['timestamp'])) {
                $eventTime = strtotime($data['timestamp']);
                if ($eventTime >= $cutoffTime) {
                    $events[] = trim($line);
                }
            }
        }
        
        fclose($handle);
        return $events;
    }
    
    /**
     * リスクレベルの計算
     */
    private function calculateRiskLevel($analysis) {
        $riskScore = 0;
        
        // 総イベント数によるスコア
        if ($analysis['total_events'] > 100) {
            $riskScore += 3;
        } elseif ($analysis['total_events'] > 50) {
            $riskScore += 2;
        } elseif ($analysis['total_events'] > 20) {
            $riskScore += 1;
        }
        
        // 攻撃パターンによるスコア
        foreach ($analysis['attack_patterns'] as $pattern => $count) {
            if ($count > 10) {
                $riskScore += 3;
            } elseif ($count > 5) {
                $riskScore += 2;
            } elseif ($count > 2) {
                $riskScore += 1;
            }
        }
        
        // 疑わしいIPによるスコア
        foreach ($analysis['suspicious_ips'] as $ip => $count) {
            if ($count > 20) {
                $riskScore += 2;
            } elseif ($count > 10) {
                $riskScore += 1;
            }
        }
        
        if ($riskScore >= 8) {
            return 'CRITICAL';
        } elseif ($riskScore >= 5) {
            return 'HIGH';
        } elseif ($riskScore >= 3) {
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }
    
    /**
     * リアルタイム監視の開始
     */
    public function startRealTimeMonitoring() {
        if (!$this->config['monitoring']['real_time']) {
            return;
        }
        
        // バックグラウンドで監視を開始
        $this->monitorLogFile();
    }
    
    /**
     * ログファイルの監視
     */
    private function monitorLogFile() {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        $lastSize = filesize($this->logFile);
        
        while (true) {
            clearstatcache();
            $currentSize = filesize($this->logFile);
            
            if ($currentSize > $lastSize) {
                $this->processNewLogEntries($lastSize, $currentSize);
                $lastSize = $currentSize;
            }
            
            sleep(1); // 1秒間隔でチェック
        }
    }
    
    /**
     * 新しいログエントリの処理
     */
    private function processNewLogEntries($lastSize, $currentSize) {
        $handle = fopen($this->logFile, 'r');
        if (!$handle) {
            return;
        }
        
        fseek($handle, $lastSize);
        $newContent = fread($handle, $currentSize - $lastSize);
        fclose($handle);
        
        $lines = explode("\n", $newContent);
        foreach ($lines as $line) {
            if (trim($line)) {
                $this->processLogEntry(trim($line));
            }
        }
    }
    
    /**
     * ログエントリの処理
     */
    private function processLogEntry($logEntry) {
        $data = json_decode($logEntry, true);
        if (!$data) {
            return;
        }
        
        $eventType = $data['event'] ?? '';
        
        // 緊急イベントの検出
        $criticalEvents = ['LOGIN_FAILED', 'CSRF_TOKEN_INVALID', 'MALICIOUS_INPUT_DETECTED'];
        if (in_array($eventType, $criticalEvents)) {
            $this->handleCriticalEvent($data);
        }
        
        // パターンマッチング
        foreach ($this->config['patterns'] as $patternName => $pattern) {
            if (preg_match($pattern, $eventType)) {
                $this->handleAttackPattern($patternName, $data);
            }
        }
    }
    
    /**
     * 緊急イベントの処理
     */
    private function handleCriticalEvent($data) {
        // 即座にアラートを送信
        $this->sendAlert('CRITICAL', $data);
        
        // 必要に応じてIPをブロック
        $this->considerIpBlocking($data['ip'] ?? '');
    }
    
    /**
     * 攻撃パターンの処理
     */
    private function handleAttackPattern($patternName, $data) {
        // パターン別の処理
        switch ($patternName) {
            case 'brute_force':
                $this->handleBruteForceAttack($data);
                break;
            case 'csrf_attack':
                $this->handleCsrfAttack($data);
                break;
            case 'malicious_input':
                $this->handleMaliciousInput($data);
                break;
        }
    }
    
    /**
     * ブルートフォース攻撃の処理
     */
    private function handleBruteForceAttack($data) {
        $ip = $data['ip'] ?? '';
        $timeWindow = 300; // 5分
        
        // 同じIPからの失敗回数をチェック
        $recentFailures = $this->countRecentFailures($ip, $timeWindow);
        
        if ($recentFailures > 5) {
            $this->sendAlert('BRUTE_FORCE_DETECTED', $data);
            $this->blockIp($ip, 1800); // 30分間ブロック
        }
    }
    
    /**
     * 最近の失敗回数のカウント
     */
    private function countRecentFailures($ip, $timeWindow) {
        $events = $this->getRecentEvents($timeWindow);
        $count = 0;
        
        foreach ($events as $event) {
            $data = json_decode($event, true);
            if ($data && 
                ($data['ip'] ?? '') === $ip && 
                ($data['event'] ?? '') === 'LOGIN_FAILED') {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * IPブロックの実行
     */
    private function blockIp($ip, $duration) {
        // .htaccessにIPブロックルールを追加
        $htaccessFile = '.htaccess';
        $blockRule = "Deny from {$ip}\n";
        
        if (file_exists($htaccessFile)) {
            $content = file_get_contents($htaccessFile);
            if (strpos($content, $blockRule) === false) {
                file_put_contents($htaccessFile, $blockRule, FILE_APPEND);
            }
        } else {
            file_put_contents($htaccessFile, $blockRule);
        }
        
        // ブロック解除のスケジュール
        $this->scheduleIpUnblock($ip, $duration);
    }
    
    /**
     * IPブロック解除のスケジュール
     */
    private function scheduleIpUnblock($ip, $duration) {
        // 実際の実装では、cronジョブやキューシステムを使用
        // ここでは簡易的な実装
        $unblockTime = time() + $duration;
        $unblockData = [
            'ip' => $ip,
            'unblock_time' => $unblockTime
        ];
        
        file_put_contents('logs/ip_blocks.json', json_encode($unblockData) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * アラートの送信
     */
    private function sendAlert($type, $data) {
        $message = $this->formatAlertMessage($type, $data);
        
        // メールアラート
        if ($this->config['alerts']['email']['enabled']) {
            $this->sendEmailAlert($message);
        }
        
        // Webhookアラート
        if ($this->config['alerts']['webhook']['enabled']) {
            $this->sendWebhookAlert($message);
        }
        
        // ログに記録
        error_log("SECURITY_ALERT: {$message}");
    }
    
    /**
     * アラートメッセージのフォーマット
     */
    private function formatAlertMessage($type, $data) {
        $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
        $event = $data['event'] ?? 'UNKNOWN';
        $ip = $data['ip'] ?? 'unknown';
        $details = $data['details'] ?? '';
        
        return "[{$type}] {$timestamp} - {$event} from {$ip} - {$details}";
    }
    
    /**
     * メールアラートの送信
     */
    private function sendEmailAlert($message) {
        $subject = 'PocketNavi セキュリティアラート';
        $headers = 'From: security@pocketnavi.com' . "\r\n" .
                  'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        
        foreach ($this->config['alerts']['email']['recipients'] as $recipient) {
            mail($recipient, $subject, $message, $headers);
        }
    }
    
    /**
     * Webhookアラートの送信
     */
    private function sendWebhookAlert($message) {
        $payload = [
            'text' => "🚨 PocketNavi セキュリティアラート",
            'attachments' => [
                [
                    'color' => 'danger',
                    'text' => $message
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['alerts']['webhook']['url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        curl_exec($ch);
        curl_close($ch);
    }
    
    /**
     * セキュリティレポートの生成
     */
    public function generateSecurityReport($timeWindow = 86400) {
        $analysis = $this->analyzeSecurityEvents($timeWindow);
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'time_window' => $timeWindow,
            'analysis' => $analysis,
            'recommendations' => $this->generateRecommendations($analysis),
            'statistics' => $this->generateStatistics($timeWindow)
        ];
        
        return $report;
    }
    
    /**
     * 推奨事項の生成
     */
    private function generateRecommendations($analysis) {
        $recommendations = [];
        
        if ($analysis['risk_level'] === 'CRITICAL' || $analysis['risk_level'] === 'HIGH') {
            $recommendations[] = '即座にセキュリティ監査を実施してください';
            $recommendations[] = '疑わしいIPアドレスをブロックしてください';
            $recommendations[] = 'パスワードポリシーを強化してください';
        }
        
        if (isset($analysis['attack_patterns']['brute_force']) && $analysis['attack_patterns']['brute_force'] > 5) {
            $recommendations[] = 'ブルートフォース攻撃が検出されています。アカウントロックアウト機能を強化してください';
        }
        
        if (isset($analysis['attack_patterns']['csrf_attack']) && $analysis['attack_patterns']['csrf_attack'] > 3) {
            $recommendations[] = 'CSRF攻撃が検出されています。CSRFトークンの検証を強化してください';
        }
        
        if (isset($analysis['attack_patterns']['malicious_input']) && $analysis['attack_patterns']['malicious_input'] > 2) {
            $recommendations[] = '悪意のある入力が検出されています。入力値検証を強化してください';
        }
        
        return $recommendations;
    }
    
    /**
     * 統計情報の生成
     */
    private function generateStatistics($timeWindow) {
        $events = $this->getRecentEvents($timeWindow);
        
        $stats = [
            'total_events' => count($events),
            'events_per_hour' => count($events) / ($timeWindow / 3600),
            'unique_ips' => 0,
            'most_common_event' => '',
            'time_distribution' => []
        ];
        
        $eventCounts = [];
        $ipCounts = [];
        
        foreach ($events as $event) {
            $data = json_decode($event, true);
            if (!$data) continue;
            
            $eventType = $data['event'] ?? 'UNKNOWN';
            $ip = $data['ip'] ?? 'unknown';
            
            $eventCounts[$eventType] = ($eventCounts[$eventType] ?? 0) + 1;
            $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
        }
        
        $stats['unique_ips'] = count($ipCounts);
        $stats['most_common_event'] = !empty($eventCounts) ? array_keys($eventCounts, max($eventCounts))[0] : 'NONE';
        
        return $stats;
    }
    
    /**
     * 設定の取得
     */
    public function getConfig() {
        return $this->config;
    }
    
    /**
     * 設定の更新
     */
    public function setConfig($key, $value) {
        $this->config[$key] = $value;
    }
}
