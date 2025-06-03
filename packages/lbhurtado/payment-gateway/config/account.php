<?php

return [
    'system_user' => [
        'identifier' => env('SYSTEM_USER_ID', 'lester@hurtado.ph'),
        'identifier_column' => 'email',
        'model' => \LBHurtado\PaymentGateway\Tests\Models\User::class,
    ],
];
