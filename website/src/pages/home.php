<?php
/**
 * Home Page - Currency History and Rates
 * Uses centralized JavaScript from /js/app.js
 */
?>

<div class="max-w-default mx-auto px-4 md:px-0">

    <!-- Currency History Section -->
    <section class="mb-12">
        <h1 class="font-heading text-3xl text-text mb-6">Währungshistorie</h1>

        <div class="bg-white border border-border rounded-lg p-6 shadow-card">
            <!-- Currency Pair Selection -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="fromCurrency" class="block text-sm font-medium text-muted mb-2">Von Währung</label>
                    <select id="fromCurrency" 
                            class="w-full border border-border rounded px-4 py-3 bg-white text-text cursor-pointer focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary select-styled">
                        <option value="">Währung wählen...</option>
                    </select>
                </div>
                <div>
                    <label for="toCurrency" class="block text-sm font-medium text-muted mb-2">Zu Währung</label>
                    <select id="toCurrency" 
                            class="w-full border border-border rounded px-4 py-3 bg-white text-text cursor-pointer focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary select-styled">
                        <option value="">Währung wählen...</option>
                    </select>
                </div>
            </div>

            <!-- Analysis Info Panel -->
            <div id="analysis-info" class="mb-4 hidden p-4 bg-background-alt border border-border rounded">
                <div class="flex justify-between items-center flex-wrap gap-y-2">
                    <div class="text-sm">
                        <span class="font-medium text-muted">Zeitraum: </span>
                        <span id="date-range" class="text-text">Wird geladen...</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="font-medium text-muted">Veränderung: </span>
                        <div class="flex items-center gap-1">
                            <span id="trend-arrow"></span>
                            <span id="change-value">Wird berechnet...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Container -->
            <div class="relative w-full h-[400px] border border-border rounded bg-white mb-4">
                <div id="candlestickChartContainer" class="w-full h-full"></div>
                <div id="chart-loading" class="absolute inset-0 flex items-center justify-center bg-white/90 hidden">
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                        <span class="text-muted">Lade Chartdaten...</span>
                    </div>
                </div>
            </div>

            <!-- Time Range Buttons -->
            <div class="flex flex-wrap gap-2">
                <button onclick="resetZoom()" 
                        class="px-4 py-2 text-sm border border-border rounded hover:border-primary hover:text-primary transition-colors">
                    Zoom zurücksetzen
                </button>
                <div class="w-px bg-border mx-2"></div>
                <button onclick="updateChartWithRange('1W')" 
                        class="px-4 py-2 text-sm border border-border rounded hover:border-primary hover:text-primary transition-colors">
                    1W
                </button>
                <button onclick="updateChartWithRange('1M')" 
                        class="px-4 py-2 text-sm border border-border rounded hover:border-primary hover:text-primary transition-colors">
                    1M
                </button>
                <button onclick="updateChartWithRange('3M')" 
                        class="px-4 py-2 text-sm border border-border rounded hover:border-primary hover:text-primary transition-colors">
                    3M
                </button>
                <button onclick="updateChartWithRange('1Y')" 
                        class="px-4 py-2 text-sm border border-border rounded hover:border-primary hover:text-primary transition-colors">
                    1J
                </button>
                <button onclick="updateChartWithRange('ALL')" 
                        class="px-4 py-2 text-sm border border-border rounded hover:border-primary hover:text-primary transition-colors">
                    Alles
                </button>
                <div class="w-px bg-border mx-2"></div>
                <input type="date" 
                       id="customStartDate" 
                       class="px-3 py-2 text-sm border border-border rounded focus:border-primary focus:outline-none">
                <input type="date" 
                       id="customEndDate" 
                       max="<?= date('Y-m-d') ?>" 
                       class="px-3 py-2 text-sm border border-border rounded focus:border-primary focus:outline-none">
                <button onclick="updateChartWithCustomRange()" 
                        class="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary-dark transition-colors">
                    Anwenden
                </button>
            </div>
        </div>
    </section>

    <!-- Current Rates Section -->
    <section>
        <h2 class="font-heading text-2xl text-text mb-6">Aktuelle Kurse</h2>

        <div class="bg-white border border-border rounded-lg p-6 shadow-card">
            <!-- Base Currency Selection -->
            <div class="mb-6">
                <label for="baseCurrency" class="block text-sm font-medium text-muted mb-2">Basiswährung</label>
                <select id="baseCurrency"
                        class="w-64 border border-border rounded px-4 py-3 bg-white text-text cursor-pointer focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary select-styled">
                    <option value="">Lade...</option>
                </select>
            </div>

            <!-- Rates Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-background-alt border-b border-border">
                            <th class="text-left p-3 text-sm font-semibold text-muted">Paar</th>
                            <th class="text-right p-3 text-sm font-semibold text-muted">Kurs</th>
                            <th class="text-center p-3 text-sm font-semibold text-muted w-24">7 Tage</th>
                            <th class="text-right p-3 text-sm font-semibold text-muted">Änderung</th>
                            <th class="text-right p-3 text-sm font-semibold text-muted">Zeit</th>
                        </tr>
                    </thead>
                    <tbody id="ratesTableBody">
                        <tr>
                            <td colspan="5" class="text-center p-8 text-muted">
                                <div class="flex items-center justify-center gap-3">
                                    <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                                    <span>Lade Kurse...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

</div>

<!-- Lightweight Charts Library -->
<script src="https://unpkg.com/lightweight-charts@5.0.7/dist/lightweight-charts.standalone.production.js"></script>
