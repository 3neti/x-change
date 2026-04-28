<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;

it('approves a pending manual approval workflow through the lifecycle route surface', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $store = app(ClaimApprovalWorkflowStoreContract::class);

    $store->put($voucher, [
        'status' => 'pending',
        'requirements' => ['manual_approval'],
        'payload' => [
            'mobile' => '639171234567',
        ],
    ]);

    $response = $this->postJson(route('api.x.v1.vouchers.claim.approve', [
        'code' => $voucher->code,
    ]), [
        'approved_by' => 'tester',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
});

it('returns not found when approving an unknown voucher code', function () {
    $response = $this->postJson(route('api.x.v1.vouchers.claim.approve', [
        'code' => 'MISSING',
    ]));

    $response->assertNotFound();
});
