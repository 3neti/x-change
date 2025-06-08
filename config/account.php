<?php

return [
    'system_user' => [
        'identifier' => env('SYSTEM_USER_ID', 'lester@hurtado.ph'),
        'identifier_column' => 'email',
        'model' => App\Models\System::class
    ],
];
