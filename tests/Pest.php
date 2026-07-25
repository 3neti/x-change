<?php

declare(strict_types=1);

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProvider;
use LBHurtado\EmiCore\Contracts\SettlementProviderRegistryContract;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityManifestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityReadinessData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\EmiCore\Support\SettlementProviderRegistry;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Data\WithdrawalDisbursementExecutionData;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\FakeAuditLogger;
use LBHurtado\XChange\Tests\Fakes\FakePayoutProvider;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XChange\Tests\TestCase;
use Propaganistas\LaravelPhone\PhoneNumber;

uses(TestCase::class)->in('Unit', 'Feature');

afterEach(function () {
    Mockery::close();
});

function actingAsTestUser(int $amount = 1_000_000): User
{
    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'tester+'.Str::uuid().'@example.com',
        'password' => Hash::make('password'),
    ]);

    test()->actingAs($user);

    fundTestUserWallet($user, $amount);

    return $user;
}

function fundTestUserWallet(User $user, int $amount = 1_000_000): void
{
    if (! $user instanceof Wallet) {
        throw new RuntimeException('Test user must implement Bavix Wallet interface.');
    }

    $wallet = $user->wallet()->firstOrCreate([
        'slug' => 'platform',
    ], [
        'name' => 'Platform Wallet',
    ]);

    if ((int) $wallet->balance < $amount) {
        $wallet->deposit($amount - (int) $wallet->balance);
    }
}
function validVoucherInstructions(
    float $amount = 100.00,
    ?string $settlementRail = 'INSTAPAY',
    array $overrides = [],
): VoucherInstructionsData {
    $data = array_replace_recursive([
        'cash' => [
            'amount' => $amount,
            'currency' => 'PHP',
            'settlement_rail' => $settlementRail,
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'http://example.com/webhook',
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
        'metadata' => [
            'issuer_id' => (string) optional(auth()->user())->id,
            'issuer_name' => optional(auth()->user())->name,
            'issuer_email' => optional(auth()->user())->email,
            'created_at' => now()->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
        ],
    ], $overrides);

    return VoucherInstructionsData::from($data);
}

function issueVoucher(
    VoucherInstructionsData|array|null $instructions = null
): Voucher {
    if (! auth()->check()) {
        actingAsTestUser();
    }

    /** @var User $user */
    $user = auth()->user();

    fundTestUserWallet($user);

    $instructions ??= validVoucherInstructions();

    if (is_array($instructions)) {
        $instructions = VoucherInstructionsData::from($instructions);
    }

    /** @var Voucher $voucher */
    $voucher = GenerateVouchers::run($instructions)->first();

    return $voucher;
}

function fakePayoutProvider(): FakePayoutProvider
{
    /** @var TestCase $test */
    $test = test();

    return $test->fakePayoutProvider()->reset();
}

function fakeAuditLogger(): FakeAuditLogger
{
    /** @var TestCase $test */
    $test = test();

    return $test->fakeAuditLogger()->reset();
}

function xchangeApi(string $path): string
{
    $prefix = trim((string) config('x-change.routes.api_prefix', 'api/x'), '/');
    $version = trim((string) config('x-change.routes.api_version', 'v1'), '/');
    $path = ltrim($path, '/');

    return '/'.$prefix.'/'.$version.'/'.$path;
}

function enableNetbankTreasuryForTests(): User
{
    $system = User::query()->create([
        'name' => 'Test System Treasury',
        'email' => 'test-system-treasury+'.Str::uuid().'@example.com',
        'password' => 'not-a-login-credential',
    ]);
    $provider = new class implements SettlementProvider
    {
        public function manifest(): ProviderCapabilityManifestData
        {
            return new ProviderCapabilityManifestData(
                provider: 'netbank',
                label: 'NetBank Test Provider',
                capabilities: [
                    ProviderCapability::ReadinessProbe,
                    ProviderCapability::BalanceRead,
                    ProviderCapability::FundingEvidenceRead,
                    ProviderCapability::FundingInstructionIssue,
                ],
            );
        }
    };
    $probe = new class implements ProviderReadinessProbe
    {
        public function providerCode(): string
        {
            return 'netbank';
        }

        public function checkReadiness(
            ProviderReadinessRequestData $request,
        ): ProviderCapabilityReadinessData {
            return new ProviderCapabilityReadinessData(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                checks: collect($request->requiredCapabilities)
                    ->mapWithKeys(static fn ($capability): array => [$capability->value => true])
                    ->all(),
                issues: [],
                checkedAt: new DateTimeImmutable,
            );
        }
    };
    $systemResolver = new class($system) implements SystemUserResolverContract
    {
        public function __construct(private readonly User $system) {}

        public function resolve(): Wallet
        {
            return $this->system;
        }
    };

    config()->set('x-change.treasury.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.treasury.legal_profile', 'treasury-settlement-ph-v1');
    config()->set('x-change.treasury.legal_profile_version', '2026-07-24.1');
    config()->set('x-change.treasury.connections', [
        'netbank-primary' => [
            'provider' => 'netbank',
            'mode' => 'required',
            'currency' => 'PHP',
            'decimal_places' => 2,
            'inventory_reference' => 'inventory:netbank:vca-cash',
            'settlement_resource_reference' => 'resource:netbank:corporate-vca',
            'settlement_resource_type' => 'cash_at_bank',
            'custody_mode' => 'provider_projection',
            'required_capabilities' => [
                'readiness_probe',
                'balance_read',
                'funding_evidence_read',
                'funding_instruction_issue',
            ],
        ],
    ]);
    app()->instance(
        SettlementProviderRegistryContract::class,
        new SettlementProviderRegistry([$provider]),
    );
    app()->instance($probe::class, $probe);
    app()->tag($probe::class, 'emi.provider-readiness-probes');
    app()->instance(
        SystemUserResolverContract::class,
        $systemResolver,
    );

    foreach ([
        TreasuryProviderConnectionCatalog::class,
        TreasuryPreflightService::class,
        TreasuryProvisioningService::class,
        TreasuryAccountPortfolioProvisioningContract::class,
        VerifiedTreasuryFundingAllocationContract::class,
    ] as $abstract) {
        app()->forgetInstance($abstract);
    }

    return $system;
}

function treasuryClientFundsLedger(User $owner, string $provider = 'netbank'): Bavix\Wallet\Models\Wallet
{
    $position = TreasuryPosition::query()
        ->whereMorphedTo('principal', $owner)
        ->where('provider', $provider)
        ->where('purpose', TreasuryPositionPurpose::ClientFunds)
        ->sole();

    return Bavix\Wallet\Models\Wallet::query()->findOrFail($position->internal_ledger_id);
}

/**
 * @return array<string, mixed>
 */
function validPayCodePayload(
    float $amount = 100.00,
    ?string $settlementRail = 'INSTAPAY',
    array $overrides = []
): array {
    return array_replace_recursive([
        'cash' => [
            'amount' => $amount,
            'currency' => 'PHP',
            'settlement_rail' => $settlementRail,
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => [
                'selfie',
                'signature',
            ],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.com/webhook',
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
        'metadata' => [],
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function validOnboardIssuerPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Issuer Name',
        'email' => 'issuer@example.com',
        'mobile' => '09171234567',
        'country' => 'PH',
        'identity' => [],
        'metadata' => [],
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function validOpenIssuerWalletPayload(
    mixed $issuerId = 1,
    array $overrides = []
): array {
    return array_replace_recursive([
        'issuer_id' => $issuerId,
        'wallet' => [
            'slug' => 'platform',
            'name' => 'Platform Wallet',
        ],
        'metadata' => [],
    ], $overrides);
}

function validPayoutRequestData(): PayoutRequestData
{
    return PayoutRequestData::from([
        'reference' => 'TEST-09173011987-S1',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);
}

function fakeWithdrawalDisbursementExecution(): WithdrawalDisbursementExecutionData
{
    $input = validPayoutRequestData();

    return new WithdrawalDisbursementExecutionData(
        input: $input,
        response: PayoutResultData::from([
            'uuid' => (string) Str::uuid(),
            'transaction_id' => 'TXN-123',
            'status' => PayoutStatus::PENDING,
            'provider' => 'netbank',
            'raw' => [],
        ]),
        status: 'pending',
        message: null,
    );
}

function fakePayoutContact(
    ?string $name = 'Juan Dela Cruz',
    string $mobile = '09171234567',
    string $country = 'PH',
): Contact {
    $phone = new PhoneNumber($mobile, $country);
    $contact = Contact::fromPhoneNumber($phone) ?? new Contact;
    // Fallback if fromPhoneNumber returns null (e.g., not persisted)
    $contact->mobile = $phone->formatForMobileDialingInCountry($country);
    if ($name !== null) {
        $contact->name = $name;
    }

    return $contact;
}

function settlementEnvelopeDriversPath(): string
{
    return dirname(__DIR__, 1).'/config/envelope-drivers';
}
