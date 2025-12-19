<?php
/**
 * mnFOREX - Currency API Service
 * 
 * Centralized API communication for all currency operations.
 * Handles requests to the external currency exchange API with
 * proper error handling and response validation.
 * 
 * @package mnFOREX
 * @author  Tin Sever
 * @license MIT
 */

class CurrencyApi
{
    /** @var string API base URL */
    private string $baseUrl;
    
    /** @var int Request timeout in seconds */
    private int $timeout;
    
    /** @var string User agent for API requests (DSGVO: neutral, keine persönlichen Daten) */
    private string $userAgent;
    
    /** @var array Error message templates */
    private array $errors;

    /**
     * Initialize the API service
     * 
     * @param array $config Application configuration
     */
    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['api']['base_url'], '/');
        $this->timeout = $config['api']['timeout'] ?? 15;
        $this->userAgent = $config['api']['user_agent'] ?? 'CurrencyAPI/1.0';
        $this->errors = $config['errors'] ?? [];
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * Fetch all available currencies
     * 
     * @return array List of currencies with codes, names, and rates
     * @throws Exception on API error
     */
    public function listCurrencies(): array
    {
        return $this->request('list');
    }

    /**
     * Get current exchange rates for a base currency
     * 
     * @param string $baseCurrency Base currency code (e.g., 'EUR')
     * @return array Rates data with currency pairs and values
     * @throws Exception on API error
     */
    public function getRates(string $baseCurrency): array
    {
        return $this->request('rates', ['base' => $baseCurrency]);
    }

    /**
     * Convert amount between two currencies
     * 
     * @param float  $amount Amount to convert
     * @param string $from   Source currency code
     * @param string $to     Target currency code
     * @return array Conversion result with rate and converted amount
     * @throws Exception on API error
     */
    public function convert(float $amount, string $from, string $to): array
    {
        return $this->request('convert', [
            'amount' => $amount,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Get historical exchange rates for a date range
     * 
     * @param string $from      Source currency code
     * @param string $to        Target currency code
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate   End date (Y-m-d format)
     * @return array Historical rate data
     * @throws Exception on API error
     */
    public function getHistorical(string $from, string $to, string $startDate, string $endDate): array
    {
        return $this->request('historical', [
            'from' => $from,
            'to' => $to,
            'start' => $startDate,
            'end' => $endDate,
        ]);
    }

    // =========================================================================
    // SAFE WRAPPERS (return null instead of throwing)
    // =========================================================================

    /**
     * Safe wrapper for listCurrencies - returns null on error
     * Useful for template rendering where exceptions are inconvenient.
     * 
     * @return array|null Currencies array or null on error
     */
    public function listCurrenciesSafe(): ?array
    {
        try {
            return $this->listCurrencies();
        } catch (Exception $e) {
            error_log('CurrencyApi::listCurrencies failed: ' . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // GETTERS
    // =========================================================================

    /**
     * Get API base URL (for JavaScript configuration)
     * 
     * @return string Base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    // =========================================================================
    // PRIVATE METHODS
    // =========================================================================

    /**
     * Make HTTP request to the API
     * 
     * DSGVO Note: This method does not log or transmit any user-identifying
     * information. The User-Agent is generic and no IP addresses are stored.
     * 
     * @param string $action API action (list, rates, convert, historical)
     * @param array  $params Query parameters
     * @return array Parsed JSON response
     * @throws Exception on network, HTTP, or parsing errors
     */
    private function request(string $action, array $params = []): array
    {
        $params['action'] = $action;
        $url = $this->baseUrl . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_FOLLOWLOCATION => true,
            // SSL: Disabled for compatibility (some shared hosts lack CA bundle)
            // In production with proper CA bundle, set both to true/2
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // Validate response
        $this->validateResponse($response, $httpCode, $curlError, $curlErrno, $url);

        // Parse JSON
        $data = json_decode($response, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for URL: {$url}");
            throw new Exception($this->getError('json') . ' (' . json_last_error_msg() . ')');
        }

        // Check for API-level errors
        if (isset($data['error'])) {
            $message = $data['error']['message'] ?? $data['error'];
            throw new Exception($message);
        }

        return $data['data'] ?? $data;
    }

    /**
     * Validate cURL response
     * 
     * @param string|false $response  cURL response body
     * @param int          $httpCode  HTTP status code
     * @param string       $curlError cURL error message
     * @param int          $curlErrno cURL error number
     * @param string       $url       Request URL (for logging)
     * @throws Exception on validation failure
     */
    private function validateResponse($response, int $httpCode, string $curlError, int $curlErrno, string $url): void
    {
        // Network error
        if ($curlError || $curlErrno) {
            throw new Exception($this->getError('network') . " (cURL {$curlErrno}: {$curlError})");
        }

        // Empty response
        if (empty($response)) {
            throw new Exception($this->getError('network') . ' (Empty response)');
        }

        // HTTP error
        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $apiError = $errorData['error'] ?? $this->getError('api');
            throw new Exception($apiError . " (HTTP {$httpCode})");
        }
    }

    /**
     * Get localized error message
     * 
     * @param string $key Error key
     * @return string Error message
     */
    private function getError(string $key): string
    {
        return $this->errors[$key] ?? $this->errors['general'] ?? 'Unknown error';
    }
}
