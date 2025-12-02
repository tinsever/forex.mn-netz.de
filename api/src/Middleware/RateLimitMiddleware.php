<?php

declare(strict_types=1);

namespace App\Middleware;

use Exception;

/**
 * Rate Limit Middleware
 * 
 * File-based rate limiting for API requests.
 * Limits requests per IP address per minute.
 */
class RateLimitMiddleware
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => true,
            'requests_per_minute' => 60,
            'storage_path' => __DIR__ . '/../../storage/rate_limits',
        ], $config);

        // Ensure storage directory exists
        if ($this->config['enabled'] && !is_dir($this->config['storage_path'])) {
            @mkdir($this->config['storage_path'], 0755, true);
        }
    }

    /**
     * Checks if the request is within rate limits.
     *
     * @return bool True if request is allowed.
     * @throws Exception If rate limit is exceeded.
     */
    public function handle(): bool
    {
        if (!$this->config['enabled']) {
            return true;
        }

        $ip = $this->getClientIp();
        $key = md5($ip);
        $filePath = $this->config['storage_path'] . "/{$key}.json";
        
        $currentMinute = (int) (time() / 60);
        $data = $this->loadData($filePath);

        // Reset if we're in a new minute
        if (!isset($data['minute']) || $data['minute'] !== $currentMinute) {
            $data = [
                'minute' => $currentMinute,
                'count' => 0,
            ];
        }

        $data['count']++;

        // Set rate limit headers
        $remaining = max(0, $this->config['requests_per_minute'] - $data['count']);
        header('X-RateLimit-Limit: ' . $this->config['requests_per_minute']);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . (($currentMinute + 1) * 60));

        if ($data['count'] > $this->config['requests_per_minute']) {
            $this->saveData($filePath, $data);
            throw new Exception('Rate limit exceeded. Please try again later.', 429);
        }

        $this->saveData($filePath, $data);
        $this->cleanup();

        return true;
    }

    /**
     * Gets the client IP address.
     *
     * @return string The client IP.
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_REAL_IP',            // Nginx
            'REMOTE_ADDR',               // Direct
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * Loads rate limit data from file.
     *
     * @param string $filePath Path to the data file.
     * @return array The rate limit data.
     */
    private function loadData(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Saves rate limit data to file.
     *
     * @param string $filePath Path to the data file.
     * @param array $data The data to save.
     */
    private function saveData(string $filePath, array $data): void
    {
        @file_put_contents($filePath, json_encode($data), LOCK_EX);
    }

    /**
     * Cleans up old rate limit files (runs occasionally).
     */
    private function cleanup(): void
    {
        // Only run cleanup ~1% of the time
        if (rand(1, 100) !== 1) {
            return;
        }

        $files = glob($this->config['storage_path'] . '/*.json');
        if (!$files) {
            return;
        }

        $cutoff = time() - 120; // Remove files older than 2 minutes
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}

