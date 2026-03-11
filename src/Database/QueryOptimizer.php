<?php

/**
 * クエリ最適化クラス
 * データベースクエリのパフォーマンスを向上させる
 */
class QueryOptimizer {
    
    private $cache = [];
    private $queryStats = [];
    
    /**
     * クエリを最適化
     */
    public function optimize($sql, $params = []) {
        $cacheKey = md5($sql . serialize($params));
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $optimizedSql = $this->applyOptimizations($sql);
        $this->cache[$cacheKey] = $optimizedSql;
        
        return $optimizedSql;
    }
    
    /**
     * 最適化を適用
     */
    private function applyOptimizations($sql) {
        // インデックスヒントの追加
        $sql = $this->addIndexHints($sql);
        
        // 不要なJOINの削除
        $sql = $this->removeUnnecessaryJoins($sql);
        
        // サブクエリの最適化
        $sql = $this->optimizeSubqueries($sql);
        
        // ORDER BYの最適化
        $sql = $this->optimizeOrderBy($sql);
        
        return $sql;
    }
    
    /**
     * インデックスヒントを追加
     */
    private function addIndexHints($sql) {
        // 建築物テーブルのインデックスヒント
        if (strpos($sql, 'buildings_table_3') !== false) {
            $sql = str_replace(
                'FROM buildings_table_3',
                'FROM buildings_table_3 USE INDEX (idx_title, idx_location, idx_completion_years)',
                $sql
            );
        }
        
        // 建築家テーブルのインデックスヒント
        if (strpos($sql, 'individual_architects_3') !== false) {
            $sql = str_replace(
                'FROM individual_architects_3',
                'FROM individual_architects_3 USE INDEX (idx_slug, idx_name)',
                $sql
            );
        }
        
        return $sql;
    }
    
    /**
     * 不要なJOINを削除
     */
    private function removeUnnecessaryJoins($sql) {
        // 建築家情報が不要な場合はJOINを削除
        if (strpos($sql, 'architectJa') === false && 
            strpos($sql, 'architectEn') === false &&
            strpos($sql, 'ia.') === false) {
            
            $sql = preg_replace('/LEFT JOIN building_architects[^;]*/', '', $sql);
            $sql = preg_replace('/LEFT JOIN architect_compositions[^;]*/', '', $sql);
            $sql = preg_replace('/LEFT JOIN individual_architects[^;]*/', '', $sql);
        }
        
        return $sql;
    }
    
    /**
     * サブクエリを最適化
     */
    private function optimizeSubqueries($sql) {
        // EXISTS句の最適化
        $sql = preg_replace_callback(
            '/EXISTS\s*\(\s*SELECT\s+1\s+FROM\s+([^)]+)\)/i',
            function($matches) {
                return "EXISTS (SELECT 1 FROM {$matches[1]} LIMIT 1)";
            },
            $sql
        );
        
        return $sql;
    }
    
    /**
     * ORDER BYを最適化
     */
    private function optimizeOrderBy($sql) {
        // 複合インデックスを活用したORDER BYの最適化
        if (strpos($sql, 'ORDER BY b.has_photo DESC, b.building_id DESC') !== false) {
            // インデックスを活用できるように調整
            $sql = str_replace(
                'ORDER BY b.has_photo DESC, b.building_id DESC',
                'ORDER BY b.has_photo DESC, b.building_id DESC',
                $sql
            );
        }
        
        return $sql;
    }
    
    /**
     * クエリ統計を記録
     */
    public function recordQueryStats($sql, $executionTime, $rowCount) {
        $queryHash = md5($sql);
        
        if (!isset($this->queryStats[$queryHash])) {
            $this->queryStats[$queryHash] = [
                'sql' => $sql,
                'execution_times' => [],
                'row_counts' => [],
                'total_executions' => 0
            ];
        }
        
        $this->queryStats[$queryHash]['execution_times'][] = $executionTime;
        $this->queryStats[$queryHash]['row_counts'][] = $rowCount;
        $this->queryStats[$queryHash]['total_executions']++;
    }
    
    /**
     * クエリ統計を取得
     */
    public function getQueryStats() {
        $stats = [];
        
        foreach ($this->queryStats as $hash => $data) {
            $avgExecutionTime = array_sum($data['execution_times']) / count($data['execution_times']);
            $avgRowCount = array_sum($data['row_counts']) / count($data['row_counts']);
            
            $stats[] = [
                'sql' => $data['sql'],
                'total_executions' => $data['total_executions'],
                'avg_execution_time' => $avgExecutionTime,
                'avg_row_count' => $avgRowCount,
                'max_execution_time' => max($data['execution_times']),
                'min_execution_time' => min($data['execution_times'])
            ];
        }
        
        // 実行時間でソート
        usort($stats, function($a, $b) {
            return $b['avg_execution_time'] <=> $a['avg_execution_time'];
        });
        
        return $stats;
    }
    
    /**
     * スロークエリを検出
     */
    public function detectSlowQueries($threshold = 1.0) {
        $slowQueries = [];
        
        foreach ($this->queryStats as $hash => $data) {
            $avgExecutionTime = array_sum($data['execution_times']) / count($data['execution_times']);
            
            if ($avgExecutionTime > $threshold) {
                $slowQueries[] = [
                    'sql' => $data['sql'],
                    'avg_execution_time' => $avgExecutionTime,
                    'total_executions' => $data['total_executions']
                ];
            }
        }
        
        return $slowQueries;
    }
    
    /**
     * クエリキャッシュをクリア
     */
    public function clearCache() {
        $this->cache = [];
    }
    
    /**
     * 統計をクリア
     */
    public function clearStats() {
        $this->queryStats = [];
    }
    
    /**
     * 推奨インデックスを生成
     */
    public function generateIndexRecommendations() {
        $recommendations = [];
        
        // 建築物テーブルの推奨インデックス
        $recommendations[] = [
            'table' => 'buildings_table_3',
            'index' => 'idx_title_location',
            'columns' => ['title', 'location'],
            'type' => 'INDEX',
            'reason' => 'タイトルと場所での検索を高速化'
        ];
        
        $recommendations[] = [
            'table' => 'buildings_table_3',
            'index' => 'idx_completion_years_photo',
            'columns' => ['completionYears', 'has_photo'],
            'type' => 'INDEX',
            'reason' => '完成年と写真フィルターでの検索を高速化'
        ];
        
        $recommendations[] = [
            'table' => 'buildings_table_3',
            'index' => 'idx_location_coords',
            'columns' => ['lat', 'lng'],
            'type' => 'INDEX',
            'reason' => '位置情報検索を高速化'
        ];
        
        // 建築家テーブルの推奨インデックス
        $recommendations[] = [
            'table' => 'individual_architects_3',
            'index' => 'idx_name_slug',
            'columns' => ['name_ja', 'name_en', 'slug'],
            'type' => 'INDEX',
            'reason' => '建築家名とスラッグでの検索を高速化'
        ];
        
        // 関連テーブルの推奨インデックス
        $recommendations[] = [
            'table' => 'building_architects',
            'index' => 'idx_building_architect',
            'columns' => ['building_id', 'architect_id'],
            'type' => 'INDEX',
            'reason' => '建築物と建築家の関連検索を高速化'
        ];
        
        return $recommendations;
    }
    
    /**
     * クエリの実行計画を分析
     */
    public function analyzeQueryPlan($sql, $db) {
        try {
            $explainSql = "EXPLAIN " . $sql;
            $stmt = $db->prepare($explainSql);
            $stmt->execute();
            $plan = $stmt->fetchAll();
            
            $analysis = [
                'sql' => $sql,
                'plan' => $plan,
                'recommendations' => []
            ];
            
            // 実行計画を分析して推奨事項を生成
            foreach ($plan as $row) {
                if ($row['type'] === 'ALL') {
                    $analysis['recommendations'][] = "テーブルスキャンが発生しています。インデックスの追加を検討してください。";
                }
                
                if ($row['Extra'] && strpos($row['Extra'], 'Using filesort') !== false) {
                    $analysis['recommendations'][] = "ファイルソートが発生しています。ORDER BY句の最適化を検討してください。";
                }
                
                if ($row['Extra'] && strpos($row['Extra'], 'Using temporary') !== false) {
                    $analysis['recommendations'][] = "一時テーブルが使用されています。クエリの最適化を検討してください。";
                }
            }
            
            return $analysis;
            
        } catch (Exception $e) {
            error_log("Query plan analysis error: " . $e->getMessage());
            return null;
        }
    }
}
?>