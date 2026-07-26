<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\EmiCore\Contracts\ProviderBalanceReader;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceObservationData;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryInventoryOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\Treasury\MissingDisbursementPostingRepairService;
use LBHurtado\XChange\Services\Treasury\TreasuryInventoryRegistrationService;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\User;

it('repairs an exact authoritative deficit once without including configured fees', function () {
    $fixture = missingDisbursementPostingRepairFixture();
    $arguments = [
        '--connection' => 'netbank-primary',
        '--json' => true,
    ];

    $dryRunExit = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        $arguments,
    );
    $dryRunOutput = Artisan::output();
    $dryRun = json_decode($dryRunOutput, true);

    expect($dryRunExit)->toBe(Command::SUCCESS, $dryRunOutput)
        ->and($dryRun['status'])->toBe('dry_run')
        ->and($dryRun['committed'])->toBeFalse()
        ->and($dryRun['deficit_minor'])->toBe(4_500)
        ->and($dryRun['principal_amount_minor'])->toBe(4_500)
        ->and($dryRun['candidate_count'])->toBe(3)
        ->and($dryRun['reconciliation_ids'])->toBe($fixture['reconciliation_ids'])
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe(1)
        ->and($dryRunOutput)->not->toContain('provider-transaction-secret')
        ->and($dryRunOutput)->not->toContain('09173011987');

    $commitExit = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        [
            ...$arguments,
            '--reconciliation' => array_reverse($fixture['reconciliation_ids']),
            '--commit' => true,
        ],
    );
    $commitOutput = Artisan::output();
    $committed = json_decode($commitOutput, true);
    $inventory = app(TreasuryInventoryPositionReadModelContract::class)
        ->find('inventory:netbank:vca-cash');
    $legacyUnattributed = collect(
        app(TreasuryPositionReadModelContract::class)
            ->forPrincipal('principal:system'),
    )->sole(
        fn ($position): bool => $position->purpose
            === TreasuryPositionPurpose::LegacyUnattributed,
    );

    expect($commitExit)->toBe(Command::SUCCESS, $commitOutput)
        ->and($committed['status'])->toBe('repaired')
        ->and($committed['committed'])->toBeTrue()
        ->and($committed['repaired_count'])->toBe(3)
        ->and($committed['deficit_minor'])->toBe(0)
        ->and($inventory?->balanceMinor)->toBe(5_500)
        ->and($legacyUnattributed->balanceMinor)->toBe(5_500)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(4)
        ->and(TreasuryPositionOperation::query()->count())->toBe(4)
        ->and(TreasuryInventoryOperation::query()
            ->where('operation_type', TreasuryInventoryOperationType::Adjustment)
            ->where('metadata->source', 'missing_disbursement_posting_repair')
            ->where('metadata->configured_fee_excluded', true)
            ->count())->toBe(3)
        ->and(TreasuryPositionOperation::query()
            ->where('operation_type', TreasuryPositionOperationType::Derecognition)
            ->where('metadata->source', 'missing_disbursement_posting_repair')
            ->where('metadata->configured_fee_excluded', true)
            ->count())->toBe(3);

    $replayExit = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        [
            ...$arguments,
            '--reconciliation' => $fixture['reconciliation_ids'],
            '--commit' => true,
        ],
    );
    $replayOutput = Artisan::output();
    $replayed = json_decode($replayOutput, true);

    expect($replayExit)->toBe(Command::SUCCESS, $replayOutput)
        ->and($replayed['status'])->toBe('already_repaired')
        ->and(TreasuryInventoryOperation::query()->count())->toBe(4)
        ->and(TreasuryPositionOperation::query()->count())->toBe(4);
});

it('requires explicit IDs and an exact provider deficit before committing', function () {
    $fixture = missingDisbursementPostingRepairFixture();

    $missingIdsExit = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        [
            '--connection' => 'netbank-primary',
            '--commit' => true,
            '--json' => true,
        ],
    );
    $missingIds = json_decode(Artisan::output(), true);

    expect($missingIdsExit)->toBe(Command::FAILURE)
        ->and($missingIds['status'])->toBe('rejected')
        ->and($missingIds['message'])->toContain('requires explicit reconciliation IDs')
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe(1);

    $fixture['reader']->amountMinor = 5_400;
    $mismatchExit = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        [
            '--connection' => 'netbank-primary',
            '--reconciliation' => $fixture['reconciliation_ids'],
            '--commit' => true,
            '--json' => true,
        ],
    );
    $mismatch = json_decode(Artisan::output(), true);

    expect($mismatchExit)->toBe(Command::FAILURE)
        ->and($mismatch['status'])->toBe('rejected')
        ->and($mismatch['message'])->toContain('does not exactly match')
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe(1);
});

it('ignores a fully posted disbursement owned by a non-system principal', function () {
    $fixture = missingDisbursementPostingRepairFixture();
    addPreviouslyPostedDisbursement($fixture['reader']);

    $exitCode = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        [
            '--connection' => 'netbank-primary',
            '--json' => true,
        ],
    );
    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($exitCode)->toBe(Command::SUCCESS, $output)
        ->and($result['status'])->toBe('dry_run')
        ->and($result['deficit_minor'])->toBe(4_500)
        ->and($result['principal_amount_minor'])->toBe(4_500)
        ->and($result['reconciliation_ids'])->toBe($fixture['reconciliation_ids'])
        ->and(TreasuryInventoryOperation::query()->count())->toBe(2)
        ->and(TreasuryPositionOperation::query()->count())->toBe(2);
});

it('ignores a pre-opening disbursement synchronized after the opening baseline', function () {
    $fixture = missingDisbursementPostingRepairFixture();
    addPreOpeningSynchronizedDisbursement();
    $baseline = TreasuryInventoryOperation::query()
        ->where('metadata->source', 'provider_balance_reconciliation')
        ->latest('id')
        ->firstOrFail();
    $preOpening = DisbursementReconciliation::query()
        ->where(
            'provider_transaction_id',
            'pre-opening-provider-transaction',
        )
        ->firstOrFail();

    expect(CarbonImmutable::parse($preOpening->raw_response['date']))
        ->toBeLessThan(CarbonImmutable::instance($baseline->created_at));

    $exitCode = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        [
            '--connection' => 'netbank-primary',
            '--json' => true,
        ],
    );
    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($exitCode)->toBe(Command::SUCCESS, $output)
        ->and($result['status'])->toBe('dry_run')
        ->and($result['deficit_minor'])->toBe(4_500)
        ->and($result['principal_amount_minor'])->toBe(4_500)
        ->and($result['reconciliation_ids'])->toBe($fixture['reconciliation_ids']);
});

it('repairs from Account Funding Reserve after opening capital is allocated', function () {
    $fixture = missingDisbursementPostingRepairFixture();
    capitalizeRepairFixture();
    $arguments = [
        '--connection' => 'netbank-primary',
        '--json' => true,
    ];

    $dryRunExit = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        $arguments,
    );
    $dryRunOutput = Artisan::output();
    $dryRun = json_decode($dryRunOutput, true);

    expect($dryRunExit)->toBe(Command::SUCCESS, $dryRunOutput)
        ->and($dryRun['status'])->toBe('dry_run')
        ->and($dryRun['principal_amount_minor'])->toBe(4_500);

    $commitExit = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        [
            ...$arguments,
            '--reconciliation' => $fixture['reconciliation_ids'],
            '--commit' => true,
        ],
    );
    $commitOutput = Artisan::output();
    $committed = json_decode($commitOutput, true);
    $reserve = collect(
        app(TreasuryPositionReadModelContract::class)
            ->forPrincipal('principal:system'),
    )->sole(
        fn ($position): bool => $position->purpose
            === TreasuryPositionPurpose::AccountFundingReserve,
    );
    $reserveId = TreasuryPosition::query()
        ->where('position_reference', $reserve->positionReference)
        ->value('id');
    $payCodeReserve = collect(
        app(TreasuryPositionReadModelContract::class)
            ->forPrincipal('principal:system'),
    )->sole(
        fn ($position): bool => $position->purpose
            === TreasuryPositionPurpose::PayCodeReserve,
    );
    $payCodeReserveId = TreasuryPosition::query()
        ->where('position_reference', $payCodeReserve->positionReference)
        ->value('id');

    expect($commitExit)->toBe(Command::SUCCESS, $commitOutput)
        ->and($committed['status'])->toBe('repaired')
        ->and($reserve->balanceMinor)->toBe(5_500)
        ->and($payCodeReserve->balanceMinor)->toBe(0)
        ->and(TreasuryPositionOperation::query()
            ->where('operation_type', TreasuryPositionOperationType::Reservation)
            ->where('source_position_id', $reserveId)
            ->where('destination_position_id', $payCodeReserveId)
            ->count())->toBe(3)
        ->and(TreasuryPositionOperation::query()
            ->where('operation_type', TreasuryPositionOperationType::Derecognition)
            ->where('source_position_id', $payCodeReserveId)
            ->count())->toBe(3);

    $replayExit = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        [
            ...$arguments,
            '--reconciliation' => $fixture['reconciliation_ids'],
            '--commit' => true,
        ],
    );
    $replayOutput = Artisan::output();
    $replayed = json_decode($replayOutput, true);

    expect($replayExit)->toBe(Command::SUCCESS, $replayOutput)
        ->and($replayed['status'])->toBe('already_repaired')
        ->and(TreasuryPositionOperation::query()
            ->where('operation_type', TreasuryPositionOperationType::Reservation)
            ->where('source_position_id', $reserveId)
            ->count())->toBe(3)
        ->and(TreasuryPositionOperation::query()
            ->where('operation_type', TreasuryPositionOperationType::Derecognition)
            ->where('source_position_id', $payCodeReserveId)
            ->count())->toBe(3);
});

it('rolls back the position debit when the inventory posting fails', function () {
    $fixture = missingDisbursementPostingRepairFixture();
    $realInventoryOperations = app(TreasuryInventoryOperationContract::class);
    $failingInventoryOperations = new class($realInventoryOperations) implements TreasuryInventoryOperationContract
    {
        public function __construct(
            private readonly TreasuryInventoryOperationContract $operations,
        ) {}

        public function registerInventory(
            TreasuryInventoryData $inventory,
        ): TreasuryInventoryData {
            return $this->operations->registerInventory($inventory);
        }

        public function recognize(
            TreasuryInventoryRecognitionData $recognition,
        ): TreasuryInventoryRecognitionData {
            return $this->operations->recognize($recognition);
        }

        public function reclassify(
            TreasuryInventoryReclassificationData $reclassification,
        ): TreasuryInventoryReclassificationData {
            return $this->operations->reclassify($reclassification);
        }

        public function adjust(
            TreasuryInventoryAdjustmentData $adjustment,
        ): TreasuryInventoryAdjustmentData {
            throw new RuntimeException('Simulated inventory write failure.');
        }

        public function reverse(
            TreasuryOperationReversalData $reversal,
        ): TreasuryOperationReversalData {
            return $this->operations->reverse($reversal);
        }
    };
    app()->instance(
        TreasuryInventoryOperationContract::class,
        $failingInventoryOperations,
    );
    app()->forgetInstance(MissingDisbursementPostingRepairService::class);

    $exitCode = Artisan::call(
        'x-change:treasury:repair-missing-disbursement-postings',
        [
            '--connection' => 'netbank-primary',
            '--reconciliation' => $fixture['reconciliation_ids'],
            '--commit' => true,
            '--json' => true,
        ],
    );
    $output = Artisan::output();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output)->toContain('could not be completed safely')
        ->and($output)->not->toContain('Simulated inventory write failure')
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe(1)
        ->and(app(TreasuryInventoryPositionReadModelContract::class)
            ->find('inventory:netbank:vca-cash')?->balanceMinor)->toBe(10_000);
});

/**
 * @return array{
 *     reader: ProviderBalanceReader&object,
 *     reconciliation_ids: list<int>
 * }
 */
function missingDisbursementPostingRepairFixture(): array
{
    Carbon::setTestNow('2026-07-25T08:00:00+08:00');
    $system = enableNetbankTreasuryForTests();
    config()->set('x-change.treasury.legal_profile', 'treasury-settlement-ph-v1');
    config()->set('x-change.treasury.legal_profile_version', '2026-07-24.1');
    config()->set('x-change.treasury.principal_reference', 'principal:system');
    config()->set(
        'x-change.treasury.system_mandate_reference',
        'mandate:system:treasury',
    );
    $reader = new class implements ProviderBalanceReader
    {
        public int $amountMinor = 10_000;

        public DateTimeImmutable $observedAt;

        public function __construct()
        {
            $this->observedAt = new DateTimeImmutable(
                '2026-07-25T08:00:00+08:00',
            );
        }

        public function providerCode(): string
        {
            return 'netbank';
        }

        public function readBalance(
            ProviderBalanceRequestData $request,
        ): ProviderBalanceObservationData {
            return new ProviderBalanceObservationData(
                provider: 'netbank',
                connectionReference: $request->connectionReference,
                settlementResourceReference: $request->settlementResourceReference,
                amountMinor: $this->amountMinor,
                currency: $request->currency,
                observedAt: $this->observedAt,
                evidenceReference: 'netbank-balance:test',
            );
        }
    };
    $service = new TreasuryOpeningBalanceReconciliationService(
        app(TreasuryPreflightService::class),
        app(TreasuryProvisioningService::class),
        app(TreasuryInventoryOperationContract::class),
        app(TreasuryInventoryRegistrationService::class),
        app(TreasuryInventoryPositionReadModelContract::class),
        app(TreasuryPositionOperationContract::class),
        app(TreasuryPositionReadModelContract::class),
        [$reader],
    );
    app()->instance(TreasuryOpeningBalanceReconciliationService::class, $service);

    $opening = $service->reconcile(['netbank-primary']);

    if (! $opening->passes()) {
        throw new RuntimeException('Repair fixture could not establish its opening balance.');
    }

    $evidenceAt = new DateTimeImmutable('2026-07-25T08:05:00+08:00');
    $reconciliationIds = [];

    foreach (range(1, 3) as $index) {
        $transactionId = 'provider-transaction-secret-'.$index;
        $voucher = new Voucher([
            'code' => 'REPAIR-'.$index.'-'.str()->upper(str()->random(6)),
            'metadata' => [
                'disbursement' => [
                    'amount' => 15.00,
                    'currency' => 'PHP',
                    'gateway' => 'netbank',
                    'transaction_id' => $transactionId,
                    'fee_amount' => 1_000,
                ],
            ],
        ]);
        $voucher->owner()->associate($system);
        $voucher->save();
        $reconciliation = DisbursementReconciliation::query()->create([
            'voucher_id' => $voucher->getKey(),
            'voucher_code' => $voucher->code,
            'claim_type' => 'redeem',
            'provider' => 'netbank',
            'provider_reference' => 'provider-reference-'.$index,
            'provider_transaction_id' => $transactionId,
            'status' => 'succeeded',
            'internal_status' => 'matched',
            'amount' => 15.00,
            'currency' => 'PHP',
            'account_number_masked' => '09173011987',
            'settlement_rail' => 'INSTAPAY',
            'needs_review' => false,
            'attempted_at' => $evidenceAt,
            'completed_at' => $evidenceAt,
            'last_checked_at' => $evidenceAt,
            'raw_response' => [
                'transaction_id' => $transactionId,
                'status' => 'Settled',
                'amount' => [
                    'cur' => 'PHP',
                    'num' => 1_500,
                ],
                'date' => $evidenceAt->format(DATE_RFC3339),
                'account_number' => '09173011987',
            ],
        ]);
        $reconciliationIds[] = (int) $reconciliation->getKey();
    }

    $reader->amountMinor = 5_500;
    $reader->observedAt = new DateTimeImmutable(
        '2026-07-25T08:06:00+08:00',
    );
    app()->forgetInstance(MissingDisbursementPostingRepairService::class);

    return [
        'reader' => $reader,
        'reconciliation_ids' => $reconciliationIds,
    ];
}

/**
 * @param  ProviderBalanceReader&object  $reader
 */
function addPreviouslyPostedDisbursement(
    ProviderBalanceReader $reader,
): void {
    $owner = User::query()->create([
        'name' => 'Existing Disbursement Owner',
        'email' => 'existing-disbursement+'.str()->uuid().'@example.com',
        'password' => 'not-a-login-credential',
    ]);
    $voucher = new Voucher([
        'code' => 'POSTED-'.str()->upper(str()->random(6)),
        'metadata' => [],
    ]);
    $voucher->owner()->associate($owner);
    $voucher->save();
    $reconciliation = DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'redeem',
        'provider' => 'netbank',
        'provider_reference' => 'already-posted-provider-reference',
        'provider_transaction_id' => 'already-posted-provider-transaction',
        'status' => 'succeeded',
        'internal_status' => 'matched',
        'amount' => 12.50,
        'currency' => 'PHP',
        'needs_review' => false,
        'completed_at' => new DateTimeImmutable(
            '2026-07-25T08:04:00+08:00',
        ),
    ]);
    $legacyUnattributed = collect(
        app(TreasuryPositionReadModelContract::class)
            ->forPrincipal('principal:system'),
    )->sole(
        fn ($position): bool => $position->purpose
            === TreasuryPositionPurpose::LegacyUnattributed,
    );
    $metadata = [
        'disbursement_reconciliation_id' => $reconciliation->getKey(),
    ];
    $externalReference = 'netbank:already-posted-provider-transaction';

    app(TreasuryPositionOperationContract::class)->derecognize(
        new TreasuryPositionDerecognitionData(
            operationReference: 'pay-code-position-derecognition:already-posted',
            sourcePositionReference: $legacyUnattributed->positionReference,
            amountMinor: 1_250,
            currency: 'PHP',
            idempotencyKey: 'pay-code-position-derecognition-key:already-posted',
            externalReference: $externalReference,
            metadata: $metadata,
        ),
    );
    app(TreasuryInventoryOperationContract::class)->adjust(
        new TreasuryInventoryAdjustmentData(
            operationReference: 'pay-code-inventory-outflow:already-posted',
            inventoryReference: 'inventory:netbank:vca-cash',
            deltaAmountMinor: -1_250,
            currency: 'PHP',
            status: 'requested',
            idempotencyKey: 'pay-code-inventory-outflow-key:already-posted',
            effectiveAt: '2026-07-25T08:04:00+08:00',
            externalReference: $externalReference,
            metadata: $metadata,
        ),
    );
    $reader->amountMinor = 4_250;
}

function addPreOpeningSynchronizedDisbursement(): void
{
    $system = app(SystemUserResolverContract::class)->resolve();
    $providerObservedAt = new DateTimeImmutable(
        '2026-07-24T23:55:00+08:00',
    );
    $synchronizedAt = new DateTimeImmutable(
        '2026-07-25T08:10:00+08:00',
    );
    $transactionId = 'pre-opening-provider-transaction';
    $voucher = new Voucher([
        'code' => 'PRE-OPENING-'.str()->upper(str()->random(6)),
        'metadata' => [
            'disbursement' => [
                'amount' => 12.50,
                'currency' => 'PHP',
                'gateway' => 'netbank',
                'transaction_id' => $transactionId,
            ],
        ],
    ]);
    $voucher->owner()->associate($system);
    $voucher->save();

    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'redeem',
        'provider' => 'netbank',
        'provider_reference' => 'pre-opening-provider-reference',
        'provider_transaction_id' => $transactionId,
        'status' => 'succeeded',
        'internal_status' => 'recorded',
        'amount' => 12.50,
        'currency' => 'PHP',
        'needs_review' => false,
        'attempted_at' => $providerObservedAt,
        'completed_at' => $synchronizedAt,
        'last_checked_at' => $synchronizedAt,
        'raw_response' => [
            'transaction_id' => $transactionId,
            'status' => 'Settled',
            'amount' => [
                'cur' => 'PHP',
                'num' => 1_250,
            ],
            'date' => $providerObservedAt->format(DATE_RFC3339),
        ],
    ]);
}

function capitalizeRepairFixture(): void
{
    $system = app(SystemUserResolverContract::class)->resolve();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $system,
        ['netbank-primary'],
    );
    $positions = collect(
        app(TreasuryPositionReadModelContract::class)
            ->forPrincipal('principal:system'),
    );
    $legacy = $positions->sole(
        fn ($position): bool => $position->purpose
            === TreasuryPositionPurpose::LegacyUnattributed,
    );
    $reserve = $positions->sole(
        fn ($position): bool => $position->purpose
            === TreasuryPositionPurpose::AccountFundingReserve,
    );

    app(TreasuryPositionOperationContract::class)->allocate(
        new TreasuryPositionAllocationData(
            operationReference: 'test-opening-capitalization',
            sourcePositionReference: $legacy->positionReference,
            destinationPositionReference: $reserve->positionReference,
            amountMinor: 10_000,
            currency: 'PHP',
            idempotencyKey: 'test-opening-capitalization:key',
            externalReference: 'test-opening-capitalization:authorization',
        ),
    );
}
