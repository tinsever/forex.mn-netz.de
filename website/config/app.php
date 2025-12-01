<?php
/**
 * Application Configuration
 */

return [
    // API Configuration
    'api' => [
        'base_url' => getenv('API_BASE') ?: 'https://xc.mn-netz.de/api',
        'timeout' => 15,
        'user_agent' => 'mnFOREX/1.0',
    ],

    // Default Currencies
    'defaults' => [
        'base_currency' => 'VYR',
        'target_currency' => 'USD',
        'chart_from' => 'VYR',
        'chart_to' => 'USD',
        'rates_base' => 'IRE',
    ],

    // UI Settings
    'ui' => [
        'site_name' => 'mnFOREX',
        'date_format' => 'd.m.Y',
        'number_decimals' => 4,
        'chart_default_range' => '1Y',
    ],

    // Error Messages
    'errors' => [
        'network' => 'Netzwerkfehler: Verbindung zum Server fehlgeschlagen.',
        'api' => 'API-Fehler: Die Anfrage konnte nicht verarbeitet werden.',
        'json' => 'Datenfehler: Ungültige Antwort vom Server.',
        'currency_not_found' => 'Währung nicht gefunden.',
        'invalid_amount' => 'Ungültiger Betrag eingegeben.',
        'general' => 'Ein unerwarteter Fehler ist aufgetreten.',
    ],
];

