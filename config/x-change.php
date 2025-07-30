<?php

use Carbon\CarbonInterval;
use LBHurtado\Voucher\Enums\VoucherInputField;

return [
    'generate' => [
        'country' => env('GENERATE_COUNTRY', 'PH'),
        'prefix' => env('GENERATE_PREFIX'), // New field for prefix
        'mask' => env('GENERATE_MASK', '****'), // New field for mask
    ],

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
                    VoucherInputField::LOCATION,
                    VoucherInputField::REFERENCE_CODE
                ],
                'validation' => [
                    'name' => 'required|string',
                    'address' => 'required|string',
                    'birth_date' => 'required|date',
                    'email' => 'required|email',
                    'gross_monthly_income' => 'required|numeric|min:0',
                    'location' => 'required|string',
                    'reference_code' => 'required|string',
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
        ],

    ],

    'pricelist' => [

        'cash.amount' => [
            'price' => 20 * 100,
            'description' => 'Amount of cash the user will receive',
        ],
//        'cash.currency' => [
//            'price' => 110,
//            'description' => 'Currency of the cash disbursement',
//        ],
        'cash.validation.secret' => [
            'price' => 120,
            'description' => 'Secret code required to claim the voucher',
        ],
        'cash.validation.mobile' => [
            'price' => 130,
            'description' => 'Mobile number required to redeem',
        ],
//        'cash.validation.country' => [
//            'price' => 140,
//            'description' => 'Country of the claimant',
//        ],
        'cash.validation.location' => [
            'price' => 150,
            'description' => 'Expected location of redemption',
        ],
        'cash.validation.radius' => [
            'price' => 160,
            'description' => 'Radius around the location where redemption is valid',
        ],
        'feedback.email' => [
            'price' => 170,
            'label' => 'Email Address',
            'description' => 'Email address to send voucher feedback to',
        ],
        'feedback.mobile' => [
            'price' => 180,
            'label' => 'Mobile Number',
            'description' => 'Mobile number to send feedback SMS to',
        ],
        'feedback.webhook' => [
            'price' => 190,
            'label' => 'Webhook URL',
            'description' => 'Webhook URL to notify after redemption',
        ],
        'rider.message' => [
            'price' => 200,
            'label' => 'Rider Message',
            'description' => 'Message shown to the rider or recipient',
        ],
        'rider.url' => [
            'price' => 210,
            'label' => 'Rider URL',
            'description' => 'Redirect link shown to the user after redemption',
        ],
        'inputs.fields.email' => [
            'price' => 220,
            'description' => 'Email input field required from the user',
        ],
        'inputs.fields.mobile' => [
            'price' => 230,
            'description' => 'Mobile Number input field required from the user',
        ],
        'inputs.fields.name' => [
            'price' => 240,
            'description' => 'Name input field required from the user',
        ],
        'inputs.fields.address' => [
            'price' => 250,
            'label' => 'Full Address',
            'description' => 'Address input field required from the user',
        ],
        'inputs.fields.birth_date' => [
            'price' => 260,
            'description' => 'Birth Date input field required from the user',
        ],
        'inputs.fields.gross_monthly_income' => [
            'price' => 270,
            'description' => 'Gross Monthly Income input field required from the user',
        ],
        'inputs.fields.signature' => [
            'price' => 280,
            'description' => 'Signature input field required from the user',
        ],
        'inputs.fields.location' => [
            'price' => 300,
            'description' => 'Location input field required from the user',
        ],
    ],
];
