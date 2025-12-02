# mnFOREX

A fictional currency exchange platform with real-time rates, historical charts, and a currency calculator. Includes both a frontend website and a standalone REST API.

## Project Structure

```
forex/
├── api/                    # REST API (standalone)
│   ├── config/
│   ├── public/
│   └── src/
├── website/                # Frontend application
│   ├── config/
│   ├── public/
│   └── src/
├── LICENSE
└── README.md
```

---

# API

A PHP REST API for converting between virtual and real-world currencies. Uses the [Frankfurter API](https://www.frankfurter.app/) for real-time forex rates.

## Features

- Convert between virtual and real currencies
- Get current exchange rates for all currencies
- Retrieve historical exchange rates
- Built-in CORS support
- File-based rate limiting (no Redis required)
- PSR-4 autoloading

## Requirements

- PHP 8.0+
- MySQL/MariaDB
- Composer
- cURL extension

## Installation

1. **Install dependencies**
   ```bash
   cd api
   composer install
   ```

2. **Configure the application**
   ```bash
   cp config/app.php.example config/app.php
   ```
   
   Edit `config/app.php` with your database credentials.

3. **Set up the database**
   
   Create a `currencies` table:
   ```sql
   CREATE TABLE currencies (
       id INT AUTO_INCREMENT PRIMARY KEY,
       name VARCHAR(100) NOT NULL,
       code VARCHAR(10) NOT NULL UNIQUE,
       symbol VARCHAR(10),
       country VARCHAR(100),
       subdivision VARCHAR(100),
       exchange_rate DECIMAL(20, 10) NOT NULL,
       real_currency VARCHAR(10) NOT NULL
   );
   ```

4. **Configure your web server**
   
   Point your document root to the `api/public/` directory.

## API Endpoints

### List Currencies

```
GET ?action=list
```

**Response:**
```json
{
    "success": true,
    "action": "list",
    "data": [
        {
            "name": "Vyrth",
            "short": "VYR",
            "symbol": "$",
            "country": "Gurkistan",
            "breakdown": "100 cents",
            "exchange_rate": "1.0000000000",
            "forex": "EUR"
        }
    ]
}
```

### Convert Currency

```
GET ?action=convert&amount={amount}&from={from_code}&to={to_code}
```

**Example:**
```
GET ?action=convert&amount=100&from=VYR&to=EUR
```

**Response:**
```json
{
    "success": true,
    "action": "convert",
    "data": {
        "from": "VYR",
        "to": "EUR",
        "amount": 100,
        "result": 92.45
    }
}
```

### Get Current Rates

```
GET ?action=rates&base={base_code}
```

**Response:**
```json
{
    "success": true,
    "action": "rates",
    "data": {
        "base": "VYR",
        "rates": {
            "EUR": {
                "value": 0.9245,
                "change": 0.0,
                "timestamp": "2024-01-15T12:00:00+00:00"
            }
        }
    }
}
```

### Get Historical Rates

```
GET ?action=historical&from={from}&to={to}&start={YYYY-MM-DD}&end={YYYY-MM-DD}
```

**Response:**
```json
{
    "success": true,
    "action": "historical",
    "data": {
        "from": "VYR",
        "to": "EUR",
        "start_date": "2024-01-01",
        "end_date": "2024-01-07",
        "rates": [
            {"date": "2024-01-01", "rate": 0.9245},
            {"date": "2024-01-02", "rate": 0.9250}
        ]
    }
}
```

## Error Handling

```json
{
    "success": false,
    "error": {
        "code": 400,
        "message": "Missing required parameter: 'amount' for action 'convert'."
    }
}
```

| Code | Description                           |
|------|---------------------------------------|
| 200  | Success                               |
| 400  | Bad Request (missing/invalid params)  |
| 404  | Not Found (currency/action not found) |
| 405  | Method Not Allowed                    |
| 429  | Rate Limit Exceeded                   |
| 502  | Bad Gateway (external API error)      |
| 503  | Service Unavailable (database error)  |

## Rate Limiting

- Default: 60 requests per minute per IP
- Headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

## Configuration

Edit `api/config/app.php`:

```php
return [
    'database' => [
        'host' => 'localhost',
        'name' => 'your_db',
        'user' => 'your_user',
        'pass' => 'your_password',
    ],
    'cors' => [
        'allowed_origins' => ['https://your-frontend.com'],
    ],
    'rate_limit' => [
        'enabled' => true,
        'requests_per_minute' => 60,
    ],
    'api' => [
        'debug' => false,
    ],
];
```

---

# Website

## Requirements

- PHP 7.4 or higher
- cURL extension enabled
- Web server (Apache, nginx, or PHP built-in server)

## Installation

1. Upload the contents of this repository to your web server
2. Ensure `website/public/` is your document root
3. Edit `website/config/app.php` to adjust settings

## Configuration

Edit `website/config/app.php`:

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

Override the API URL using an environment variable:

```bash
export API_BASE="https://your-api.com/api"
```

---

## License

MIT License - See [LICENSE](LICENSE) file for details.

## Credits

- Exchange rates by [Frankfurter API](https://www.frankfurter.app/)
- Charts powered by [LightweightCharts](https://tradingview.github.io/lightweight-charts/)
- Styling with [Tailwind CSS](https://tailwindcss.com/)
- Fonts: DM Serif Display, Source Sans 3
