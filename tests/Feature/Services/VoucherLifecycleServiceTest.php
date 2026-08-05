<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Data\Claims\ApprovalStatusData;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\VoucherLifecycleService;

it('lists vouchers as lifecycle summaries', function () {
    $voucher = issueVoucher();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn([$voucher]);

    $service = new VoucherLifecycleService($access);

    $result = $service->list([]);

    expect($result)->toBeArray()
        ->and($result[0]['voucher_id'])->toBe($voucher->id)
        ->and($result[0]['code'])->toBe($voucher->code)
        ->and($result[0]['currency'])->toBe((string) data_get($voucher, 'cash.currency', 'PHP'));
});

it('uses immutable instructions for treasury-backed voucher face value', function () {
    $voucher = issueVoucher(validVoucherInstructions(amount: 20));
    $voucher->voucherEntities()->delete();
    $voucher = $voucher->fresh();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn([$voucher]);

    $result = (new VoucherLifecycleService($access))->list([]);

    expect($voucher->cash)->toBeNull()
        ->and($result[0]['amount'])->toBe(20.0)
        ->and($result[0]['currency'])->toBe('PHP');
});

it('includes pending approval summary for vouchers requiring Paynamics OTP approval', function () {
    $voucher = issueVoucher();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn([$voucher]);

    $approvalStatus = new class implements ClaimApprovalStatusResolver
    {
        public function resolve(Voucher $voucher): ?ApprovalStatusData
        {
            return new ApprovalStatusData(
                status: 'approval_required',
                voucher_code: (string) $voucher->code,
                messages: ['Payout OTP approval required.'],
                provider: 'paynamics',
                authorization_type: 'otp',
                reference_id: $voucher->code.'-09173011987',
                otp_required: true,
                message: 'Paynamics payout OTP is pending.',
            );
        }
    };

    $service = new VoucherLifecycleService($access, $approvalStatus);

    $result = $service->list([]);

    expect($result[0]['approval'])->toMatchArray([
        'required' => true,
        'type' => 'otp',
        'provider' => 'paynamics',
        'reference_id' => $voucher->code.'-09173011987',
        'message' => 'Paynamics payout OTP is pending.',
    ])
        ->and($result[0]['display_status'])->toBe('awaiting_approval')
        ->and($result[0]['approval']['action_url'])->toContain('/x/pay-codes/'.$voucher->code.'/approval');
});

it('omits approval summary for vouchers without pending approval', function () {
    $voucher = issueVoucher();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn([$voucher]);

    $approvalStatus = new class implements ClaimApprovalStatusResolver
    {
        public function resolve(Voucher $voucher): ?ApprovalStatusData
        {
            return null;
        }
    };

    $service = new VoucherLifecycleService($access, $approvalStatus);

    $result = $service->list([]);

    expect($result[0]['approval'])->toBeNull()
        ->and($result[0]['display_status'])->toBe($result[0]['status']);
});

it('keeps a completed non-payout redemption primary when approval metadata remains pending', function () {
    $voucher = issueVoucher();
    $voucher->redeemed_at = now();
    $voucher->save();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn([$voucher->fresh()]);

    $approvalStatus = new class implements ClaimApprovalStatusResolver
    {
        public function resolve(Voucher $voucher): ?ApprovalStatusData
        {
            return new ApprovalStatusData(
                status: 'approval_required',
                voucher_code: (string) $voucher->code,
                messages: ['Payout OTP approval required.'],
                provider: 'paynamics',
                authorization_type: 'otp',
                reference_id: $voucher->code.'-09173011987',
                otp_required: true,
                message: 'Paynamics payout OTP is pending.',
            );
        }
    };

    $service = new VoucherLifecycleService($access, $approvalStatus);

    $result = $service->list([]);

    expect($result[0]['status'])->toBe('redeemed')
        ->and($result[0]['display_status'])->toBe('redeemed')
        ->and($result[0]['operational_status']['settlement_outcome'])->toBe('not_applicable')
        ->and($result[0]['approval'])->toMatchArray([
            'required' => true,
            'type' => 'otp',
            'provider' => 'paynamics',
            'reference_id' => $voucher->code.'-09173011987',
            'message' => 'Paynamics payout OTP is pending.',
        ]);
});

it('shows a voucher by id', function () {
    $voucher = issueVoucher();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('findOrFail')
        ->once()
        ->with((string) $voucher->id)
        ->andReturn($voucher);

    $service = new VoucherLifecycleService($access);

    $result = $service->show((string) $voucher->id);

    expect($result)->toBeArray()
        ->and($result['voucher_id'])->toBe($voucher->id)
        ->and($result['code'])->toBe($voucher->code)
        ->and($result['display_status'])->toBe($result['status']);
});

it('shows a voucher by code', function () {
    $voucher = issueVoucher();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('findByCodeOrFail')
        ->once()
        ->with($voucher->code)
        ->andReturn($voucher);

    $service = new VoucherLifecycleService($access);

    $result = $service->showByCode($voucher->code);

    expect($result)->toBeArray()
        ->and($result['voucher_id'])->toBe($voucher->id)
        ->and($result['code'])->toBe($voucher->code);
});

it('includes dates, instructions, and claims in detail response', function () {
    $voucher = issueVoucher();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('findByCodeOrFail')
        ->once()
        ->with($voucher->code)
        ->andReturn($voucher);

    $service = new VoucherLifecycleService($access);

    $result = $service->showByCode($voucher->code);

    // Dates
    expect($result)->toHaveKey('created_at')
        ->and($result)->toHaveKey('expires_at')
        ->and($result)->toHaveKey('starts_at')
        ->and($result)->toHaveKey('redeemed_at');

    // Instructions
    expect($result)->toHaveKey('instructions')
        ->and($result['instructions'])->toBeArray()
        ->and($result['instructions'])->toHaveKey('cash')
        ->and($result['instructions'])->toHaveKey('inputs')
        ->and($result['instructions'])->toHaveKey('feedback')
        ->and($result['instructions'])->toHaveKey('rider');

    // Claims
    expect($result)->toHaveKey('claims')
        ->and($result['claims'])->toBeArray();
});

it('includes sanitized authoritative redemption details', function () {
    $voucher = issueVoucher(validVoucherInstructions(amount: 20));
    $voucher->voucherEntities()->delete();
    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 2_000,
        'disbursed_amount_minor' => 2_000,
        'remaining_balance_minor' => 0,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
        'completed_at' => now(),
    ]);
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'withdraw',
        'provider' => 'netbank',
        'provider_reference' => $voucher->code.'-09173011987-S2',
        'provider_transaction_id' => '409669715',
        'status' => 'succeeded',
        'internal_status' => 'finalized',
        'amount' => 20,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
        'settlement_rail' => 'INSTAPAY',
        'completed_at' => now(),
    ]);

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('findByCodeOrFail')
        ->once()
        ->with($voucher->code)
        ->andReturn($voucher->fresh());

    $result = (new VoucherLifecycleService($access))->showByCode($voucher->code);

    expect($result['amount'])->toBe(20.0)
        ->and($result['redemption'])->toMatchArray([
            'status' => 'succeeded',
            'amount_minor' => 2_000,
            'currency' => 'PHP',
            'provider' => 'netbank',
            'settlement_rail' => 'INSTAPAY',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number_masked' => '*******1987',
            'provider_transaction_id' => '409669715',
        ])
        ->and($result['redemption'])->not->toHaveKeys(['raw_request', 'raw_response']);
});

it('separates a completed claim from its rejected provider payout', function () {
    $voucher = issueVoucher(validVoucherInstructions(amount: 1000));
    $voucher->voucherEntities()->delete();
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'treasury.pay_code_reservation.status', 'recovery_pending');
    data_set($metadata, 'treasury.pay_code_reservation.amount_minor', 100_000);
    data_set($metadata, 'disbursement.status', 'rejected');
    data_set($metadata, 'disbursement.requires_recovery', true);
    $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'payout_rejected',
        'requested_amount_minor' => 100_000,
        'disbursed_amount_minor' => 0,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******6025',
        'completed_at' => now(),
        'failure_message' => 'AC01 (Incorrect account number)',
    ]);
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'withdraw',
        'provider' => 'netbank',
        'provider_reference' => $voucher->code.'-09707616025-S1',
        'provider_transaction_id' => '410402088',
        'status' => 'failed',
        'internal_status' => 'recovery_opened',
        'amount' => 1000,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******6025',
        'settlement_rail' => 'INSTAPAY',
        'needs_review' => false,
        'error_message' => 'AC01 (Incorrect account number)',
        'completed_at' => now(),
    ]);
    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('findByCodeOrFail')
        ->once()
        ->with($voucher->code)
        ->andReturn($voucher->fresh());

    $result = (new VoucherLifecycleService($access))->showByCode($voucher->code);

    expect($result['redemption'])->toMatchArray([
        'status' => 'failed',
        'claim_status' => 'payout_rejected',
        'payout_status' => 'failed',
        'amount_minor' => 100_000,
        'provider_transaction_id' => '410402088',
        'rejection_reason' => 'AC01 (Incorrect account number)',
        'requires_recovery' => true,
        'can_correct_destination' => true,
    ])->and($result['redemption'])->not->toHaveKeys([
        'raw_request',
        'raw_response',
        'account_number_ciphertext',
    ]);
});

it('keeps a successful payout primary after the voucher expiry date', function () {
    $voucher = issueVoucher(validVoucherInstructions(amount: 20));
    $voucher->forceFill([
        'redeemed_at' => now()->subDay(),
        'expires_at' => now()->subMinute(),
    ])->saveQuietly();

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 2_000,
        'disbursed_amount_minor' => 2_000,
        'remaining_balance_minor' => 0,
        'currency' => 'PHP',
        'completed_at' => now()->subDay(),
        'meta' => ['fully_claimed' => true],
    ]);
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'withdraw',
        'provider' => 'netbank',
        'provider_reference' => $voucher->code.'-successful-expired',
        'provider_transaction_id' => '410733956',
        'status' => 'succeeded',
        'internal_status' => 'finalized',
        'amount' => 20,
        'currency' => 'PHP',
        'completed_at' => now()->subDay(),
    ]);

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('list')->once()->with([])->andReturn([$voucher->fresh()]);

    $result = (new VoucherLifecycleService($access))->list([])[0];

    expect($result['status'])->toBe('paid')
        ->and($result['display_status'])->toBe('paid')
        ->and($result['voucher_status'])->toBe('expired')
        ->and($result['operational_status'])->toMatchArray([
            'key' => 'paid',
            'label' => 'Paid',
            'availability_key' => 'closed',
            'settlement_outcome' => 'succeeded',
            'is_terminal' => true,
            'can_claim' => false,
            'can_retry_payout' => false,
        ]);
});

it('keeps a rejected payout primary after the voucher expiry date', function () {
    $voucher = issueVoucher(validVoucherInstructions(amount: 1000));
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'treasury.pay_code_reservation.status', 'recovery_pending');
    data_set($metadata, 'disbursement.requires_recovery', true);
    $voucher->forceFill([
        'metadata' => $metadata,
        'redeemed_at' => now()->subDay(),
        'expires_at' => now()->subMinute(),
    ])->saveQuietly();

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'payout_rejected',
        'requested_amount_minor' => 100_000,
        'disbursed_amount_minor' => 0,
        'remaining_balance_minor' => 0,
        'currency' => 'PHP',
        'completed_at' => now()->subDay(),
        'failure_message' => 'AC01 (Incorrect account number)',
        'meta' => ['fully_claimed' => true],
    ]);
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'withdraw',
        'provider' => 'netbank',
        'provider_reference' => $voucher->code.'-rejected-expired',
        'status' => 'failed',
        'internal_status' => 'recovery_opened',
        'amount' => 1000,
        'currency' => 'PHP',
        'error_message' => 'AC01 (Incorrect account number)',
        'completed_at' => now()->subDay(),
    ]);

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('list')->once()->with([])->andReturn([$voucher->fresh()]);

    $result = (new VoucherLifecycleService($access))->list([])[0];

    expect($result['status'])->toBe('payout_rejected')
        ->and($result['display_status'])->toBe('payout_rejected')
        ->and($result['voucher_status'])->toBe('expired')
        ->and($result['attention']['key'])->toBe('payout_rejected')
        ->and($result['operational_status'])->toMatchArray([
            'key' => 'payout_rejected',
            'label' => 'Payout Rejected',
            'availability_key' => 'closed',
            'settlement_outcome' => 'rejected',
            'is_terminal' => true,
            'can_claim' => false,
            'can_retry_payout' => true,
        ]);
});

it('keeps expiry primary when no claim or payout exists', function () {
    $voucher = issueVoucher();
    $voucher->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('list')->once()->with([])->andReturn([$voucher->fresh()]);

    $result = (new VoucherLifecycleService($access))->list([])[0];

    expect($result['status'])->toBe('expired')
        ->and($result['display_status'])->toBe('expired')
        ->and($result['operational_status'])->toMatchArray([
            'key' => 'expired',
            'availability_key' => 'expired',
            'settlement_outcome' => 'not_applicable',
            'can_claim' => false,
        ]);
});

it('returns voucher status', function () {
    $voucher = issueVoucher();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('findOrFail')
        ->once()
        ->with((string) $voucher->id)
        ->andReturn($voucher);

    $service = new VoucherLifecycleService($access);

    $result = $service->status((string) $voucher->id);

    expect($result)->toBeArray()
        ->and($result['voucher_id'])->toBe($voucher->id)
        ->and($result['code'])->toBe($voucher->code)
        ->and($result['claimed'])->toBeFalse();
});

it('projects fully claimed voucher claims as redeemed even before redeemed_at is stamped', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 25,
        overrides: [
            'cash' => [
                'slice_mode' => 'open',
                'max_slices' => 1,
                'min_withdrawal' => 25,
            ],
        ],
    ));

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'withdrawn',
        'requested_amount_minor' => 1000,
        'disbursed_amount_minor' => 1000,
        'remaining_balance_minor' => 1500,
        'currency' => 'PHP',
        'meta' => [
            'fully_claimed' => false,
        ],
    ]);

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 2,
        'claim_type' => 'withdraw',
        'status' => 'withdrawn',
        'requested_amount_minor' => 2500,
        'disbursed_amount_minor' => 2500,
        'remaining_balance_minor' => 0,
        'currency' => 'PHP',
        'meta' => [
            'fully_claimed' => true,
        ],
    ]);

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn([$voucher->fresh()]);

    $service = new VoucherLifecycleService($access);

    $result = $service->list([]);

    expect($result[0]['status'])->toBe('redeemed')
        ->and($result[0]['display_status'])->toBe('redeemed');
});

it('cancels a voucher', function () {
    $voucher = issueVoucher();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('findOrFail')
        ->once()
        ->with((string) $voucher->id)
        ->andReturn($voucher);

    $service = new VoucherLifecycleService($access);

    $result = $service->cancel((string) $voucher->id, [
        'reason' => 'customer_requested',
    ]);

    expect($result)->toBeArray()
        ->and($result['voucher_id'])->toBe($voucher->id)
        ->and($result['status'])->toBe('cancelled')
        ->and($result['cancelled'])->toBeTrue()
        ->and($result['reason'])->toBe('customer_requested');

    expect($voucher->fresh()->state)->toBe(VoucherState::CLOSED);
});

it('marks cancelled voucher status correctly', function () {
    $voucher = issueVoucher();
    $voucher->state = VoucherState::CLOSED;
    $voucher->closed_at = now();
    $voucher->save();

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('findOrFail')
        ->once()
        ->with((string) $voucher->id)
        ->andReturn($voucher->fresh());

    $service = new VoucherLifecycleService($access);

    $result = $service->status((string) $voucher->id);

    expect($result)->toBeArray()
        ->and($result['status'])->toBe('cancelled');
});
