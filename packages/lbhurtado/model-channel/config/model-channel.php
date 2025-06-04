<?php

return [
    'rules' => [
        'mobile' => ['required', (new \Propaganistas\LaravelPhone\Rules\Phone)->country('PH')->type('mobile')],
        'webhook' => ['required', 'url'],
    ],
];
