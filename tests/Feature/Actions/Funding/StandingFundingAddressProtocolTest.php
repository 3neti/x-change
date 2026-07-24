<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Contracts\StandingFundingAddressProvider;
use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressRequestData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingObservationRequestData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\XChange\Actions\Funding\OpenFundingSuspenseCase;
use LBHurtado\XChange\Actions\Funding\ProvisionStandingFundingAddress;
use LBHurtado\XChange\Actions\Funding\SyncStandingFundingAddress;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressProviderRegistry;

beforeEach(function () {
    config()->set('x-change.funding.standing_addresses.limits', [
        'minimum_amount_minor' => 100,
        'maximum_amount_minor' => 5_000_000,
        'daily_limit_minor' => 10_000_000,
    ]);
});

it('persists an immutable purpose-bound address without storing plaintext', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $provider = new StandingFundingAddressProviderFake;
    bindStandingFundingProvider($provider);

    $first = app(ProvisionStandingFundingAddress::class)->handle(
        owner: $user,
        accountReference: 'wallet:'.$wallet->uuid,
        provider: 'netbank',
        purpose: FundingAddressPurpose::AccountFunding,
        recognitionMode: FundingRecognitionMode::ObserveOnly,
    );
    $second = app(ProvisionStandingFundingAddress::class)->handle(
        owner: $user,
        accountReference: 'wallet:'.$wallet->uuid,
        provider: 'netbank',
        purpose: FundingAddressPurpose::AccountFunding,
        recognitionMode: FundingRecognitionMode::Automatic,
    );

    expect($second->address->is($first->address))->toBeTrue()
        ->and($second->address->purpose)->toBe(FundingAddressPurpose::AccountFunding)
        ->and($second->address->recognition_mode)->toBe(FundingRecognitionMode::ObserveOnly)
        ->and(StandingFundingAddress::query()->count())->toBe(1)
        ->and($second->providerAddress->fundingAddress)->toBe($provider->fundingAddress)
        ->and($provider->requests)->toHaveCount(2)
        ->and($provider->requests[1]->existingFundingAddress)->toBe(
            $first->providerAddress->fundingAddress,
        );

    $stored = DB::table('x_change_standing_funding_addresses')->sole();

    expect($stored->funding_address_ciphertext)->not->toContain($provider->fundingAddress)
        ->and($stored->funding_address_hash)->toBe(hash('sha256', $provider->fundingAddress))
        ->and($stored->derivation_scheme)->toBe('netbank-account-hmac-v2')
        ->and($stored->derivation_key_id)->toBe('test-key-v2')
        ->and($stored->derivation_counter)->toBe(0)
        ->and($stored->reference_length)->toBe(11);
});

it('recognizes settled provider evidence and credits an Account exactly once', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $provider = new StandingFundingAddressProviderFake;
    bindStandingFundingProvider($provider);
    $address = provisionStandingAddress(
        $user,
        'wallet:'.$wallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::Automatic,
    );
    $provider->observations = [
        standingFundingObservation($provider->fundingAddress),
    ];

    $first = app(SyncStandingFundingAddress::class)->handle($address);
    $second = app(SyncStandingFundingAddress::class)->handle($address->refresh());
    $receipt = AccountFundingReceipt::query()->sole();

    expect($first->settled)->toBe(1)
        ->and($second->settled)->toBe(1)
        ->and($receipt->status)->toBe(AccountFundingReceiptStatus::Settled)
        ->and($receipt->gross_amount_minor)->toBe(25_000)
        ->and($receipt->net_amount_minor)->toBe(24_950)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(24_950)
        ->and(AccountFundingReceipt::query()->count())->toBe(1)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(24_950)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(Transaction::query()->where('type', Transaction::TYPE_DEPOSIT)->count())->toBe(1);
});

it('keeps observe-only receipts out of Account and Treasury balances', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $provider = new StandingFundingAddressProviderFake;
    bindStandingFundingProvider($provider);
    $address = provisionStandingAddress(
        $user,
        'wallet:'.$wallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::ObserveOnly,
    );
    $provider->observations = [
        standingFundingObservation($provider->fundingAddress),
    ];

    $result = app(SyncStandingFundingAddress::class)->handle($address);
    $receipt = AccountFundingReceipt::query()->sole();

    expect($result->observed)->toBe(1)
        ->and($receipt->status)->toBe(AccountFundingReceiptStatus::Observed)
        ->and($receipt->verified_at)->not->toBeNull()
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(0);
});

it('requires owner approval before supervised recognition can credit the Account', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $provider = new StandingFundingAddressProviderFake;
    bindStandingFundingProvider($provider);
    $address = provisionStandingAddress(
        $user,
        'wallet:'.$wallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::Supervised,
    );
    $provider->observations = [
        standingFundingObservation($provider->fundingAddress),
    ];

    $result = app(SyncStandingFundingAddress::class)->handle($address);
    $receipt = AccountFundingReceipt::query()->sole();

    expect($result->awaitingApproval)->toBe(1)
        ->and($receipt->status)->toBe(AccountFundingReceiptStatus::AwaitingApproval)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0);

    $response = $this->postJson(route(
        'x-change.cockpit.funding.standing-addresses.netbank.receipts.approve',
        $receipt,
    ));
    $settled = $receipt->refresh();

    $response
        ->assertOk()
        ->assertJsonPath('schema', 'x-change.cockpit.account-funding-receipt-approval.v1')
        ->assertJsonPath('receipt.status', 'settled');

    expect($settled->status)->toBe(AccountFundingReceiptStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(24_950)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1);
});

it('preserves legacy evidence while correcting a post-activation NetBank credit once', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $provider = new StandingFundingAddressProviderFake;
    bindStandingFundingProvider($provider);
    $address = provisionStandingAddress(
        $user,
        'wallet:'.$wallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::ObserveOnly,
    );
    $occurredAt = $address->activated_at->addMinute()->toDateTimeImmutable();
    $provider->observations = [
        standingFundingObservation(
            $provider->fundingAddress,
            'netbank-corrected-transaction',
            2_500,
            feeAmountMinor: 2_500,
            netAmountMinor: 0,
            occurredAt: $occurredAt,
        ),
    ];

    $legacy = app(SyncStandingFundingAddress::class)->handle($address);
    $receipt = AccountFundingReceipt::query()->sole();
    $originalObservation = $receipt->providerFundingObservation()->sole();

    expect($legacy->suspense)->toBe(1)
        ->and($receipt->status)->toBe(AccountFundingReceiptStatus::Suspense)
        ->and($receipt->suspense_reason)->toBe('non_positive_net_amount')
        ->and($originalObservation->fee_amount_minor)->toBe(2_500)
        ->and($originalObservation->net_amount_minor)->toBe(0);

    $provider->observations = [
        standingFundingObservation(
            $provider->fundingAddress,
            'netbank-corrected-transaction',
            2_500,
            feeAmountMinor: 0,
            netAmountMinor: 2_500,
            occurredAt: $occurredAt,
            metadata: [
                'destination_verified' => true,
                'address_purpose' => FundingAddressPurpose::AccountFunding->value,
                'normalization_version' => 'netbank-standing-credit-v2',
                'incoming_credit_amount_is_net_received' => true,
            ],
        ),
    ];

    $corrected = app(SyncStandingFundingAddress::class)->handle($address->refresh());
    $receipt->refresh();
    $correctedObservation = $receipt->providerFundingObservation()->sole();
    $case = FundingSuspenseCase::query()->sole();

    expect($corrected->awaitingApproval)->toBe(1)
        ->and($receipt->status)->toBe(AccountFundingReceiptStatus::AwaitingApproval)
        ->and($receipt->gross_amount_minor)->toBe(2_500)
        ->and($receipt->fee_amount_minor)->toBe(0)
        ->and($receipt->net_amount_minor)->toBe(2_500)
        ->and($receipt->suspense_reason)->toBeNull()
        ->and($receipt->metadata['normalization_correction']['original_observation_id'])
        ->toBe($originalObservation->getKey())
        ->and($correctedObservation->getKey())->not->toBe($originalObservation->getKey())
        ->and($correctedObservation->metadata['normalization_version'])
        ->toBe('netbank-standing-credit-v2')
        ->and($originalObservation->fresh()->fee_amount_minor)->toBe(2_500)
        ->and($originalObservation->fresh()->net_amount_minor)->toBe(0)
        ->and($case->status)->toBe('resolved')
        ->and($case->resolution_code)->toBe('normalization_corrected')
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(ProviderFundingObservation::query()->count())->toBe(2);

    $response = $this->postJson(route(
        'x-change.cockpit.funding.standing-addresses.netbank.receipts.approve',
        $receipt,
    ));
    $replayed = app(SyncStandingFundingAddress::class)->handle($address->refresh());

    $response->assertOk()->assertJsonPath('receipt.status', 'settled');
    expect($replayed->settled)->toBe(1)
        ->and($receipt->refresh()->status)->toBe(AccountFundingReceiptStatus::Settled)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(2_500)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(Transaction::query()->where('type', Transaction::TYPE_DEPOSIT)->count())->toBe(1)
        ->and(AccountFundingReceipt::query()->count())->toBe(1)
        ->and(ProviderFundingObservation::query()->count())->toBe(2);
});

it('does not record or recognize provider transactions from before address activation', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $provider = new StandingFundingAddressProviderFake;
    bindStandingFundingProvider($provider);
    $address = provisionStandingAddress(
        $user,
        'wallet:'.$wallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::Automatic,
    );
    $provider->observations = [
        standingFundingObservation(
            $provider->fundingAddress,
            'pre-activation-transaction',
            occurredAt: $address->activated_at->subSecond()->toDateTimeImmutable(),
        ),
    ];

    $result = app(SyncStandingFundingAddress::class)->handle($address);

    expect($result->observed)->toBe(0)
        ->and($result->settled)->toBe(0)
        ->and($result->awaitingApproval)->toBe(0)
        ->and($result->suspense)->toBe(0)
        ->and(AccountFundingReceipt::query()->count())->toBe(0)
        ->and(ProviderFundingObservation::query()->count())->toBe(0)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(0);
});

it('recovers a corrected observation previously classified as changed evidence', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $provider = new StandingFundingAddressProviderFake;
    bindStandingFundingProvider($provider);
    $address = provisionStandingAddress(
        $user,
        'wallet:'.$wallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::ObserveOnly,
    );
    $occurredAt = $address->activated_at->addMinute()->toDateTimeImmutable();
    $provider->observations = [
        standingFundingObservation(
            $provider->fundingAddress,
            'previously-changed-evidence',
            2_500,
            feeAmountMinor: 2_500,
            netAmountMinor: 0,
            occurredAt: $occurredAt,
        ),
    ];

    app(SyncStandingFundingAddress::class)->handle($address);
    $receipt = AccountFundingReceipt::query()->sole();
    $originalObservationId = $receipt->provider_funding_observation_id;
    $correctedData = standingFundingObservation(
        $provider->fundingAddress,
        'previously-changed-evidence',
        2_500,
        feeAmountMinor: 0,
        netAmountMinor: 2_500,
        occurredAt: $occurredAt,
        metadata: [
            'destination_verified' => true,
            'address_purpose' => FundingAddressPurpose::AccountFunding->value,
            'normalization_version' => 'netbank-standing-credit-v2',
            'incoming_credit_amount_is_net_received' => true,
        ],
        payloadHash: hash('sha256', 'updated-provider-history-payload'),
    );
    $correctedObservation = app(RecordProviderFundingObservation::class)
        ->handle($correctedData);
    $receipt->forceFill([
        'provider_funding_observation_id' => $correctedObservation->getKey(),
        'status' => AccountFundingReceiptStatus::Suspense,
        'suspense_reason' => 'provider_evidence_changed',
    ])->saveQuietly();
    app(OpenFundingSuspenseCase::class)->handle(
        provider: 'netbank',
        reasonCode: 'provider_evidence_changed',
        observation: $correctedObservation,
        details: ['account_funding_receipt_reference' => $receipt->reference],
    );
    $provider->observations = [$correctedData];

    $result = app(SyncStandingFundingAddress::class)->handle($address->refresh());

    expect($result->awaitingApproval)->toBe(1)
        ->and($receipt->refresh()->status)->toBe(AccountFundingReceiptStatus::AwaitingApproval)
        ->and($receipt->provider_funding_observation_id)->toBe($correctedObservation->getKey())
        ->and($receipt->metadata['normalization_correction']['original_observation_id'])
        ->toBe($originalObservationId)
        ->and($receipt->net_amount_minor)->toBe(2_500)
        ->and(FundingSuspenseCase::query()->where('status', 'open')->count())->toBe(0)
        ->and(FundingSuspenseCase::query()->where('status', 'resolved')->count())->toBe(2)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0);
});

it('quarantines previously imported pre-activation receipts without changing balances', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $provider = new StandingFundingAddressProviderFake;
    bindStandingFundingProvider($provider);
    $address = provisionStandingAddress(
        $user,
        'wallet:'.$wallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::Automatic,
    );
    $address->forceFill(['activated_at' => now()->subMinutes(10)])->saveQuietly();
    $provider->observations = [
        standingFundingObservation(
            $provider->fundingAddress,
            'imported-before-boundary',
            2_500,
            feeAmountMinor: 2_500,
            netAmountMinor: 0,
            occurredAt: now()->subMinutes(5)->toDateTimeImmutable(),
        ),
    ];

    app(SyncStandingFundingAddress::class)->handle($address->refresh());
    $receipt = AccountFundingReceipt::query()->sole();

    expect($receipt->status)->toBe(AccountFundingReceiptStatus::Suspense)
        ->and(FundingSuspenseCase::query()->sole()->status)->toBe('open');

    $address->forceFill(['activated_at' => now()])->saveQuietly();
    $result = app(SyncStandingFundingAddress::class)->handle($address->refresh());

    expect($result->settled)->toBe(0)
        ->and($receipt->refresh()->status)->toBe(AccountFundingReceiptStatus::Ignored)
        ->and($receipt->suspense_reason)->toBe('pre_activation_transaction')
        ->and(FundingSuspenseCase::query()->sole()->status)->toBe('resolved')
        ->and(FundingSuspenseCase::query()->sole()->resolution_code)
        ->toBe('pre_activation_ignored')
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(0);
});

it('routes unknown destinations and amount-limit failures to suspense', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $provider = new StandingFundingAddressProviderFake;
    bindStandingFundingProvider($provider);
    $address = provisionStandingAddress(
        $user,
        'wallet:'.$wallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::Automatic,
    );

    $provider->observations = [
        standingFundingObservation('unregistered-destination', 'txn-unknown'),
    ];
    $unknown = app(SyncStandingFundingAddress::class)->handle($address);

    $provider->observations = [
        standingFundingObservation($provider->fundingAddress, 'txn-too-large', 5_000_001),
    ];
    $limited = app(SyncStandingFundingAddress::class)->handle($address->refresh());

    expect($unknown->suspense)->toBe(1)
        ->and($limited->suspense)->toBe(1)
        ->and(FundingSuspenseCase::query()->pluck('reason_code')->all())
        ->toContain('unknown_funding_address', 'above_maximum_amount')
        ->and(AccountFundingReceipt::query()->sole()->status)
        ->toBe(AccountFundingReceiptStatus::Suspense)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0);
});

it('classifies payment-purpose observations without crediting the Account', function () {
    $user = actingAsTestUser(0);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $provider = new StandingFundingAddressProviderFake;
    bindStandingFundingProvider($provider);
    $address = provisionStandingAddress(
        $user,
        'wallet:'.$wallet->uuid,
        FundingAddressPurpose::Payment,
        FundingRecognitionMode::Automatic,
    );
    $provider->observations = [
        standingFundingObservation($provider->fundingAddress),
    ];

    $result = app(SyncStandingFundingAddress::class)->handle($address);

    expect($result->observed)->toBe(1)
        ->and(AccountFundingReceipt::query()->count())->toBe(0)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(0);
});

it('retries an HMAC derivation collision with the next persisted counter', function () {
    $provider = new StandingFundingAddressProviderFake;
    $provider->counterZeroAddress = '9150000000000000';
    bindStandingFundingProvider($provider);

    $firstOwner = actingAsTestUser(0);
    $firstWallet = $firstOwner->wallet()->where('slug', 'platform')->firstOrFail();
    $first = provisionStandingAddress(
        $firstOwner,
        'wallet:'.$firstWallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::ObserveOnly,
    );

    $secondOwner = actingAsTestUser(0);
    $secondWallet = $secondOwner->wallet()->where('slug', 'platform')->firstOrFail();
    $second = provisionStandingAddress(
        $secondOwner,
        'wallet:'.$secondWallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::ObserveOnly,
    );

    expect($first->funding_address_ciphertext)->toBe('9150000000000000')
        ->and($first->derivation_counter)->toBe(0)
        ->and($second->funding_address_ciphertext)->not->toBe($first->funding_address_ciphertext)
        ->and($second->derivation_counter)->toBe(1)
        ->and(StandingFundingAddress::query()->count())->toBe(2);
});

it('fails closed when two mobile-derived bindings resolve to the same address', function () {
    $provider = new StandingFundingAddressProviderFake;
    $provider->scheme = 'netbank-mobile-v1';
    $provider->counterZeroAddress = '9150009173011987';
    bindStandingFundingProvider($provider);

    $firstOwner = actingAsTestUser(0);
    $firstWallet = $firstOwner->wallet()->where('slug', 'platform')->firstOrFail();
    provisionStandingAddress(
        $firstOwner,
        'wallet:'.$firstWallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::ObserveOnly,
    );

    $secondOwner = actingAsTestUser(0);
    $secondWallet = $secondOwner->wallet()->where('slug', 'platform')->firstOrFail();

    expect(fn () => provisionStandingAddress(
        $secondOwner,
        'wallet:'.$secondWallet->uuid,
        FundingAddressPurpose::AccountFunding,
        FundingRecognitionMode::ObserveOnly,
    ))->toThrow(InvalidArgumentException::class, 'already bound to another Account');
    expect(StandingFundingAddress::query()->count())->toBe(1);
});

function bindStandingFundingProvider(StandingFundingAddressProviderFake $provider): void
{
    app()->instance(
        StandingFundingAddressProviderRegistry::class,
        new StandingFundingAddressProviderRegistry([$provider]),
    );
}

function provisionStandingAddress(
    object $owner,
    string $accountReference,
    FundingAddressPurpose $purpose,
    FundingRecognitionMode $recognitionMode,
): StandingFundingAddress {
    return app(ProvisionStandingFundingAddress::class)->handle(
        owner: $owner,
        accountReference: $accountReference,
        provider: 'netbank',
        purpose: $purpose,
        recognitionMode: $recognitionMode,
    )->address;
}

function standingFundingObservation(
    string $fundingAddress,
    string $providerTransactionId = 'provider-transaction-1',
    int $grossAmountMinor = 25_000,
    int $feeAmountMinor = 50,
    ?int $netAmountMinor = null,
    ?DateTimeImmutable $occurredAt = null,
    ?array $metadata = null,
    ?string $payloadHash = null,
): ProviderFundingObservationData {
    $effectiveOccurredAt = $occurredAt ?? now()->addMinute()->toDateTimeImmutable();

    return new ProviderFundingObservationData(
        provider: 'netbank',
        providerTransactionId: $providerTransactionId,
        grossAmountMinor: $grossAmountMinor,
        feeAmountMinor: $feeAmountMinor,
        netAmountMinor: $netAmountMinor ?? $grossAmountMinor - $feeAmountMinor,
        currency: 'PHP',
        providerStatus: 'settled',
        verificationSource: 'netbank-vca-transaction-history',
        payloadHash: $payloadHash ?? hash('sha256', $providerTransactionId.':settled'),
        fundingAddress: 'sha256:'.hash('sha256', $fundingAddress),
        providerAccountReference: 'sha256:'.hash('sha256', 'corporate-account'),
        occurredAt: $effectiveOccurredAt,
        settledAt: $effectiveOccurredAt->modify('+1 minute'),
        metadata: $metadata ?? [
            'destination_verified' => true,
            'address_purpose' => FundingAddressPurpose::AccountFunding->value,
        ],
    );
}

final class StandingFundingAddressProviderFake implements StandingFundingAddressProvider
{
    public string $fundingAddress = '9150012345678901';

    public string $scheme = 'netbank-account-hmac-v2';

    public ?string $counterZeroAddress = null;

    /** @var list<ProviderFundingObservationData> */
    public array $observations = [];

    /** @var list<StandingFundingAddressRequestData> */
    public array $requests = [];

    public function providerCode(): string
    {
        return 'netbank';
    }

    public function createStandingFundingAddress(
        StandingFundingAddressRequestData $request,
    ): StandingFundingAddressData {
        $this->requests[] = $request;
        $this->fundingAddress = $request->existingFundingAddress
            ?? ($request->derivationCounter === 0 && $this->counterZeroAddress !== null
                ? $this->counterZeroAddress
                : '91500'.str_pad(
                    (string) (hexdec(substr(hash(
                        'sha256',
                        $request->ownerReference.'|'.$request->purpose->value.'|'.$request->derivationCounter,
                    ), 0, 8)) % 100_000_000_000),
                    11,
                    '0',
                    STR_PAD_LEFT,
                ));

        return new StandingFundingAddressData(
            provider: 'netbank',
            providerReference: 'provider-address-1',
            fundingAddress: $this->fundingAddress,
            accountReference: $request->accountReference,
            purpose: $request->purpose,
            currency: $request->currency,
            qrCode: new FundingQrCodeData(
                mimeType: 'image/png',
                base64Payload: 'cG5n',
                qrMode: 'static',
                transactionType: 'p2m',
                embeddedAmount: false,
                providerGenerated: true,
            ),
            displayData: [
                'derivation_scheme' => $request->existingFundingAddress === null
                    ? $this->scheme
                    : null,
                'derivation_key_id' => $request->existingFundingAddress === null
                    && $this->scheme === 'netbank-account-hmac-v2'
                    ? 'test-key-v2'
                    : null,
                'derivation_counter' => $request->existingFundingAddress === null
                    ? $request->derivationCounter
                    : null,
                'reference_length' => 11,
            ],
        );
    }

    public function observeStandingFundingAddress(
        StandingFundingObservationRequestData $request,
    ): array {
        return $this->observations;
    }
}
