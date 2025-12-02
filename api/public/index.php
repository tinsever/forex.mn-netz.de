<?php

declare(strict_types=1);

/**
 * mnFOREX API
 * 
 * Simple router for handling API requests.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$configPath = __DIR__ . '/../config/app.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Configuration file not found. Please copy config/app.php.example to config/app.php']);
    exit;
}

$config = require $configPath;

// Setup error handling based on debug mode
$debug = $config['api']['debug'] ?? false;
error_reporting($debug ? E_ALL : 0);
ini_set('display_errors', $debug ? '1' : '0');

// Set JSON content type for all responses
header('Content-Type: application/json; charset=utf-8');

use App\Services\CurrencyConverter;
use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitMiddleware;

$response = [];
$statusCode = 200;

try {
    // Handle CORS
    $cors = new CorsMiddleware($config['cors'] ?? []);
    if (!$cors->handle()) {
        exit; // Preflight request handled
    }

    // Handle Rate Limiting
    $rateLimit = new RateLimitMiddleware($config['rate_limit'] ?? []);
    $rateLimit->handle();

    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception("Method {$_SERVER['REQUEST_METHOD']} not allowed. Only GET is supported.", 405);
    }

    // Database connection
    $db = $config['database'];
    $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Initialize converter
    $converter = new CurrencyConverter($pdo, [
        'frankfurter_url' => $config['frankfurter']['base_url'] ?? 'https://api.frankfurter.app',
        'timeout' => $config['api']['timeout'] ?? 15,
    ]);

    // Simple routing based on 'action' parameter
    $action = $_GET['action'] ?? null;

    if ($action === null) {
        throw new Exception("Missing required parameter: 'action'. Available actions: list, convert, rates, historical", 400);
    }

    switch ($action) {
        case 'list':
            // GET ?action=list
            $response = [
                'success' => true,
                'action' => 'list',
                'data' => $converter->listCurrencies(),
            ];
            break;

        case 'convert':
            // GET ?action=convert&amount={amount}&from={from}&to={to}
            $required = ['amount', 'from', 'to'];
            foreach ($required as $param) {
                if (!isset($_GET[$param])) {
                    throw new Exception("Missing required parameter: '{$param}' for action 'convert'.", 400);
                }
            }

            $amount = (float) $_GET['amount'];
            $from = strtoupper($_GET['from']);
            $to = strtoupper($_GET['to']);

            if ($amount <= 0) {
                throw new Exception("Parameter 'amount' must be a positive number.", 400);
            }

            $result = $converter->convert($amount, $from, $to);
            $response = [
                'success' => true,
                'action' => 'convert',
                'data' => [
                    'from' => $from,
                    'to' => $to,
                    'amount' => $amount,
                    'result' => $result,
                ],
            ];
            break;

        case 'rates':
            // GET ?action=rates&base={base}
            if (!isset($_GET['base'])) {
                throw new Exception("Missing required parameter: 'base' for action 'rates'.", 400);
            }

            $base = strtoupper($_GET['base']);
            $ratesData = $converter->getRates($base);

            $response = [
                'success' => true,
                'action' => 'rates',
                'data' => $ratesData,
            ];
            break;

        case 'historical':
            // GET ?action=historical&from={from}&to={to}&start={YYYY-MM-DD}&end={YYYY-MM-DD}
            $required = ['from', 'to', 'start', 'end'];
            foreach ($required as $param) {
                if (!isset($_GET[$param])) {
                    throw new Exception("Missing required parameter: '{$param}' for action 'historical'.", 400);
                }
            }

            $from = strtoupper($_GET['from']);
            $to = strtoupper($_GET['to']);
            $start = $_GET['start'];
            $end = $_GET['end'];

            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                throw new Exception("Invalid date format. Use YYYY-MM-DD for 'start' and 'end' parameters.", 400);
            }

            $historicalData = $converter->getHistoricalRates($from, $to, $start, $end);

            $response = [
                'success' => true,
                'action' => 'historical',
                'data' => [
                    'from' => $from,
                    'to' => $to,
                    'start_date' => $start,
                    'end_date' => $end,
                    'rates' => $historicalData,
                ],
            ];
            break;

        default:
            throw new Exception("Unknown action: '{$action}'. Available actions: list, convert, rates, historical", 404);
    }

} catch (PDOException $e) {
    $statusCode = 503;
    $response = [
        'success' => false,
        'error' => [
            'code' => 503,
            'message' => $debug ? 'Database error: ' . $e->getMessage() : 'Database service temporarily unavailable.',
        ],
    ];
} catch (Exception $e) {
    $code = $e->getCode();
    $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
    $response = [
        'success' => false,
        'error' => [
            'code' => $statusCode,
            'message' => $e->getMessage(),
        ],
    ];
}

// Send response
http_response_code($statusCode);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

