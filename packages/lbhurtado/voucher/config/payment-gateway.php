<?php

return [
    'models' => [
        'user' => class_exists(App\Models\User::class)
            ? App\Models\User::class
            : LBHurtado\Voucher\Tests\Models\User::class,
    ],
    'default' => env('PAYMENT_GATEWAY', 'netbank'),

    'drivers' => [
        'netbank' => [
            'base_url' => env('NETBANK_API_URL'),
            'api_key' => env('NETBANK_API_KEY'),
        ],
        'icash' => [
            'base_url' => env('ICASH_API_URL'),
            'api_key' => env('ICASH_API_KEY'),
        ],
    ],

    'gateway' => LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway::class,


    'routes' => [
        'enabled' => env('PAYMENT_GATEWAY_ROUTES_ENABLED', true),

        'prefix' => env('PAYMENT_GATEWAY_ROUTE_PREFIX', 'api'),

        'middleware' => ['api'],

        'name_prefix' => env('PAYMENT_GATEWAY_ROUTE_NAME_PREFIX', ), // e.g., 'pg.'

        'version' => env('PAYMENT_GATEWAY_ROUTE_VERSION',), // e.g., 'v1'

        'domain' => env('PAYMENT_GATEWAY_DOMAIN'), // optional
    ],
];
