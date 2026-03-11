<?php
// 環境判定とデータベース設定

// 環境を判定（HETEMLかローカルか）
function isHETEML() {
    // HETEMLの特徴的な環境変数やパスで判定
    return isset($_SERVER['HTTP_HOST']) && 
           (strpos($_SERVER['HTTP_HOST'], 'heteml.net') !== false || 
            strpos($_SERVER['HTTP_HOST'], 'heteml.com') !== false ||
            strpos($_SERVER['HTTP_HOST'], 'heteml.jp') !== false);
}

// 環境に応じたデータベース設定を取得
function getDatabaseConfig() {
    if (isHETEML()) {
        return [
            'host' => 'localhost',
            'db_name' => '_shinkenchiku_02',
            'username' => 'root', // HETEMLの実際のユーザー名に変更
            'password' => '', // HETEMLの実際のパスワードに変更
        ];
    } else {
        return [
            'host' => 'localhost',
            'db_name' => '_shinkenchiku_db',
            'username' => 'root',
            'password' => '',
        ];
    }
}
?>
