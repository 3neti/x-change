<?php

use LBHurtado\Voucher\Data\RiderInstructionData;

it('validates and serializes rider instruction data', function () {
    $data = RiderInstructionData::from([
        'message' => 'Thank you for claiming!',
        'url' => 'https://acme.com/redirect'
    ]);
    expect($data->message)->toBe('Thank you for claiming!');
    expect($data->url)->toBe('https://acme.com/redirect');
});
