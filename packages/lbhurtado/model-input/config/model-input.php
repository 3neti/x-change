<?php

return [
    'rules' => [
        'mobile' => ['required', (new \Propaganistas\LaravelPhone\Rules\Phone)->country('PH')->type('mobile')],
        'signature' => ['required', 'string', 'min:8'],
    ],
];
