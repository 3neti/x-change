<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\DeferredOtpResolver;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Lifecycle\Output\LifecycleOutputContract;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunContext;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleClaimSubmitter;
use LBHurtado\XChange\Support\Claim\UseDeferredPaynamicsOtpResolver;
use LBHurtado\XChange\Tests\Fakes\FakePayoutProvider;

function lifecycleClaimSubmitterPaynamicsProvider(): ConstellationPayoutProvider
{
    return new class extends ConstellationPayoutProvider
    {
        public function __construct() {}

        public function disburse(PayoutRequestData $request): PayoutResultData
        {
            return new PayoutResultData(
                transaction_id: $request->reference,
                uuid: 'test-uuid',
                status: PayoutStatus::PENDING,
                provider: 'paynamics',
                metadata: [],
            );
        }

        public function checkStatus(string $transactionId): PayoutResultData
        {
            return new PayoutResultData(
                transaction_id: $transactionId,
                uuid: 'test-uuid',
                status: PayoutStatus::PENDING,
                provider: 'paynamics',
                metadata: [],
            );
        }

        public function getRailFee(SettlementRail $rail): int
        {
            return 0;
        }
    };
}

function lifecycleClaimSubmitterContext(bool $json): ScenarioRunContext
{
    $output = new class($json) implements LifecycleOutputContract
    {
        public function __construct(
            private readonly bool $json,
        ) {}

        public function line(string $message): void {}

        public function info(string $message): void {}

        public function warn(string $message): void {}

        public function error(string $message): void {}

        public function isJson(): bool
        {
            return $this->json;
        }

        public function acceptPending(): bool
        {
            return false;
        }
    };

    return new ScenarioRunContext(
        output: $output,
        scenarioKey: 'test',
        scenario: [],
        issuer: new class extends Model {},
        generated: new class
        {
            public function toArray(): array
            {
                return [];
            }
        },
        voucher: new Voucher,
        attempts: [],
        baseClaimMobile: '639171234567',
        estimate: [],
        idempotencyKey: 'test-idempotency',
        readiness: Mockery::mock(SettlementEnvelopeReadinessContract::class),
    );
}

function lifecycleClaimSubmitterApprovalPipelineContext(): ScenarioRunContext
{
    return new ScenarioRunContext(
        output: new class implements LifecycleOutputContract
        {
            public function line(string $message): void {}

            public function info(string $message): void {}

            public function warn(string $message): void {}

            public function error(string $message): void {}

            public function isJson(): bool
            {
                return false;
            }

            public function acceptPending(): bool
            {
                return false;
            }
        },
        scenarioKey: 'test',
        scenario: [
            '_runtime' => [
                'approval_pipeline' => true,
            ],
        ],
        issuer: new class extends Model {},
        generated: new class
        {
            public function toArray(): array
            {
                return [];
            }
        },
        voucher: new Voucher,
        attempts: [],
        baseClaimMobile: '639171234567',
        estimate: [],
        idempotencyKey: 'test-idempotency',
        readiness: Mockery::mock(SettlementEnvelopeReadinessContract::class),
    );
}

it('uses deferred Paynamics OTP resolver for JSON lifecycle claim submission', function () {
    app()->instance(PayoutProvider::class, lifecycleClaimSubmitterPaynamicsProvider());

    $voucher = new Voucher;
    $voucher->code = 'TEST-JSON';

    $executor = new class implements ClaimExecutorContract
    {
        public string $resolverClass = '';

        public function handle(Voucher $voucher, array $payload): mixed
        {
            $this->resolverClass = app(ConstellationOtpResolver::class)::class;

            return new WithdrawPayCodeResultData(
                voucher_code: (string) $voucher->code,
                withdrawn: true,
                status: 'withdrawn',
                requested_amount: 10,
                disbursed_amount: 10,
                currency: 'PHP',
                remaining_balance: 0,
                slice_number: null,
                remaining_slices: null,
                slice_mode: null,
                redeemer: [],
                bank_account: [],
                disbursement: [],
                messages: ['ok'],
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

    $submit = new SubmitPayCodeClaim(
        factory: $factory,
        recordVoucherClaim: Mockery::mock(RecordVoucherClaim::class)->shouldIgnoreMissing(),
    );

    app(LifecycleClaimSubmitter::class, [
        'submitPayCodeClaim' => $submit,
        'deferredOtpResolver' => app(UseDeferredPaynamicsOtpResolver::class),
    ])->submit(
        lifecycleClaimSubmitterContext(json: true),
        $voucher,
        ['mobile' => '639171234567'],
    );

    expect($executor->resolverClass)->toBe(DeferredOtpResolver::class);
});

it('uses deferred Paynamics OTP resolver when approval pipeline is enabled', function () {
    app()->instance(PayoutProvider::class, lifecycleClaimSubmitterPaynamicsProvider());

    $voucher = new Voucher;
    $voucher->code = 'TEST-APPROVAL';

    $executor = new class implements ClaimExecutorContract
    {
        public string $resolverClass = '';

        public function handle(Voucher $voucher, array $payload): mixed
        {
            $this->resolverClass = app(ConstellationOtpResolver::class)::class;

            return new WithdrawPayCodeResultData(
                voucher_code: (string) $voucher->code,
                withdrawn: true,
                status: 'withdrawn',
                requested_amount: 10,
                disbursed_amount: 10,
                currency: 'PHP',
                remaining_balance: 0,
                slice_number: null,
                remaining_slices: null,
                slice_mode: null,
                redeemer: [],
                bank_account: [],
                disbursement: [],
                messages: ['ok'],
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

    $submit = new SubmitPayCodeClaim(
        factory: $factory,
        recordVoucherClaim: Mockery::mock(RecordVoucherClaim::class)->shouldIgnoreMissing(),
    );

    $context = lifecycleClaimSubmitterApprovalPipelineContext();

    app(LifecycleClaimSubmitter::class, [
        'submitPayCodeClaim' => $submit,
        'deferredOtpResolver' => app(UseDeferredPaynamicsOtpResolver::class),
    ])->submit(
        $context,
        $voucher,
        ['mobile' => '639171234567'],
    );

    expect($executor->resolverClass)->toBe(DeferredOtpResolver::class);
});

it('does not inject Paynamics approval metadata when approval pipeline uses another provider', function () {
    app()->instance(PayoutProvider::class, new FakePayoutProvider);

    $voucher = new Voucher;
    $voucher->code = 'TEST-NETBANK';

    $executor = new class implements ClaimExecutorContract
    {
        /**
         * @var array<string, mixed>
         */
        public array $payload = [];

        public function handle(Voucher $voucher, array $payload): mixed
        {
            $this->payload = $payload;

            return new WithdrawPayCodeResultData(
                voucher_code: (string) $voucher->code,
                withdrawn: true,
                status: 'withdrawn',
                requested_amount: 10,
                disbursed_amount: 10,
                currency: 'PHP',
                remaining_balance: 0,
                slice_number: null,
                remaining_slices: null,
                slice_mode: null,
                redeemer: [],
                bank_account: [],
                disbursement: [],
                messages: ['ok'],
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

    $submit = new SubmitPayCodeClaim(
        factory: $factory,
        recordVoucherClaim: Mockery::mock(RecordVoucherClaim::class)->shouldIgnoreMissing(),
    );

    app(LifecycleClaimSubmitter::class, [
        'submitPayCodeClaim' => $submit,
        'deferredOtpResolver' => app(UseDeferredPaynamicsOtpResolver::class),
    ])->submit(
        lifecycleClaimSubmitterApprovalPipelineContext(),
        $voucher,
        ['mobile' => '639171234567'],
    );

    expect(data_get($executor->payload, 'approval.provider'))->toBeNull()
        ->and(data_get($executor->payload, 'approval.pipeline'))->toBeNull();
});
