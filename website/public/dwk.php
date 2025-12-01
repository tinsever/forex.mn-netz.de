<?php
/**
 * Currency Index Calculator
 * Calculates relative currency indices based on a base currency
 */

require_once __DIR__ . '/includes.php';

/**
 * Calculate currency index relative to a base currency
 * 
 * @param CurrencyApi $api API service instance
 * @param string $baseCurrencyCode Base currency code (e.g., 'USD')
 * @return array Index values keyed by currency code
 * @throws Exception If base currency not found or rate is zero
 */
function calculateCurrencyIndex(CurrencyApi $api, string $baseCurrencyCode): array
{
    $currencies = $api->listCurrencies();
    
    $index = [];
    $baseRate = null;
    $baseCurrencyInfo = null;

    // Find base currency rate
    foreach ($currencies as $currency) {
        if ($currency['short'] === $baseCurrencyCode) {
            $baseRate = $currency['exchange_rate'];
            $baseCurrencyInfo = $currency;
            $index[$currency['short']] = 100.00;
            break;
        }
    }

    if ($baseCurrencyInfo === null) {
        throw new Exception("Basiswährung '$baseCurrencyCode' nicht gefunden.");
    }
    
    if ($baseRate == 0) {
        throw new Exception("Wechselkurs der Basiswährung ist Null.");
    }

    $baseForex = $baseCurrencyInfo['forex'];
    $warnings = [];

    // Calculate index for each currency
    foreach ($currencies as $currency) {
        if ($currency['short'] !== $baseCurrencyCode) {
            // Warn if currencies have different forex bases
            if ($currency['forex'] !== $baseForex) {
                $warnings[] = "'{$currency['short']}' nutzt andere Basis ({$currency['forex']}) als '$baseCurrencyCode' ($baseForex)";
            }
            $index[$currency['short']] = round(($currency['exchange_rate'] / $baseRate) * 100, 2);
        }
    }

    if (!empty($warnings)) {
        $index['_warnings'] = $warnings;
    }

    return $index;
}

// Main execution
$baseCurrencyCode = $_GET['base'] ?? 'AVE';
$outputFormat = $_GET['format'] ?? 'html';

try {
    $currencyIndex = calculateCurrencyIndex($api, $baseCurrencyCode);
    
    if ($outputFormat === 'json') {
        header('Content-Type: application/json');
        echo json_encode($currencyIndex, JSON_PRETTY_PRINT);
    } else {
        // HTML output
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Währungsindex - <?= htmlspecialchars($baseCurrencyCode) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#1a365d', dark: '#0f2440' },
                        background: { DEFAULT: '#f7fafc', alt: '#edf2f7' },
                        text: '#2d3748',
                        muted: '#718096',
                        border: '#e2e8f0',
                    },
                    fontFamily: {
                        heading: ['"DM Serif Display"', 'serif'],
                        body: ['"Source Sans 3"', 'sans-serif'],
                    },
                    maxWidth: { 'default': '1200px' },
                    boxShadow: { 'card': '0 1px 3px rgba(0,0,0,0.08)' }
                }
            }
        }
    </script>
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-background text-text font-body p-8">
    <div class="max-w-default mx-auto">
        <h1 class="font-heading text-3xl mb-6">Währungsindex relativ zu <?= htmlspecialchars($baseCurrencyCode) ?></h1>
        
        <?php if (isset($currencyIndex['_warnings'])): ?>
        <div class="bg-accent/10 border border-accent text-accent-dark p-4 rounded mb-6">
            <strong>Hinweis:</strong> Einige Währungen haben unterschiedliche Basiswährungen.
            <ul class="mt-2 text-sm">
                <?php foreach ($currencyIndex['_warnings'] as $warning): ?>
                <li><?= htmlspecialchars($warning) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php unset($currencyIndex['_warnings']); ?>
        <?php endif; ?>
        
        <div class="bg-white border border-border rounded-lg shadow-card overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-background-alt border-b border-border">
                        <th class="text-left p-4 text-sm font-semibold text-muted">Währung</th>
                        <th class="text-right p-4 text-sm font-semibold text-muted">Index</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currencyIndex as $code => $value): ?>
                    <tr class="border-b border-border hover:bg-background-alt">
                        <td class="p-4 font-mono font-semibold <?= $code === $baseCurrencyCode ? 'text-primary' : '' ?>">
                            <?= htmlspecialchars($code) ?>
                        </td>
                        <td class="p-4 text-right font-mono">
                            <?= number_format($value, 2, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <p class="mt-4 text-sm text-muted">
            <a href="?base=<?= urlencode($baseCurrencyCode) ?>&format=json" class="text-primary hover:underline">JSON anzeigen</a>
        </p>
    </div>
</body>
</html>
        <?php
    }
} catch (Exception $e) {
    if ($outputFormat === 'json') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        http_response_code(500);
        echo "<h1>Fehler</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
