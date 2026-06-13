<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Exceptions\PendingConstellationOtpException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;

it('returns approval required when Paynamics payout OTP is pending during claim execution', function () {
    $voucher = issueVoucher();

    $exception = PendingConstellationOtpException::fromPayload([
        'request_id' => 'TEST-Z3EL-09173011987-S1',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
    ], [
        'success' => true,
        'data' => 'OTP successfully sent to 639171234567',
    ]);

    $executor = new class($exception) implements ClaimExecutorContract
    {
        public function __construct(
            private readonly PendingConstellationOtpException $exception,
        ) {}

        public function handle(Voucher $voucher, array $payload): mixed
        {
            throw $this->exception;
        }
    };

    $factory = new class($executor) implements ClaimExecutionFactoryContract
    {
        public function __construct(
            private readonly ClaimExecutorContract $executor,
        ) {}

        public function make(Voucher $voucher, array $payload): ClaimExecutorContract|SettlementExecutionContract
        {
            return $this->executor;
        }
    };

    $recorder = Mockery::mock(RecordVoucherClaim::class);
    $recorder->shouldNotReceive('handle');

    $result = app(SubmitPayCodeClaim::class, [
        'factory' => $factory,
        'recordVoucherClaim' => $recorder,
    ])->handle($voucher, [
        'mobile' => '639171234567',
        'bank_code' => 'GXI',
        'account_number' => '09173011987',
    ]);

    expect($result)->toBeInstanceOf(ClaimApprovalInitiationResultData::class)
        ->and($result->status)->toBe('approval_required')
        ->and($result->voucher_code)->toBe($voucher->code)
        ->and($result->requirements)->toBe(['otp'])
        ->and($result->meta)->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'TEST-Z3EL-09173011987-S1',
            'amount' => '75.00',
            'bank_account_no' => '09173011987',
            'bank_id' => 'GXI',
            'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
            'target' => 'OTP successfully sent to 639171234567',
            'otp_required' => true,
        ])
        ->and($result->messages)->toBe([
            'Payout OTP approval required.',
        ]);
});
