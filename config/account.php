<?php

return [
    'system_user' => [
        'identifier' => env('SYSTEM_USER_ID', 'admin@disburse.cash'),
        'identifier_column' => 'email',
        'model' => App\Models\System::class
    ],
];
