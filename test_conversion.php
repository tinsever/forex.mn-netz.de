<?php
require_once 'api/vendor/autoload.php';
$config = require 'api/config/app.php';
$db = $config['database'];

use App\Services\CurrencyConverter;

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']}", $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $converter = new CurrencyConverter($pdo);
    
    echo "Converting 1 SOV to GBP:\n";
    // We need to make sure GBP is in the DB or handled by the converter
    // If GBP is not in the DB, convert() will throw an error because getCurrencyInfo() returns null.
    
    // Let's check if GBP is in the DB
    $stmt = $pdo->prepare('SELECT code FROM currencies WHERE code = ?');
    $stmt->execute(['GBP']);
    if (!$stmt->fetch()) {
        echo "GBP not found in currencies table. Adding it temporarily for test...\n";
        $pdo->exec("INSERT INTO currencies (name, country, code, symbol, real_currency, exchange_rate, exchange_direction, user_id) 
                    VALUES ('British Pound', 'UK', 'GBP', '£', 'GBP', 1.0000, 'real_to_custom', 1)");
    }

    $result = $converter->convert(1, 'SOV', 'GBP');
    echo "Result: 1 SOV = $result GBP\n";
    
    if ($result == 10.5) {
        echo "SUCCESS: Conversion is correct.\n";
    } else {
        echo "FAILURE: Expected 10.5, got $result\n";
    }

    // Clean up if we added GBP
    // $pdo->exec("DELETE FROM currencies WHERE code = 'GBP' AND name = 'British Pound'");

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

