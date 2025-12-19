<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Exception;

/**
 * Currency Converter Service
 * 
 * Handles conversion between virtual and real currencies using
 * database rates and the Frankfurter API for real-time forex rates.
 */
class CurrencyConverter
{
    private PDO $pdo;
    private array $config;
    private array $frankfurterCache = [];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'frankfurter_url' => 'https://api.frankfurter.app',
            'timeout' => 15,
        ], $config);
    }

    /**
     * Retrieves currency information from the database.
     *
     * @param string $code The currency code.
     * @return array|null Currency data or null if not found.
     */
    private function getCurrencyInfo(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT code, exchange_rate, real_currency, exchange_direction FROM currencies WHERE code = ?');
        $stmt->execute([strtoupper($code)]);
        $currency = $stmt->fetch(PDO::FETCH_ASSOC);
        return $currency ?: null;
    }

    /**
     * Calculates the base rate (value of 1 custom unit in its real currency).
     *
     * @param array $info Currency info from the database.
     * @return float The base rate.
     */
    private function getBaseRate(array $info): float
    {
        $rate = (float) $info['exchange_rate'];
        $direction = $info['exchange_direction'] ?? 'real_to_custom';

        if ($direction === 'custom_to_real') {
            return $rate;
        }

        // Default: real_to_custom (1 real = X custom => 1 custom = 1/X real)
        return $rate != 0 ? 1.0 / $rate : 0.0;
    }

    /**
     * Fetches the current exchange rate from the Frankfurter API using cURL.
     *
     * @param string $from Source currency code.
     * @param string $to Target currency code.
     * @return float The exchange rate.
     * @throws Exception If the API request fails.
     */
    private function getForexRate(string $from, string $to): float
    {
        $cacheKey = "{$from}_{$to}";
        if (isset($this->frankfurterCache[$cacheKey])) {
            return $this->frankfurterCache[$cacheKey];
        }

        if ($from === $to) {
            return 1.0;
        }

        $url = "{$this->config['frankfurter_url']}/latest?from={$from}&to={$to}";
        $data = $this->fetchFromApi($url);

        if (!isset($data['rates'][$to])) {
            throw new Exception("Exchange rate not available for {$from} to {$to}.", 404);
        }

        $this->frankfurterCache[$cacheKey] = (float) $data['rates'][$to];
        return $this->frankfurterCache[$cacheKey];
    }

    /**
     * Performs currency conversion.
     *
     * @param float $amount The amount to convert.
     * @param string $fromCurrency Source currency code.
     * @param string $toCurrency Target currency code.
     * @return float The converted amount.
     * @throws Exception If conversion fails.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $fromInfo = $this->getCurrencyInfo($fromCurrency);
        $toInfo = $this->getCurrencyInfo($toCurrency);

        if (!$fromInfo || !$toInfo) {
            throw new Exception("One or both currencies not found.", 404);
        }

        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $fromBaseRate = $this->getBaseRate($fromInfo);
        $toBaseRate = $this->getBaseRate($toInfo);

        if ($toBaseRate == 0) {
            throw new Exception("Exchange rate for target currency {$toCurrency} results in zero value.", 400);
        }

        $rate = 1.0;

        if ($fromInfo['real_currency'] === $toInfo['real_currency']) {
            // Conversion within the same real currency group
            $rate = $fromBaseRate / $toBaseRate;
        } else {
            // Conversion via Frankfurter API for different real currencies
            $forexRate = $this->getForexRate($fromInfo['real_currency'], $toInfo['real_currency']);
            $rate = ($fromBaseRate / $toBaseRate) * $forexRate;
        }

        return round($amount * $rate, 5);
    }

    /**
     * Retrieves current exchange rates relative to a base currency.
     *
     * @param string $baseCurrency The base currency code.
     * @return array Rates data including change percentages.
     * @throws Exception If the base currency is not found.
     */
    public function getRates(string $baseCurrency): array
    {
        $baseInfo = $this->getCurrencyInfo($baseCurrency);
        if (!$baseInfo) {
            throw new Exception("Base currency not found.", 404);
        }

        $stmt = $this->pdo->prepare('SELECT code FROM currencies WHERE code != ?');
        $stmt->execute([strtoupper($baseCurrency)]);
        $currencyCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $rates = ['base' => $baseCurrency, 'rates' => []];

        foreach ($currencyCodes as $code) {
            $currencyInfo = $this->getCurrencyInfo($code);
            if ($currencyInfo) {
                try {
                    $currentRate = $this->convert(1, $baseCurrency, $code);
                    
                    $rates['rates'][$code] = [
                        'value' => round($currentRate, 4),
                        'change' => 0.0, // Historical change calculation would require stored data
                        'timestamp' => date('c'),
                    ];
                } catch (Exception $e) {
                    $rates['rates'][$code] = ['error' => $e->getMessage()];
                }
            }
        }

        return $rates;
    }

    /**
     * Retrieves historical exchange rates for a currency pair.
     *
     * @param string $fromCurrency Source currency code.
     * @param string $toCurrency Target currency code.
     * @param string $startDate Start date (YYYY-MM-DD).
     * @param string $endDate End date (YYYY-MM-DD).
     * @return array Array of date-rate pairs.
     * @throws Exception If currencies are not found or API fails.
     */
    public function getHistoricalRates(string $fromCurrency, string $toCurrency, string $startDate, string $endDate): array
    {
        $fromInfo = $this->getCurrencyInfo($fromCurrency);
        $toInfo = $this->getCurrencyInfo($toCurrency);

        if (!$fromInfo || !$toInfo) {
            throw new Exception("One or both currencies not found.", 404);
        }

        // Case 1: Same virtual currency
        if ($fromCurrency === $toCurrency) {
            return $this->generateConstantRates($startDate, $endDate, 1.0);
        }

        $fromBaseRate = $this->getBaseRate($fromInfo);
        $toBaseRate = $this->getBaseRate($toInfo);

        if ($toBaseRate == 0) {
            throw new Exception("Exchange rate for target currency {$toCurrency} results in zero value.", 400);
        }

        // Case 2: Different virtual currencies but same real base currency
        if ($fromInfo['real_currency'] === $toInfo['real_currency']) {
            $constantRate = $fromBaseRate / $toBaseRate;
            return $this->generateConstantRates($startDate, $endDate, round($constantRate, 4));
        }

        // Case 3: Different real base currencies - fetch from API
        $historicalForexRates = $this->fetchHistoricalForexRates(
            $fromInfo['real_currency'],
            $toInfo['real_currency'],
            $startDate,
            $endDate
        );

        $adjustedRates = [];
        foreach ($historicalForexRates as $entry) {
            $forexRate = $entry['rate'];
            $calculatedRate = ($fromBaseRate / $toBaseRate) * $forexRate;
            $adjustedRates[] = ['date' => $entry['date'], 'rate' => round($calculatedRate, 4)];
        }

        return $adjustedRates;
    }

    /**
     * Generates an array of constant rates for a date range.
     *
     * @param string $startDate Start date.
     * @param string $endDate End date.
     * @param float $rate The constant rate.
     * @return array Array of date-rate pairs.
     */
    private function generateConstantRates(string $startDate, string $endDate, float $rate): array
    {
        $rates = [];
        $currentDate = new \DateTime($startDate);
        $endDateTime = new \DateTime($endDate);
        
        while ($currentDate <= $endDateTime) {
            $rates[] = ['date' => $currentDate->format('Y-m-d'), 'rate' => $rate];
            $currentDate->modify('+1 day');
        }
        
        return $rates;
    }

    /**
     * Fetches historical forex rates from the Frankfurter API.
     *
     * @param string $from Source real currency.
     * @param string $to Target real currency.
     * @param string $startDate Start date.
     * @param string $endDate End date.
     * @return array Array of date-rate pairs.
     * @throws Exception If API request fails.
     */
    private function fetchHistoricalForexRates(string $from, string $to, string $startDate, string $endDate): array
    {
        $url = "{$this->config['frankfurter_url']}/{$startDate}..{$endDate}?from={$from}&to={$to}";
        $data = $this->fetchFromApi($url);

        if (!isset($data['rates'])) {
            throw new Exception("Historical rates not available for {$from} to {$to}.", 404);
        }

        $rates = [];
        foreach ($data['rates'] as $date => $rate) {
            if (isset($rate[$to])) {
                $rates[] = ['date' => $date, 'rate' => $rate[$to]];
            }
        }
        
        return $rates;
    }

    /**
     * Performs an API request using cURL.
     *
     * @param string $url The URL to fetch.
     * @return array Decoded JSON response.
     * @throws Exception If the request fails.
     */
    private function fetchFromApi(string $url): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: CurrencyConverterAPI/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Failed to fetch data from external API: {$error}", 503);
        }

        if ($httpCode !== 200) {
            throw new Exception("External API returned HTTP {$httpCode}.", 502);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from external API.", 502);
        }

        return $data;
    }

    /**
     * Returns a list of all supported currencies.
     *
     * @return array List of currencies with details.
     */
    public function listCurrencies(): array
    {
        $stmt = $this->pdo->query('
            SELECT
                name,
                code as short,
                symbol,
                country,
                subdivision as breakdown,
                exchange_rate,
                exchange_direction,
                real_currency as forex
            FROM currencies
            ORDER BY name
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

