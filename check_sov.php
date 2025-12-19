<?php
$config = require 'api/config/app.php';
$db = $config['database'];
try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']}", $db['user'], $db['pass']);
    $stmt = $pdo->prepare('SELECT * FROM currencies WHERE code = ?');
    $stmt->execute(['SOV']);
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

