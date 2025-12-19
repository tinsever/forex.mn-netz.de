<?php
$config = require 'api/config/app.php';
$db = $config['database'];
try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']}", $db['user'], $db['pass']);
    $pdo->exec("DELETE FROM currencies WHERE code = 'GBP' AND name = 'British Pound'");
    echo "Cleaned up GBP entry.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
unlink('check_db.php');
unlink('check_sov.php');
unlink('test_conversion.php');
unlink('cleanup.php');

