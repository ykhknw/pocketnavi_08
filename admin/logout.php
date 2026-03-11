<?php
/**
 * 管理画面ログアウト
 */

session_start();

// セッションを破棄
session_destroy();

// ログインページにリダイレクト
header('Location: login.php');
exit;
