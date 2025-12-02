<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * CORS Middleware
 * 
 * Handles Cross-Origin Resource Sharing headers for API requests.
 */
class CorsMiddleware
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
            'max_age' => 86400,
        ], $config);
    }

    /**
     * Handles CORS headers and preflight requests.
     *
     * @return bool True if request should continue, false if it was a preflight.
     */
    public function handle(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        
        // Check if origin is allowed
        if ($this->isOriginAllowed($origin)) {
            $allowedOrigin = in_array('*', $this->config['allowed_origins']) ? '*' : $origin;
            header("Access-Control-Allow-Origin: {$allowedOrigin}");
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $this->config['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->config['allowed_headers']));
        header('Access-Control-Max-Age: ' . $this->config['max_age']);

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return false;
        }

        return true;
    }

    /**
     * Checks if the given origin is allowed.
     *
     * @param string $origin The origin to check.
     * @return bool True if allowed.
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->config['allowed_origins'])) {
            return true;
        }

        return in_array($origin, $this->config['allowed_origins']);
    }
}

