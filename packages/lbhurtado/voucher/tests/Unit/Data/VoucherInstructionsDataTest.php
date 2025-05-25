<?php

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;

it('validates and serializes voucher instructions data', function () {
    $data = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 1000,
            'currency' => 'PHP',
            'validation' => [
                'secret' => '123456',
                'mobile' => '09171234567',
                'country' => 'PH',
                'location' => 'Makati City',
                'radius' => '1000m',
            ],
        ],
        'inputs' => [
            'fields' => ['email', 'mobile', 'reference_code'],
        ],
        'feedback' => [
            'email' => 'feedback@acme.com',
            'mobile' => '09171234567',
            'webhook' => 'https://acme.com/webhook',
        ],
        'rider' => [
            'message' => 'Thanks for using our service!',
            'url' => 'https://acme.com/rider',
        ],
    ]);

    expect($data->cash->amount)->toBe(1000);
    expect($data->inputs->fields)->toContain(VoucherInputField::EMAIL);
    expect($data->feedback->email)->toBe('feedback@acme.com');
    expect($data->rider->message)->toBe('Thanks for using our service!');
});
