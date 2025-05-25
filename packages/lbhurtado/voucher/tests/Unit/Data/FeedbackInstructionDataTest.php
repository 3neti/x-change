<?php

use LBHurtado\Voucher\Data\FeedbackInstructionData;

it('validates and serializes feedback instruction data', function () {
    $data = FeedbackInstructionData::from([
        'email' => 'feedback@acme.com',
        'mobile' => '09171234567',
        'webhook' => 'https://acme.com/webhook',
    ]);

    expect($data->email)->toBe('feedback@acme.com');
    expect($data->mobile)->toBe('09171234567');
    expect($data->webhook)->toBe('https://acme.com/webhook');
});
