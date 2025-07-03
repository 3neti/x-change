<?php

return [

    'redeem' => [

        'reference' => [
            'label' => env('REDEEM_REFERENCE_LABEL', 'Reference'),
            'value' => env('REDEEM_REFERENCE_VALUE', ''),
        ],

        'success' => [
            'redirect_timeout' => env('RIDER_REDIRECT_TIMEOUT', 5000),
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
                    'birthdate' => 'required|date',
                    'email' => 'required|email',
                    'gross_monthly_income' => 'required|numeric|min:0',
                ],
                'middleware' => [
                    App\Http\Middleware\Redeem\CheckVoucherMiddleware::class
                ],
            ],

            'signature' => [
                'enabled' => false,
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
