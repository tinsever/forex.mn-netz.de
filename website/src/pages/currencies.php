<?php
/**
 * Currencies Page - List all available currencies
 * Uses the centralized CurrencyApi service
 */

// API service is already initialized in index.php
$currencies = null;
$error = null;

try {
    $currencies = $api->listCurrenciesSafe();
    if ($currencies === null) {
        $error = 'Währungsdaten konnten nicht geladen werden.';
    }
} catch (Exception $e) {
    error_log('Currencies page error: ' . $e->getMessage());
    $error = 'Ein Fehler ist aufgetreten.';
}
?>

<div class="max-w-default mx-auto px-4 md:px-0">
    <h1 class="font-heading text-3xl text-text mb-6">Währungen</h1>

    <div class="bg-white border border-border rounded-lg shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-background-alt border-b border-border">
                        <th class="text-left p-4 text-sm font-semibold text-muted">Währung</th>
                        <th class="text-left p-4 text-sm font-semibold text-muted">Name</th>
                        <th class="text-left p-4 text-sm font-semibold text-muted">Land</th>
                        <th class="text-right p-4 text-sm font-semibold text-muted">Wechselkurs</th>
                        <th class="text-center p-4 text-sm font-semibold text-muted">Info</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($error): ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-negative">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-8 h-8 text-negative/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <span><?= htmlspecialchars($error) ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php elseif ($currencies && is_array($currencies)): ?>
                        <?php foreach ($currencies as $currency): ?>
                            <?php $formattedRate = number_format($currency['exchange_rate'], 4, ',', '.'); ?>
                            <tr class="border-b border-border hover:bg-background-alt transition-colors">
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-semibold text-primary"><?= htmlspecialchars($currency['short']) ?></span>
                                        <span class="text-muted text-sm">(<?= htmlspecialchars($currency['symbol']) ?>)</span>
                                    </div>
                                </td>
                                <td class="p-4 text-text"><?= htmlspecialchars($currency['name']) ?></td>
                                <td class="p-4 text-muted"><?= htmlspecialchars($currency['country']) ?></td>
                                <td class="p-4 text-right">
                                    <span class="font-mono"><?= $formattedRate ?></span>
                                    <span class="text-muted text-sm ml-1"><?= htmlspecialchars($currency['forex']) ?></span>
                                </td>
                                <td class="p-4 text-center">
                                    <button type="button"
                                            onclick="showCurrencyModal(<?= htmlspecialchars(json_encode($currency)) ?>)"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary hover:bg-primary hover:text-white transition-colors"
                                            title="Details anzeigen">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-muted">
                                Keine Währungen verfügbar.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Currency Detail Modal -->
<div id="currencyModal" 
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     onclick="if(event.target === this) closeCurrencyModal()">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-primary-dark/50 backdrop-blur-sm"></div>
    
    <!-- Modal Content -->
    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-border">
            <h3 class="font-heading text-lg text-text">Währungsdetails</h3>
            <button type="button" 
                    onclick="closeCurrencyModal()"
                    class="text-muted hover:text-text transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <!-- Body -->
        <div id="modalContent" class="p-6">
            <!-- Content injected by JavaScript -->
        </div>
        
        <!-- Footer -->
        <div class="flex justify-end p-4 border-t border-border bg-background-alt rounded-b-lg">
            <button type="button" 
                    onclick="closeCurrencyModal()"
                    class="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary-dark transition-colors">
                Schließen
            </button>
        </div>
    </div>
</div>

<script>
function showCurrencyModal(currency) {
    const modal = document.getElementById('currencyModal');
    const content = document.getElementById('modalContent');
    
    const rate = parseFloat(currency.exchange_rate) || 0;
    
    content.innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center gap-3 pb-4 border-b border-border">
                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                    <span class="font-mono font-bold text-primary text-lg">${currency.symbol}</span>
                </div>
                <div>
                    <div class="font-semibold text-text">${currency.short}</div>
                    <div class="text-sm text-muted">${currency.name}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-muted mb-1">Land</div>
                    <div class="text-text font-medium">${currency.country}</div>
                </div>
                <div>
                    <div class="text-muted mb-1">Unterteilung</div>
                    <div class="text-text font-medium">${currency.breakdown || 'N/A'}</div>
                </div>
                <div class="col-span-2">
                    <div class="text-muted mb-1">Wechselkurs</div>
                    <div class="text-text font-mono font-medium text-lg">
                        ${rate.toFixed(4)} <span class="text-muted text-sm">${currency.forex}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCurrencyModal() {
    document.getElementById('currencyModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCurrencyModal();
});
</script>
