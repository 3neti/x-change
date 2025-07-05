<?php

return [
    'rules' => [
        'email' => ['required', 'email'],
        'mobile' => ['required', (new \Propaganistas\LaravelPhone\Rules\Phone)->country('PH')->type('mobile')],
        'signature' => ['required', 'string', 'min:8'],
        'bank_account' => ['required', 'string', 'min:8'], //TODO: increase the min
        'name' => ['required', 'string', 'min:2', 'max:255'],
        'address' => ['required', 'string', 'min:10', 'max:255'],
        'birth_date' => ['required', 'date', 'before_or_equal:today'],
        'gross_monthly_income' => ['required', 'numeric', 'min:10000', 'max:1000000'],
    ],
];
