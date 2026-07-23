<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use LBHurtado\EmiCore\Contracts\StandingFundingAddressProvider;
use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressRequestData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingObservationRequestData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
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
        ->and($second->providerAddress->fundingAddress)->toBe($provider->fundingAddress);

    $stored = DB::table('x_change_standing_funding_addresses')->sole();

    expect($stored->funding_address_ciphertext)->not->toContain($provider->fundingAddress)
        ->and($stored->funding_address_hash)->toBe(hash('sha256', $provider->fundingAddress));
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
): ProviderFundingObservationData {
    return new ProviderFundingObservationData(
        provider: 'netbank',
        providerTransactionId: $providerTransactionId,
        grossAmountMinor: $grossAmountMinor,
        feeAmountMinor: 50,
        netAmountMinor: $grossAmountMinor - 50,
        currency: 'PHP',
        providerStatus: 'settled',
        verificationSource: 'netbank-vca-transaction-history',
        payloadHash: hash('sha256', $providerTransactionId.':settled'),
        fundingAddress: 'sha256:'.hash('sha256', $fundingAddress),
        providerAccountReference: 'sha256:'.hash('sha256', 'corporate-account'),
        occurredAt: new DateTimeImmutable('2026-07-24T00:00:00+08:00'),
        settledAt: new DateTimeImmutable('2026-07-24T00:01:00+08:00'),
        metadata: [
            'destination_verified' => true,
            'address_purpose' => FundingAddressPurpose::AccountFunding->value,
        ],
    );
}

final class StandingFundingAddressProviderFake implements StandingFundingAddressProvider
{
    public string $fundingAddress = '915001234567890123456';

    /** @var list<ProviderFundingObservationData> */
    public array $observations = [];

    public function providerCode(): string
    {
        return 'netbank';
    }

    public function createStandingFundingAddress(
        StandingFundingAddressRequestData $request,
    ): StandingFundingAddressData {
        $this->fundingAddress = '91500'.substr(hash(
            'sha256',
            $request->ownerReference.'|'.$request->purpose->value,
        ), 0, 16);

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
        );
    }

    public function observeStandingFundingAddress(
        StandingFundingObservationRequestData $request,
    ): array {
        return $this->observations;
    }
}
