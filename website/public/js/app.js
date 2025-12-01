/**
 * Forex Currency Simulator - Main Application JavaScript
 * Handles all API communication and UI interactions
 */

// Configuration - will be set by PHP (snake_case from PHP -> camelCase here)
const ForexApp = {
    apiBase: window.FOREX_CONFIG?.apiBase || 'https://xc.mn-netz.de/api',
    defaults: {
        baseCurrency: window.FOREX_CONFIG?.defaults?.base_currency || 'VYR',
        targetCurrency: window.FOREX_CONFIG?.defaults?.target_currency || 'USD',
        chartFrom: window.FOREX_CONFIG?.defaults?.chart_from || 'VYR',
        chartTo: window.FOREX_CONFIG?.defaults?.chart_to || 'USD',
        ratesBase: window.FOREX_CONFIG?.defaults?.rates_base || 'IRE'
    }
};

// ============================================================================
// UTILITIES
// ============================================================================

const Formatter = {
    number: (value, minDigits = 2, maxDigits = 5) => {
        return new Intl.NumberFormat('de-DE', {
            minimumFractionDigits: minDigits,
            maximumFractionDigits: maxDigits
        }).format(value);
    },

    percentage: (value) => {
        return new Intl.NumberFormat('de-DE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
            signDisplay: 'always'
        }).format(value);
    },

    date: (date) => {
        return new Date(date).toLocaleDateString('de-DE');
    }
};

// ============================================================================
// API SERVICE
// ============================================================================

const CurrencyAPI = {
    async request(action, params = {}) {
        const url = new URL(ForexApp.apiBase);
        url.searchParams.set('action', action);
        Object.entries(params).forEach(([key, value]) => {
            url.searchParams.set(key, value);
        });

        try {
            const response = await fetch(url.toString());
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || `HTTP ${response.status}`);
            }
            const data = await response.json();
            if (data.error) {
                throw new Error(data.error);
            }
            return data;
        } catch (error) {
            console.error(`API Error (${action}):`, error);
            throw error;
        }
    },

    async listCurrencies() {
        return this.request('list');
    },

    async getRates(baseCurrency) {
        return this.request('rates', { base: baseCurrency });
    },

    async convert(amount, from, to) {
        return this.request('convert', { amount, from, to });
    },

    async getHistorical(from, to, startDate, endDate) {
        return this.request('historical', { from, to, start: startDate, end: endDate });
    }
};

// ============================================================================
// CURRENCY SELECTOR
// ============================================================================

const CurrencySelector = {
    currencies: null,

    async load() {
        if (this.currencies) return this.currencies;
        
        try {
            const data = await CurrencyAPI.listCurrencies();
            if (Array.isArray(data)) {
                this.currencies = data;
                return data;
            }
            throw new Error('Invalid currency data format');
        } catch (error) {
            console.error('Failed to load currencies:', error);
            return null;
        }
    },

    populate(selectId, defaultValue = null) {
        const select = document.getElementById(selectId);
        if (!select || !this.currencies) return;

        select.innerHTML = '<option value="">Währung wählen...</option>';
        
        this.currencies.forEach(currency => {
            const option = document.createElement('option');
            option.value = currency.short;
            option.textContent = `${currency.short} - ${currency.name}`;
            if (currency.short === defaultValue) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    },

    async init(selectors) {
        const currencies = await this.load();
        if (!currencies) return false;

        Object.entries(selectors).forEach(([selectId, defaultValue]) => {
            this.populate(selectId, defaultValue);
        });
        return true;
    }
};

// ============================================================================
// CURRENCY CALCULATOR
// ============================================================================

const Calculator = {
    elements: {
        fromAmount: 'fromAmount',
        fromCurrency: 'calculatorFromCurrency',
        toCurrency: 'calculatorToCurrency',
        toAmount: 'toAmount',
        exchangeInfo: 'exchangeInfo',
        swapButton: 'swapCurrencies'
    },

    async calculate() {
        const fromCurrency = document.getElementById(this.elements.fromCurrency)?.value;
        const toCurrency = document.getElementById(this.elements.toCurrency)?.value;
        const amount = parseFloat(document.getElementById(this.elements.fromAmount)?.value) || 0;

        const toAmountEl = document.getElementById(this.elements.toAmount);
        const exchangeInfoEl = document.getElementById(this.elements.exchangeInfo);
        const toCurrencyLabel = document.getElementById('toAmountCurrency');

        // Update currency label
        if (toCurrencyLabel) {
            toCurrencyLabel.textContent = toCurrency || '';
        }

        if (!fromCurrency || !toCurrency || amount < 0) {
            if (toAmountEl) toAmountEl.value = '';
            if (exchangeInfoEl) exchangeInfoEl.classList.add('hidden');
            return;
        }

        try {
            const data = await CurrencyAPI.convert(amount, fromCurrency, toCurrency);
            
            if (data && data.result !== undefined) {
                if (toAmountEl) {
                    toAmountEl.value = Formatter.number(data.result, 2, 5);
                }
                if (exchangeInfoEl) {
                    // Calculate rate from result if not provided
                    const rate = data.rate || (amount > 0 ? data.result / amount : 0);
                    if (rate && !isNaN(rate)) {
                        exchangeInfoEl.innerHTML = `1 ${fromCurrency} = ${Formatter.number(rate, 4, 6)} ${toCurrency}`;
                        exchangeInfoEl.classList.remove('hidden');
                    } else {
                        exchangeInfoEl.classList.add('hidden');
                    }
                }
            }
        } catch (error) {
            if (toAmountEl) toAmountEl.value = '';
            if (exchangeInfoEl) {
                exchangeInfoEl.innerHTML = `Fehler: ${error.message}`;
                exchangeInfoEl.classList.remove('hidden');
            }
        }
    },

    swap() {
        const fromSelect = document.getElementById(this.elements.fromCurrency);
        const toSelect = document.getElementById(this.elements.toCurrency);
        
        if (fromSelect && toSelect) {
            const temp = fromSelect.value;
            fromSelect.value = toSelect.value;
            toSelect.value = temp;
            this.calculate();
        }
    },

    sanitizeInput(input) {
        let value = input.value.replace(',', '.');
        value = value.replace(/[^\d.]/g, '');
        const parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        if (parseFloat(value) < 0) {
            value = '0';
        }
        input.value = value;
    },

    init() {
        const fromAmountInput = document.getElementById(this.elements.fromAmount);
        if (fromAmountInput) {
            fromAmountInput.addEventListener('input', () => {
                this.sanitizeInput(fromAmountInput);
                this.calculate();
            });
        }

        [this.elements.fromCurrency, this.elements.toCurrency].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', () => this.calculate());
            }
        });

        // Swap button
        const swapBtn = document.getElementById(this.elements.swapButton);
        if (swapBtn) {
            swapBtn.addEventListener('click', () => this.swap());
        }
    }
};

// ============================================================================
// SPARKLINE - Mini Chart with REAL data
// ============================================================================

const Sparkline = {
    // Fetch real historical data for sparkline
    async fetchData(baseCurrency, targetCurrency, days = 7) {
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(endDate.getDate() - days);
        
        const formatDate = (d) => d.toISOString().split('T')[0];
        
        try {
            const data = await CurrencyAPI.getHistorical(
                baseCurrency, 
                targetCurrency, 
                formatDate(startDate), 
                formatDate(endDate)
            );
            
            if (data.rates && Array.isArray(data.rates)) {
                return data.rates.map(r => r.rate);
            }
            return null;
        } catch (e) {
            console.error(`Sparkline data fetch failed for ${baseCurrency}/${targetCurrency}:`, e);
            return null;
        }
    },

    // Calculate change percentage from data
    calculateChange(data) {
        if (!data || data.length < 2) return 0;
        const first = data[0];
        const last = data[data.length - 1];
        if (first === 0) return 0;
        return ((last - first) / first) * 100;
    },

    // Draw sparkline on canvas
    draw(canvas, data, isPositive) {
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;
        const padding = 2;
        
        ctx.clearRect(0, 0, width, height);
        
        if (!data || data.length < 2) {
            // Draw placeholder line
            ctx.beginPath();
            ctx.strokeStyle = '#a0aec0';
            ctx.lineWidth = 1;
            ctx.moveTo(padding, height / 2);
            ctx.lineTo(width - padding, height / 2);
            ctx.stroke();
            return;
        }
        
        const min = Math.min(...data);
        const max = Math.max(...data);
        const range = max - min || 1;
        
        // Draw line
        ctx.beginPath();
        ctx.strokeStyle = isPositive ? '#38a169' : '#c53030';
        ctx.lineWidth = 1.5;
        ctx.lineJoin = 'round';
        
        data.forEach((value, i) => {
            const x = padding + (i / (data.length - 1)) * (width - padding * 2);
            const y = height - padding - ((value - min) / range) * (height - padding * 2);
            
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        
        ctx.stroke();
        
        // Draw end dot
        const lastX = width - padding;
        const lastY = height - padding - ((data[data.length - 1] - min) / range) * (height - padding * 2);
        ctx.beginPath();
        ctx.fillStyle = isPositive ? '#38a169' : '#c53030';
        ctx.arc(lastX, lastY, 2, 0, Math.PI * 2);
        ctx.fill();
    }
};

// ============================================================================
// RATES TABLE
// ============================================================================

const RatesTable = {
    elements: {
        baseCurrency: 'baseCurrency',
        tableBody: 'ratesTableBody'
    },

    async load(baseCurrency) {
        if (!baseCurrency) return;

        const tableBody = document.getElementById(this.elements.tableBody);
        if (!tableBody) return;

        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center p-8 text-muted">
                    <div class="flex items-center justify-center gap-3">
                        <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                        <span>Lade Kurse und historische Daten...</span>
                    </div>
                </td>
            </tr>
        `;

        try {
            const data = await CurrencyAPI.getRates(baseCurrency);
            
            if (data.rates && typeof data.rates === 'object') {
                await this.render(baseCurrency, data.rates);
            } else {
                throw new Error('Invalid rates data');
            }
        } catch (error) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center p-4 text-negative">
                        Fehler: ${error.message}
                    </td>
                </tr>
            `;
        }
    },

    async render(baseCurrency, rates) {
        const tableBody = document.getElementById(this.elements.tableBody);
        if (!tableBody) return;

        tableBody.innerHTML = '';

        if (Object.keys(rates).length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center p-4">Keine Kurse verfügbar.</td></tr>';
            return;
        }

        let index = 0;
        const entries = Object.entries(rates);
        
        for (const [currencyCode, rateInfo] of entries) {
            const row = document.createElement('tr');
            row.className = 'border-b border-border hover:bg-background-alt transition-colors';

            if (rateInfo.error) {
                row.innerHTML = `
                    <td class="p-4 font-medium">${baseCurrency}/${currencyCode}</td>
                    <td colspan="4" class="p-4 text-negative text-center">Fehler: ${rateInfo.error}</td>
                `;
                tableBody.appendChild(row);
            } else {
                const canvasId = `sparkline-${index}`;

                // Create row with loading state for sparkline
                row.innerHTML = `
                    <td class="p-3 font-semibold text-primary">${baseCurrency}/${currencyCode}</td>
                    <td class="p-3 text-right font-mono text-lg">${Formatter.number(rateInfo.value || 0, 4, 5)}</td>
                    <td class="p-3">
                        <canvas id="${canvasId}" width="80" height="30" class="block"></canvas>
                    </td>
                    <td class="p-3 text-right" id="change-${index}">
                        <span class="text-muted">--</span>
                    </td>
                    <td class="p-3 text-right text-muted text-sm">${rateInfo.timestamp || 'Live'}</td>
                `;
                
                tableBody.appendChild(row);
                
                // Fetch real historical data and draw sparkline
                this.loadSparkline(baseCurrency, currencyCode, canvasId, index);
            }
            index++;
        }
    },

    async loadSparkline(baseCurrency, targetCurrency, canvasId, rowIndex) {
        const canvas = document.getElementById(canvasId);
        const changeCell = document.getElementById(`change-${rowIndex}`);
        
        if (!canvas) return;

        // Fetch real data
        const data = await Sparkline.fetchData(baseCurrency, targetCurrency, 7);
        
        if (data && data.length > 1) {
            const change = Sparkline.calculateChange(data);
            const isPositive = change >= 0;
            const trendClass = isPositive ? 'text-positive' : 'text-negative';
            
            // Update change cell with real data
            if (changeCell) {
                changeCell.innerHTML = `<span class="${trendClass} font-semibold">${Formatter.percentage(change)}%</span>`;
            }
            
            // Draw sparkline with real data
            Sparkline.draw(canvas, data, isPositive);
        } else {
            // No data available
            if (changeCell) {
                changeCell.innerHTML = `<span class="text-muted">N/A</span>`;
            }
            Sparkline.draw(canvas, null, true);
        }
    },

    init() {
        const select = document.getElementById(this.elements.baseCurrency);
        if (select) {
            select.addEventListener('change', (e) => this.load(e.target.value));
        }
    }
};

// ============================================================================
// CHART MODULE
// ============================================================================

const Chart = {
    instance: null,
    series: null,
    elements: {
        container: 'candlestickChartContainer',
        loading: 'chart-loading',
        fromCurrency: 'fromCurrency',
        toCurrency: 'toCurrency',
        analysisInfo: 'analysis-info',
        dateRange: 'date-range',
        trendArrow: 'trend-arrow',
        changeValue: 'change-value',
        customStartDate: 'customStartDate',
        customEndDate: 'customEndDate'
    },

    showLoading(show) {
        const loadingDiv = document.getElementById(this.elements.loading);
        if (loadingDiv) {
            loadingDiv.classList.toggle('hidden', !show);
        }
    },

    getDateRange(range) {
        const today = new Date();
        let startDate = new Date();

        switch (range) {
            case '1W': startDate.setDate(today.getDate() - 7); break;
            case '1M': startDate.setMonth(today.getMonth() - 1); break;
            case '3M': startDate.setMonth(today.getMonth() - 3); break;
            case '6M': startDate.setMonth(today.getMonth() - 6); break;
            case '1Y': startDate.setFullYear(today.getFullYear() - 1); break;
            case 'ALL': startDate = new Date('2010-01-01'); break;
            default: startDate.setFullYear(today.getFullYear() - 1);
        }

        return {
            start: startDate.toISOString().split('T')[0],
            end: today.toISOString().split('T')[0]
        };
    },

    updateAnalysis() {
        if (!this.series || !this.series.data || this.series.data.length === 0) {
            document.getElementById(this.elements.analysisInfo)?.classList.add('hidden');
            return;
        }

        const data = this.series.data;
        const first = data[0];
        const last = data[data.length - 1];

        if (!first || !last || first.value === undefined || last.value === undefined || first.value === 0) {
            document.getElementById(this.elements.analysisInfo)?.classList.add('hidden');
            return;
        }

        const startDate = Formatter.date(first.time * 1000);
        const endDate = Formatter.date(last.time * 1000);
        const absoluteChange = last.value - first.value;
        const percentageChange = (absoluteChange / first.value) * 100;

        const analysisDiv = document.getElementById(this.elements.analysisInfo);
        if (analysisDiv) analysisDiv.classList.remove('hidden');

        const dateRangeEl = document.getElementById(this.elements.dateRange);
        if (dateRangeEl) dateRangeEl.textContent = `${startDate} bis ${endDate}`;

        const trendArrow = document.getElementById(this.elements.trendArrow);
        const changeEl = document.getElementById(this.elements.changeValue);

        if (trendArrow && changeEl) {
            if (percentageChange > 0.005) {
                trendArrow.innerHTML = '↗';
                trendArrow.className = 'text-positive text-xl font-bold';
                changeEl.className = 'text-positive font-medium';
            } else if (percentageChange < -0.005) {
                trendArrow.innerHTML = '↘';
                trendArrow.className = 'text-negative text-xl font-bold';
                changeEl.className = 'text-negative font-medium';
            } else {
                trendArrow.innerHTML = '→';
                trendArrow.className = 'text-muted text-xl font-bold';
                changeEl.className = 'text-muted font-medium';
            }
            changeEl.textContent = `${Formatter.number(absoluteChange, 4, 4)} (${Formatter.percentage(percentageChange)}%)`;
        }
    },

    async update(startDate, endDate) {
        const fromCurrency = document.getElementById(this.elements.fromCurrency)?.value;
        const toCurrency = document.getElementById(this.elements.toCurrency)?.value;

        if (!fromCurrency || !toCurrency || !startDate || !endDate) return;

        this.showLoading(true);
        const container = document.getElementById(this.elements.container);

        try {
            const data = await CurrencyAPI.getHistorical(fromCurrency, toCurrency, startDate, endDate);

            if (data.rates && Array.isArray(data.rates)) {
                const chartData = data.rates.map(rate => ({
                    time: new Date(rate.date).getTime() / 1000,
                    value: rate.rate
                }));

                if (!this.instance && typeof LightweightCharts !== 'undefined') {
                    this.instance = LightweightCharts.createChart(container, {
                        layout: {
                            textColor: '#2d3748',
                            background: { type: 'solid', color: '#ffffff' }
                        },
                        grid: {
                            vertLines: { color: '#e2e8f0' },
                            horzLines: { color: '#e2e8f0' }
                        },
                        crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
                        rightPriceScale: { borderColor: '#cbd5e0' },
                        timeScale: {
                            borderColor: '#cbd5e0',
                            timeVisible: true,
                            secondsVisible: false,
                            tickMarkFormatter: (time) => Formatter.date(time * 1000)
                        },
                        localization: {
                            locale: 'de-DE',
                            timeFormatter: (timestamp) => Formatter.date(timestamp * 1000),
                            priceFormatter: (price) => Formatter.number(price, 4, 5)
                        }
                    });

                    this.series = this.instance.addSeries(LightweightCharts.LineSeries, {
                        color: '#1a365d',
                        lineWidth: 2,
                        crosshairMarkerVisible: true,
                        crosshairMarkerRadius: 4,
                        priceLineVisible: false,
                        lastValueVisible: false
                    });
                } else if (this.series) {
                    this.series.setData([]);
                }

                if (this.series) {
                    this.series.setData(chartData);
                    this.instance.timeScale().fitContent();
                    this.updateAnalysis();
                }
            } else {
                container.innerHTML = '<p class="text-center p-4 text-muted">Keine Daten für diese Auswahl verfügbar.</p>';
                document.getElementById(this.elements.analysisInfo)?.classList.add('hidden');
            }
        } catch (error) {
            container.innerHTML = `<p class="text-center p-4 text-negative">Fehler beim Laden: ${error.message}</p>`;
            document.getElementById(this.elements.analysisInfo)?.classList.add('hidden');
        } finally {
            this.showLoading(false);
        }
    },

    updateWithRange(range) {
        const dateRange = this.getDateRange(range);
        this.update(dateRange.start, dateRange.end);
    },

    updateWithCustomRange() {
        const startDate = document.getElementById(this.elements.customStartDate)?.value;
        const endDate = document.getElementById(this.elements.customEndDate)?.value;
        if (startDate && endDate && startDate <= endDate) {
            this.update(startDate, endDate);
        } else {
            alert('Bitte gültiges Start- und Enddatum auswählen.');
        }
    },

    resetZoom() {
        if (this.instance) {
            this.instance.timeScale().fitContent();
        }
    },

    init() {
        const fromSelect = document.getElementById(this.elements.fromCurrency);
        const toSelect = document.getElementById(this.elements.toCurrency);

        if (fromSelect) {
            fromSelect.addEventListener('change', () => this.updateWithRange('1Y'));
        }
        if (toSelect) {
            toSelect.addEventListener('change', () => this.updateWithRange('1Y'));
        }
    }
};

// Global functions for onclick handlers
window.updateChartWithRange = (range) => Chart.updateWithRange(range);
window.updateChartWithCustomRange = () => Chart.updateWithCustomRange();
window.resetZoom = () => Chart.resetZoom();

// ============================================================================
// INITIALIZATION
// ============================================================================

document.addEventListener('DOMContentLoaded', async function() {
    // Initialize currency selectors
    const selectors = {
        calculatorFromCurrency: ForexApp.defaults.baseCurrency,
        calculatorToCurrency: ForexApp.defaults.targetCurrency,
        fromCurrency: ForexApp.defaults.chartFrom,
        toCurrency: ForexApp.defaults.chartTo,
        baseCurrency: ForexApp.defaults.ratesBase
    };

    const currenciesLoaded = await CurrencySelector.init(selectors);

    if (currenciesLoaded) {
        // Initialize calculator
        Calculator.init();
        Calculator.calculate();

        // Initialize chart (if on home page)
        if (document.getElementById('candlestickChartContainer')) {
            Chart.init();
            Chart.updateWithRange('1Y');
        }

        // Initialize rates table (if on home page)
        if (document.getElementById('ratesTableBody')) {
            RatesTable.init();
            RatesTable.load(ForexApp.defaults.ratesBase);
        }
    } else {
        console.error('Failed to initialize: currencies could not be loaded');
    }
});

