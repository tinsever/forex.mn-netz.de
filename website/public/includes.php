<?php
/**
 * Common includes file
 * Loads configuration and services for standalone scripts
 */

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Load API service
require_once __DIR__ . '/../src/Services/CurrencyApi.php';

// Initialize API service
$api = new CurrencyApi($config);
