<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Mapping
    |--------------------------------------------------------------------------
    |
    | This array maps payment method names to their corresponding gateway
    | class implementations.
    |
    */

    'gateways' => [
        'credit_card' => \App\Gateways\CreditCardGateway::class,
        'debit_card' => \App\Gateways\CreditCardGateway::class,
        'paypal' => \App\Gateways\PayPalGateway::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for each payment gateway.
    |
    */

    'gateway_config' => [
        'credit_card' => [
            'api_key' => env('CREDIT_CARD_API_KEY'),
            'secret' => env('CREDIT_CARD_SECRET'),
            'endpoint' => env('CREDIT_CARD_ENDPOINT', 'https://api.creditcard-gateway.com'),
            'timeout' => env('CREDIT_CARD_TIMEOUT', 30),
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'endpoint' => env('PAYPAL_ENDPOINT', 'https://api.sandbox.paypal.com'),
        ],
    ],
];

