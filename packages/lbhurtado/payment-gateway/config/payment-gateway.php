<?php

return [
    'models' => [
        'user' => \LBHurtado\PaymentGateway\Tests\Models\User::class,
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
];
