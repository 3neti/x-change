<?php

use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Data\InputFieldsData;

it('can create InputFieldsData from strings and cast to enum', function () {
    $fields = ['email', 'mobile', 'kyc'];

    $data = InputFieldsData::fromArray($fields);

    expect($data->fields)->toHaveCount(3);
    expect($data->fields[0])->toBeInstanceOf(VoucherInputField::class);
    expect($data->fields[0])->toBe(VoucherInputField::EMAIL);
    expect($data->contains(VoucherInputField::KYC))->toBeTrue();
    expect($data->contains(VoucherInputField::SIGNATURE))->toBeFalse();
    expect($data->toArray())->toEqual(['email', 'mobile', 'kyc']);
});
