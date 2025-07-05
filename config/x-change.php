<?php

use LBHurtado\Voucher\Enums\VoucherInputField;

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

            // 📥 General Inputs Page
            'inputs' => [
                'enabled' => true,
                'title' => 'Gather Inputs',
                'route' => 'redeem.inputs',
                'page' => 'Redeem/Inputs',
                'session_key' => 'inputs',
                'fields' => [
                    VoucherInputField::EMAIL,
                    VoucherInputField::BIRTH_DATE,
                    VoucherInputField::GROSS_MONTHLY_INCOME,
                    VoucherInputField::NAME,
                    VoucherInputField::ADDRESS,
                ],
                'validation' => [
                    'name' => 'required|string',
                    'address' => 'required|string',
                    'birth_date' => 'required|date',
                    'email' => 'required|email',
                    'gross_monthly_income' => 'required|numeric|min:0',
                ],
                'middleware' => [
                    App\Http\Middleware\Redeem\CheckVoucherMiddleware::class,
                ],
            ],

            // ✍️ Signature Capture Page
            'signature' => [
                'enabled' => true,
                'title' => 'Capture Signature',
                'route' => 'redeem.signature',
                'page' => 'Redeem/Signature',
                'session_key' => 'signature',
                'fields' => [
                    VoucherInputField::SIGNATURE,
                ],
                'validation' => [
                    'signature' => 'required|string',
                ],
                'middleware' => [
                    App\Http\Middleware\Redeem\CheckVoucherMiddleware::class,
                ],
            ],

            // 📸 Selfie Capture Page
            'selfie' => [
                'enabled' => false,
                'title' => 'Capture Selfie',
                'route' => 'redeem.selfie',
                'page' => 'Redeem/Selfie',
                'session_key' => 'kyc',
                'fields' => [
                    VoucherInputField::KYC,
                ],
                'validation' => [
                    'kyc' => 'required|string', // Adjust as needed
                ],
                'middleware' => [
                    App\Http\Middleware\Redeem\CheckVoucherMiddleware::class,
                ],
            ],

//            // 🏦 Bank Account Info (optional)
//            'bank' => [
//                'enabled' => false,
//                'title' => 'Provide Bank Info',
//                'route' => 'redeem.bank',
//                'page' => 'Redeem/BankAccount',
//                'session_key' => 'bank_account',
//                'fields' => [
//                    VoucherInputField::BANK_ACCOUNT,
//                ],
//                'validation' => [
//                    'bank_account' => 'required|string', // Adjust as needed
//                ],
//                'middleware' => [
//                    App\Http\Middleware\Redeem\CheckVoucherMiddleware::class,
//                ],
//            ],

        ],

    ],

];
