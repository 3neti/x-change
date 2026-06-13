<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayloadSession;

it('stores and retrieves claim approval resume payload by voucher', function () {
    $voucher = issueVoucher();

    $store = app(ClaimApprovalResumePayloadSession::class);

    $store->put($voucher, [
        'mobile' => '639171234567',
        'bank_code' => 'GXI',
        'account_number' => '09173011987',
    ]);

    expect($store->get($voucher))->toMatchArray([
        'mobile' => '639171234567',
        'bank_code' => 'GXI',
        'account_number' => '09173011987',
    ]);
});

it('forgets claim approval resume payload by voucher', function () {
    $voucher = issueVoucher();

    $store = app(ClaimApprovalResumePayloadSession::class);

    $store->put($voucher, [
        'mobile' => '639171234567',
    ]);

    $store->forget($voucher);

    expect($store->get($voucher))->toBeNull();
});
