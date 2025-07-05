<?php

return [

    'redeem' => [

        'reference' => [
            'label' => env('REDEEM_REFERENCE_LABEL', 'Reference'),
            'value' => env('REDEEM_REFERENCE_VALUE', ''),
        ],

        'success' => [
            'redirect_timeout' => env('RIDER_REDIRECT_TIMEOUT', 5000),
            'rider' => env('RIDER_REDIRECT_URL', 'https://mosaically.com/photomosaic/d7d3f985-ef62-49a4-897d-e209061475d0'),
        ],

        'auto_feedback' => (bool) env('REDEEM_AUTO_FEEDBACK', true),

        /**
         * 💡 Plugins define dynamic redemption steps
         */
        'plugins' => [
            'inputs' => [
                'enabled' => true,
                'title' => 'Gather Inputs',
                'route' => 'redeem.inputs',
                'page' => 'Redeem/Inputs',
                'session_key' => 'inputs',
                'validation' => [
                    'name' => 'required|string',
                    'address' => 'required|string',
                    'birth_date' => 'required|date',
                    'email' => 'required|email',
                    'gross_monthly_income' => 'required|numeric|min:0',
                ],
                'middleware' => [
                    App\Http\Middleware\Redeem\CheckVoucherMiddleware::class
                ],
            ],

            'signature' => [
                'enabled' => true,
                'title' => 'Capture Signature',
                'route' => 'redeem.signature',
                'page' => 'Redeem/Signature',
                'session_key' => 'signature',
                'validation' => [
                    'signature' => 'required|string',
                ],
                'middleware' => [
                    App\Http\Middleware\Redeem\CheckVoucherMiddleware::class
                ],
            ],
        ],
    ],

];
