<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Data\Claims\ApprovalStatusData;
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

    expect($result[0]['approval'])->toBeNull();
});

it('omits stale approval summary for redeemed vouchers', function () {
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
        ->and($result[0]['approval'])->toBeNull();
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
        ->and($result['code'])->toBe($voucher->code);
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
