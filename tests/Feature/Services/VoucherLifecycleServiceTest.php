<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Data\Claims\ApprovalStatusData;
use LBHurtado\XChange\Enums\ClaimEvidenceKind;
use LBHurtado\XChange\Enums\ClaimEvidenceStatus;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimEvidence;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Models\VoucherSliceExecution;
use LBHurtado\XChange\Models\VoucherSliceExecutionItem;
use LBHurtado\XChange\Services\VoucherAccessService;
use LBHurtado\XChange\Services\VoucherLifecycleService;

function canonicalLifecyclePayableVoucher(string $code): Voucher
{
    $issuer = actingAsTestUser();

    return issueVoucher(validVoucherInstructions(overrides: [
        'voucher_type' => 'payable',
        'target_amount' => 100,
        'metadata' => [
            'flow_type' => 'collectible',
            'issuer_id' => (string) $issuer->getKey(),
            'collection_wallet_id' => (string) $issuer->wallet()->where('slug', 'platform')->sole()->getKey(),
            'custom' => ['external_reference' => $code.'-ORDER'],
        ],
    ]));
}

function canonicalLifecyclePaymentAttempt(Voucher $voucher): PaymentAttempt
{
    $key = (string) str()->uuid();

    return PaymentAttempt::query()->create([
        'voucher_id' => $voucher->getKey(),
        'provider_code' => 'netbank',
        'expected_amount_minor' => 10000,
        'currency' => 'PHP',
        'status' => PaymentAttemptStatus::AwaitingPayment,
        'session_key_hash' => hash('sha256', 'session-'.$key),
        'idempotency_key_hash' => hash('sha256', 'idempotency-'.$key),
        'idempotency_fingerprint' => hash('sha256', 'fingerprint-'.$key),
    ]);
}

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

it('projects a sanitized claim summary for redeemed cockpit surfaces', function () {
    $voucher = issueVoucher();
    $voucher->forceFill([
        'redeemed_at' => Carbon::parse('2026-08-31 09:45:00', 'Asia/Manila'),
    ])->save();
    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'claim',
        'status' => 'paid',
        'requested_amount_minor' => 2500,
        'disbursed_amount_minor' => 2500,
        'remaining_balance_minor' => 0,
        'currency' => 'PHP',
        'claimer_mobile' => '09173011987',
        'reference' => 'CLAIM-REF-001',
        'completed_at' => Carbon::parse('2026-08-31 09:46:00', 'Asia/Manila'),
    ]);
    VoucherClaimEvidence::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_claim_id' => $claim->getKey(),
        'requirement_key' => 'location',
        'kind' => ClaimEvidenceKind::Location,
        'status' => ClaimEvidenceStatus::Captured,
        'summary' => 'Makati counter',
        'captured_at' => Carbon::parse('2026-08-31 09:46:00', 'Asia/Manila'),
    ]);

    $detail = app(VoucherLifecycleService::class)->show((string) $voucher->getKey());

    expect($detail['claim_summary'])->toMatchArray([
        'schema' => 'x-change.cockpit.pay-code-claim-summary.v1',
        'status' => 'paid',
        'claimed_at' => '2026-08-31T09:46:00+00:00',
        'claimed_by_label' => '•••• 1987',
        'claimed_mobile_masked' => '•••• 1987',
        'amount_minor' => 2500,
        'currency' => 'PHP',
        'location_label' => 'Makati counter',
        'evidence_count' => 1,
        'latest_claim_reference' => 'CLAIM-REF-001',
    ])
        ->and(json_encode($detail['claim_summary']))->not->toContain('09173011987');
});

it('projects canonical collection facts in lifecycle summary and detail', function (string $consumerStatus): void {
    $voucher = canonicalLifecyclePayableVoucher('CANONICAL-'.strtoupper($consumerStatus));

    if ($consumerStatus === 'processing') {
        canonicalLifecyclePaymentAttempt($voucher);
    }

    if ($consumerStatus === 'paid') {
        VoucherCollection::query()->create([
            'voucher_id' => $voucher->getKey(),
            'collection_number' => 1,
            'status' => 'collected',
            'requested_amount_minor' => 10000,
            'collected_amount_minor' => 10000,
            'currency' => 'PHP',
            'provider' => 'netbank',
            'provider_reference' => 'canonical-'.$voucher->code,
            'provider_transaction_id' => 'canonical-'.$voucher->code,
            'idempotency_key' => 'canonical-'.$voucher->code,
            'completed_at' => now(),
        ]);
    }

    $service = app(VoucherLifecycleService::class);
    $summary = collect($service->list())->firstWhere('id', $voucher->getKey());
    $detail = $service->show((string) $voucher->getKey());

    foreach ([$summary, $detail] as $facts) {
        expect($facts)
            ->toBeArray()
            ->and($facts['external_reference'])->toBe('CANONICAL-'.strtoupper($consumerStatus).'-ORDER')
            ->and($facts['consumer_status'])->toBe($consumerStatus)
            ->and($facts['collection'])->toMatchArray([
                'currency' => 'PHP',
                'target_amount_minor' => 10000,
                'collected_total_minor' => $consumerStatus === 'paid' ? 10000 : 0,
                'remaining_to_collect_minor' => $consumerStatus === 'paid' ? 0 : 10000,
                'is_fully_collected' => $consumerStatus === 'paid',
                'is_overpaid' => false,
                'overpaid_amount_minor' => 0,
            ]);
    }
})->with(['payable', 'processing', 'paid']);

it('projects null collection facts for a non-collectible voucher', function (): void {
    $voucher = issueVoucher();
    $detail = app(VoucherLifecycleService::class)->show((string) $voucher->getKey());

    expect($detail['consumer_status'])->toBeNull()
        ->and($detail['collection'])->toBeNull()
        ->and($detail['external_reference'])->toBeNull();
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

it('keeps a successful first slice partially claimed and claimable', function () {
    $voucher = issueVoucher(validVoucherInstructions(amount: 100));
    $plan = app(VoucherSlicePlanFactory::class)->equal(10_000, 'PHP', 2);
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'instructions.slice_plan', $plan->canonicalArray());
    $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 5_000,
        'disbursed_amount_minor' => 5_000,
        'remaining_balance_minor' => 5_000,
        'currency' => 'PHP',
        'completed_at' => now(),
        'meta' => ['fully_claimed' => false],
    ]);
    $reference = (string) Str::ulid();
    $execution = VoucherSliceExecution::query()->create([
        'reference' => $reference,
        'voucher_id' => $voucher->getKey(),
        'voucher_claim_id' => $claim->getKey(),
        'plan_fingerprint' => $plan->hash(),
        'idempotency_key_hash' => hash('sha256', $reference),
        'request_fingerprint' => hash('sha256', 'request-'.$reference),
        'provider_operation_reference' => 'slice-'.$reference,
        'claim_number' => 1,
        'status' => 'succeeded',
        'amount_minor' => 5_000,
        'currency' => 'PHP',
        'reserved_at' => now(),
        'settled_at' => now(),
    ]);
    VoucherSliceExecutionItem::query()->create([
        'execution_id' => $execution->getKey(),
        'voucher_id' => $voucher->getKey(),
        'slice_id' => 'slice_1',
        'label' => 'Slice 1',
        'sequence' => 1,
        'amount_minor' => 5_000,
        'status' => 'consumed',
        'reserved_at' => now(),
        'consumed_at' => now(),
    ]);
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'withdraw',
        'provider' => 'netbank',
        'provider_reference' => $reference,
        'provider_transaction_id' => 'slice-first-paid',
        'status' => 'succeeded',
        'internal_status' => 'finalized',
        'amount' => 50,
        'currency' => 'PHP',
        'completed_at' => now(),
    ]);

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('list')->once()->with([])->andReturn([$voucher->fresh()]);
    $result = (new VoucherLifecycleService($access))->list([])[0];

    expect($result['status'])->toBe('partially_claimed')
        ->and($result['operational_status'])->toMatchArray([
            'label' => 'Partially Claimed',
            'availability_label' => 'Claimable',
            'settlement_outcome' => 'succeeded',
            'is_terminal' => false,
            'can_claim' => true,
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

    expect($voucher->fresh()->state)->toBe(VoucherState::CANCELLED);

    $readService = new VoucherLifecycleService(new VoucherAccessService);
    $projections = [
        collect($readService->list())->firstWhere('code', $voucher->code),
        $readService->showByCode($voucher->code),
        $readService->status((string) $voucher->id),
    ];

    foreach ($projections as $projection) {
        expect($projection)->toMatchArray([
            'status' => 'cancelled',
            'display_status' => 'cancelled',
            'voucher_status' => 'cancelled',
        ])->and($projection['operational_status'])->toMatchArray([
            'key' => 'cancelled',
            'is_terminal' => true,
            'can_claim' => false,
        ]);
    }

    expect($readService->showByCode($voucher->code)['claims'])->toBe([])
        ->and(VoucherClaim::query()->where('voucher_id', $voucher->id)->count())->toBe(0)
        ->and(DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->count())->toBe(0);
});

it('projects cancelled vouchers as terminal and not claimable', function () {
    $voucher = issueVoucher();
    $voucher->state = VoucherState::CANCELLED;
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
        ->and($result['status'])->toBe('cancelled')
        ->and($result['display_status'])->toBe('cancelled')
        ->and($result['voucher_status'])->toBe('cancelled')
        ->and($result['operational_status'])->toMatchArray([
            'key' => 'cancelled',
            'label' => 'Cancelled',
            'tone' => 'neutral',
            'availability_key' => 'cancelled',
            'availability_label' => 'Cancelled',
            'settlement_outcome' => 'not_applicable',
            'is_terminal' => true,
            'can_claim' => false,
            'can_retry_payout' => false,
        ]);
});

it('keeps an unclaimed closed voucher distinct from cancellation', function () {
    $voucher = issueVoucher();
    $voucher->update([
        'state' => VoucherState::CLOSED,
        'closed_at' => now(),
    ]);

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('findOrFail')
        ->once()
        ->with((string) $voucher->id)
        ->andReturn($voucher->fresh());

    $result = (new VoucherLifecycleService($access))->status((string) $voucher->id);

    expect($result)->toMatchArray([
        'status' => 'closed',
        'display_status' => 'closed',
        'voucher_status' => 'closed',
    ])->and($result['operational_status'])->toMatchArray([
        'key' => 'closed',
        'label' => 'Closed',
        'is_terminal' => true,
        'can_claim' => false,
    ]);
});
