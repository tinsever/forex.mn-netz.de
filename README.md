# mnFOREX

A fictional currency exchange simulator with real-time rates, historical charts, and a currency calculator.

# API
Coming soon...

# Website

## Requirements

- PHP 7.4 or higher
- cURL extension enabled
- Web server (Apache, nginx, or PHP built-in server)

## Installation

1. Upload the contents of this repository to your web server
2. Ensure the `public/` directory is your document root (or configure accordingly)
3. Edit `config/app.php` to adjust settings if needed

## Configuration

All configuration is inside the `config/app.php`:

```php
return [
    'api' => [
        'base_url' => 'https://your-api-endpoint.com/api',
        'timeout' => 15,
    ],
    'defaults' => [
        'base_currency' => 'EUR',
        'target_currency' => 'USD',
    ],
    'ui' => [
        'site_name' => 'Your Site Name',
    ],
];
```

### Environment Variables

You can override the API URL using an environment variable:

```bash
export API_BASE="https://your-api.com/api"
```

## API Integration

This simulator connects to an external currency API. The API service class (`src/Services/CurrencyApi.php`) handles all communication with proper error handling.

Available API actions:
- `list` - Get all available currencies
- `rates` - Get exchange rates for a base currency
- `convert` - Convert amount between currencies
- `historical` - Get historical exchange rates

## License

MIT License - See [LICENSE](LICENSE) file for details.

## Credits

- Charts powered by [LightweightCharts](https://tradingview.github.io/lightweight-charts/)
- Styling with [Tailwind CSS](https://tailwindcss.com/)
- Fonts: DM Serif Display, Source Sans 3