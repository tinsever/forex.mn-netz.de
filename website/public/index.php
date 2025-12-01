<?php
/**
 * mnFOREX
 * 
 * Main entry point for the application.
 * Handles routing, configuration loading, and page rendering.
 * 
 * @author Tin Sever
 * @license MIT
 */

// ============================================================================
// BOOTSTRAP
// ============================================================================

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Load API service
require_once __DIR__ . '/../src/Services/CurrencyApi.php';

// Initialize API service
$api = new CurrencyApi($config);

// ============================================================================
// SESSION & ERROR HANDLING
// ============================================================================

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_errors.log');

// ============================================================================
// SECURITY HEADERS
// ============================================================================

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');

// CORS (für API-Aufrufe)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With');

// Routing
$page = $_GET['page'] ?? 'home';
$action = $_GET['action'] ?? null;

// Map pages to their file paths
$pageFile = match (true) {
    $page === 'currencies' && $action === 'new' => 'currency/create.php',
    $page === 'currencies' && $action === 'edit' => 'currency/edit.php',
    default => "$page.php"
};

// Handle actions (store, update, delete)
if ($action && in_array($action, ['store', 'update', 'delete'])) {
    $actionFile = __DIR__ . "/../src/actions/{$page}/{$action}.php";
    if (file_exists($actionFile)) {
        require $actionFile;
        exit;
    }
}

// Verify page file exists
$fullPath = __DIR__ . "/../src/pages/$pageFile";
if (!file_exists($fullPath)) {
    http_response_code(404);
    die("Seite nicht gefunden.");
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['ui']['site_name']) ?></title>
    
    <!-- Tailwind CSS v3 (stable) + Custom Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#1a365d', dark: '#0f2440' },
                        secondary: '#276749',
                        accent: { DEFAULT: '#d69e2e', dark: '#b7791f' },
                        background: { DEFAULT: '#f7fafc', alt: '#edf2f7' },
                        text: '#2d3748',
                        muted: '#718096',
                        border: '#e2e8f0',
                        positive: '#38a169',
                        negative: '#c53030',
                    },
                    fontFamily: {
                        heading: ['"DM Serif Display"', 'serif'],
                        body: ['"Source Sans 3"', 'sans-serif'],
                    },
                    maxWidth: {
                        'default': '1200px',
                    },
                    boxShadow: {
                        'card': '0 1px 3px rgba(0,0,0,0.08)',
                    }
                }
            }
        }
    </script>
    <link href="css/styles.css" rel="stylesheet">
    
    <!-- App Configuration for JavaScript -->
    <script>
        window.FOREX_CONFIG = {
            apiBase: <?= json_encode($api->getBaseUrl()) ?>,
            defaults: <?= json_encode($config['defaults']) ?>
        };
    </script>
</head>
<body class="bg-background text-text font-body">
    <!-- Header -->
    <header class="bg-white border-b border-border">
        <div class="border-b border-border">
            <div class="flex items-center justify-between max-w-default mx-auto py-4 px-4 md:px-0">
                <div class="flex items-center gap-3">
                    <div class="bg-primary p-2 rounded">
                        <img src="img/logo.svg" alt="Logo" class="h-6 w-auto filter-white">
                    </div>
                    <span class="font-heading text-xl text-primary font-bold tracking-tight"><?= htmlspecialchars($config['ui']['site_name']) ?></span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="https://netz.mn-netz.de" 
                       class="text-sm text-muted hover:text-primary transition-colors">
                        Mein Login
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="max-w-default mx-auto px-4 md:px-0">
            <div class="flex items-center gap-6 py-3">
                <?php $currentPage = $_GET['page'] ?? 'home'; ?>
                <a href="?page=home" 
                   class="text-sm font-medium pb-3 -mb-3 border-b-2 transition-colors <?= $currentPage === 'home' 
                       ? 'text-primary border-primary' 
                       : 'text-muted border-transparent hover:text-text hover:border-border-dark' ?>">
                    Startseite
                </a>
                <a href="?page=currencies" 
                   class="text-sm font-medium pb-3 -mb-3 border-b-2 transition-colors <?= $currentPage === 'currencies' 
                       ? 'text-primary border-primary' 
                       : 'text-muted border-transparent hover:text-text hover:border-border-dark' ?>">
                    Währungen
                </a>
            </div>
        </nav>
    </header>

    <!-- Calculator Section -->
    <section class="bg-primary-dark text-white py-6">
        <div class="max-w-default mx-auto px-4 md:px-0">
            <div class="max-w-3xl mx-auto">
                <h2 class="font-heading text-xl mb-4 text-center">Währungsrechner</h2>
                
                <div class="bg-white/10 rounded-lg p-5">
                    <!-- Calculator Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-11 gap-3 items-center">
                        <!-- From Amount -->
                        <div class="md:col-span-3">
                            <input type="number" 
                                   id="fromAmount" 
                                   value="1" 
                                   min="0" 
                                   step="0.01"
                                   class="w-full bg-white text-text rounded-lg px-4 py-3 font-mono text-xl focus:outline-none focus:ring-2 focus:ring-accent">
                        </div>
                        
                        <!-- From Currency -->
                        <div class="md:col-span-3">
                            <select id="calculatorFromCurrency"
                                    class="w-full bg-white text-text rounded-lg px-3 py-3 font-semibold cursor-pointer focus:outline-none focus:ring-2 focus:ring-accent">
                                <option value="">Währung wählen...</option>
                            </select>
                        </div>

                        <!-- Swap Button -->
                        <div class="md:col-span-1 flex justify-center">
                            <button type="button" 
                                    id="swapCurrencies"
                                    class="w-11 h-11 rounded-full bg-accent hover:bg-accent-dark text-primary-dark flex items-center justify-center transition-all hover:scale-110"
                                    title="Währungen tauschen">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                            </button>
                        </div>

                        <!-- To Currency -->
                        <div class="md:col-span-3">
                            <select id="calculatorToCurrency"
                                    class="w-full bg-white text-text rounded-lg px-3 py-3 font-semibold cursor-pointer focus:outline-none focus:ring-2 focus:ring-accent">
                                <option value="">Währung wählen...</option>
                            </select>
                        </div>
                        
                        <!-- To Amount (Result) - Hidden on mobile, shown below -->
                        <div class="md:col-span-1 hidden md:block text-center text-2xl text-white/50">=</div>
                    </div>
                    
                    <!-- Result Row -->
                    <div class="mt-4 bg-white/5 rounded-lg p-4">
                        <div class="text-sm text-white/60 mb-1">Ergebnis</div>
                        <div class="flex items-baseline gap-2">
                            <input type="text" 
                                   id="toAmount" 
                                   readonly
                                   placeholder="0,00"
                                   class="bg-transparent text-white text-3xl font-mono font-bold w-full focus:outline-none">
                            <span id="toAmountCurrency" class="text-white/70 text-lg font-semibold"></span>
                        </div>
                        <div id="exchangeInfo" class="mt-2 text-sm text-white/50 hidden"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="py-8">
        <?php include $fullPath; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-primary-dark text-white/60 py-8 mt-auto">
        <div class="max-w-default mx-auto px-4 md:px-0 text-center text-sm">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($config['ui']['site_name']) ?>. Alle Angaben fiktiv und ohne Gewähr.</p>
        </div>
    </footer>

    <!-- Application JavaScript -->
    <script src="js/app.js"></script>
</body>
</html>
