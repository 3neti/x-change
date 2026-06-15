<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiPaynamicsConstellation\Exceptions\PendingConstellationOtpException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use LBHurtado\XChange\Data\WithdrawalDisbursementExecutionData;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;

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

it('returns approval required when approval pipeline receives swallowed deferred Paynamics OTP result', function () {
    $voucher = issueVoucher();
    $voucher->code = 'TEST-KMHE';

    $executor = new class implements ClaimExecutorContract
    {
        public function handle(Voucher $voucher, array $payload): mixed
        {
            return new RedeemPayCodeResultData(
                voucher_code: (string) $voucher->code,
                redeemed: true,
                status: 'redeemed',
                redeemer: [],
                bank_account: [],
                inputs: [],
                disbursement: [
                    'status' => 'unknown',
                    'needs_review' => true,
                    'error' => 'Paynamics payout OTP is pending.',
                ],
                messages: [
                    'Voucher redemption succeeded.',
                ],
            );
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
        'approval' => [
            'pipeline' => true,
            'provider' => 'paynamics',
        ],
        'bank_account' => [
            'bank_code' => 'GXI',
            'account_number' => '09171234567',
        ],
    ]);

    expect($result)->toBeInstanceOf(ClaimApprovalInitiationResultData::class)
        ->and($result->status)->toBe('approval_required')
        ->and($result->voucher_code)->toBe('TEST-KMHE')
        ->and($result->requirements)->toBe(['otp'])
        ->and($result->actions)->toBe(['otp'])
        ->and($result->meta)->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'TEST-KMHE-09171234567',
            'otp_required' => true,
            'message' => 'Paynamics payout OTP is pending.',
        ])
        ->and($result->messages)->toBe([
            'Payout OTP approval required.',
        ]);
});

it('returns approval required when deferred Paynamics OTP is only recorded on voucher metadata', function () {
    $voucher = issueVoucher();
    $voucher->code = 'TEST-4BQT';

    $executor = new class implements ClaimExecutorContract
    {
        public function handle(Voucher $voucher, array $payload): mixed
        {
            $metadata = $voucher->metadata ?? [];

            data_set($metadata, 'disbursement', [
                'transaction_id' => 'TEST-4BQT-09171234567',
                'status' => 'pending',
                'error' => 'Paynamics payout OTP is pending.',
                'requires_reconciliation' => true,
            ]);

            $voucher::query()
                ->whereKey($voucher->getKey())
                ->update([
                    'metadata' => $metadata,
                ]);

            return new RedeemPayCodeResultData(
                voucher_code: (string) $voucher->code,
                redeemed: true,
                status: 'redeemed',
                redeemer: [],
                bank_account: [],
                inputs: [],
                disbursement: [
                    'status' => 'requested',
                ],
                messages: [
                    'Voucher redeemed successfully.',
                ],
            );
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
        'approval' => [
            'pipeline' => true,
            'provider' => 'paynamics',
        ],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ]);

    expect($result)->toBeInstanceOf(ClaimApprovalInitiationResultData::class)
        ->and($result->status)->toBe('approval_required')
        ->and($result->meta)->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'TEST-4BQT-09171234567',
            'otp_required' => true,
            'message' => 'Paynamics payout OTP is pending.',
        ]);
});

it('replays only Paynamics payout when approval OTP resumes an already redeemed voucher', function () {
    $voucher = issueVoucher();
    $voucher->code = 'TEST-9UB7';
    $voucher->metadata = [
        'disbursement' => [
            'transaction_id' => 'TEST-9UB7-09171234567',
            'amount' => 12.50,
            'status' => 'pending',
            'error' => 'Paynamics payout OTP is pending.',
            'settlement_rail' => 'INSTAPAY',
            'recipient_identifier' => '09171234567',
            'metadata' => [
                'bank_code' => 'GXCHPHM2XXX',
            ],
        ],
    ];
    $voucher->save();

    $executor = new class implements ClaimExecutorContract
    {
        public function handle(Voucher $voucher, array $payload): mixed
        {
            throw new RuntimeException('Failed to redeem voucher.');
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

    $disbursements = Mockery::mock(WithdrawalDisbursementExecutor::class);
    $disbursements
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function (Voucher $givenVoucher, PayoutRequestData $request, int $sliceNumber) use ($voucher): bool {
            return $givenVoucher === $voucher
                && $sliceNumber === 1
                && $request->reference === 'TEST-9UB7-09171234567'
                && $request->amount === 12.50
                && $request->account_number === '09171234567'
                && $request->bank_code === 'GXCHPHM2XXX'
                && $request->settlement_rail === 'INSTAPAY';
        })
        ->andReturn(new WithdrawalDisbursementExecutionData(
            input: PayoutRequestData::from([
                'reference' => 'TEST-9UB7-09171234567',
                'amount' => 12.50,
                'account_number' => '09171234567',
                'bank_code' => 'GXCHPHM2XXX',
                'settlement_rail' => 'INSTAPAY',
                'currency' => 'PHP',
            ]),
            response: new PayoutResultData(
                transaction_id: 'TEST-9UB7-09171234567',
                uuid: 'paynamics-uuid',
                status: PayoutStatus::PENDING,
                provider: 'paynamics',
                metadata: [],
            ),
            status: 'pending',
        ));

    $result = app(SubmitPayCodeClaim::class, [
        'factory' => $factory,
        'recordVoucherClaim' => $recorder,
        'approvalReplayDisbursements' => $disbursements,
    ])->handle($voucher, [
        'mobile' => '639171234567',
        'approval' => [
            'resume' => true,
            'provider' => 'paynamics',
            'reference_id' => 'TEST-9UB7-09171234567',
        ],
        'otp' => [
            'verified' => true,
            'code' => '969862',
        ],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ]);

    expect($result->status)->toBe('redeemed')
        ->and($result->disbursement)->toMatchArray([
            'status' => 'pending',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
            'transaction_id' => 'TEST-9UB7-09171234567',
            'gateway' => 'paynamics',
        ]);
});
