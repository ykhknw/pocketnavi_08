#!/usr/local/php/8.3/bin/php
<?php
$logFile = __DIR__ . '/../logs/cron_test.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[$timestamp] CRON TEST OK\n", FILE_APPEND);
echo "[$timestamp] CRON TEST OK\n";
?>