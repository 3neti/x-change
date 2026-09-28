<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Treasury\ReleasePayCodeTerminalReserve;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Services\Commercial\CommercialPrincipalProvisioningService;
use LBHurtado\XChange\Tests\Fakes\User;

function commercialPayCodeCommand(): Command
{
    return new class extends Command
    {
        public function option($key = null): mixed
        {
            return false;
        }

        public function info($string, $verbosity = null): void {}

        public function line($string, $style = null, $verbosity = null): void {}
    };
}

function authorizeCommercialPayCodeActors(User $maker, User $checker): void
{
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $maker->getMorphClass(),
        'operator_id' => $maker->getKey(),
        'capability' => CommercialOperatorCapability::PreparePayCodes->value,
        'authorization_reference' => 'test:commercial-pay-code:maker',
        'valid_from' => now()->subMinute(),
    ]);
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $checker->getMorphClass(),
        'operator_id' => $checker->getKey(),
        'capability' => CommercialOperatorCapability::ApprovePayCodes->value,
        'authorization_reference' => 'test:commercial-pay-code:checker',
        'valid_from' => now()->subMinute(),
    ]);
}

it('credits the Commercial Principal then independently approves and reserves its fifty peso Pay Code', function (): void {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    $checker = User::query()->create([
        'name' => 'Commercial Checker',
        'email' => 'commercial-checker@example.test',
        'password' => 'password',
    ]);
    app(CommercialPrincipalProvisioningService::class)->provision();
    $principal = CommercialPrincipal::query()->sole();
    authorizeCommercialPayCodeActors($maker, $checker);

    $prepare = app(LifecycleScenarioEngine::class)->run(
        commercialPayCodeCommand(),
        'commercial_pay_code_issuance',
        new LifecycleScenarioRunOptions(
            maker: (string) $maker->getKey(),
            checker: (string) $checker->getKey(),
            runReference: 'COMMERCIAL-PC-001',
            phase: 'prepare',
            json: true,
        ),
    );

    expect($prepare->exitCode)->toBe(Command::SUCCESS, json_encode($prepare->payload))
        ->and($prepare->payload)->toMatchArray([
            'phase' => 'awaiting_checker',
            'amount_minor' => 5_000,
            'provider_calls' => 0,
            'external_money_moved' => false,
        ])
        ->and($prepare->payload['approval_pay_code'])->toBeString()
        ->and($prepare->payload['funding_source'])->toBe('commercial_principal_client_funds')
        ->and($prepare->payload['balances'])->toMatchArray([
            'commercial_client_funds_minor' => 10_000,
            'commercial_pay_code_reserve_minor' => 0,
            'commercial_revenue_minor' => 0,
        ])
        ->and(Voucher::query()->count())->toBe(1);

    $approve = app(LifecycleScenarioEngine::class)->run(
        commercialPayCodeCommand(),
        'commercial_pay_code_issuance',
        new LifecycleScenarioRunOptions(
            maker: (string) $maker->getKey(),
            checker: (string) $checker->getKey(),
            runReference: 'COMMERCIAL-PC-001',
            phase: 'approve',
            confirmCheckerApproval: true,
            json: true,
        ),
    );

    expect($approve->exitCode)->toBe(Command::SUCCESS)
        ->and($approve->payload)->toMatchArray([
            'phase' => 'issued',
            'amount_minor' => 5_000,
            'provider_calls' => 0,
            'external_money_moved' => false,
        ])
        ->and($approve->payload['funding_source'])->toBe('commercial_principal_client_funds')
        ->and($approve->payload['balances'])->toMatchArray([
            'commercial_client_funds_minor' => 5_000,
            'commercial_pay_code_reserve_minor' => 5_000,
            'commercial_revenue_minor' => 0,
        ])
        ->and($approve->payload['pay_code'])->toBeString();

    $voucher = Voucher::query()->where('code', $approve->payload['pay_code'])->sole();
    expect($voucher->owner->is($maker))->toBeTrue()
        ->and(data_get($voucher->metadata, 'instructions.metadata.custom.commercial_sponsorship.principal_reference'))
        ->toBe($principal->reference)
        ->and(data_get($voucher->metadata, 'instructions.metadata.custom.commercial_sponsorship.issued_by.id'))
        ->toBe((string) $maker->getKey())
        ->and(data_get($voucher->metadata, 'instructions.metadata.custom.commercial_sponsorship.approved_by.id'))
        ->toBe((string) $checker->getKey())
        ->and(data_get($voucher->metadata, 'instructions.metadata.custom.commercial_sponsorship.funded_by'))
        ->toBe($principal->reference)
        ->and(data_get($voucher->metadata, 'treasury.pay_code_reservation.funding_principal.funding_principal_id'))
        ->toBe((string) $principal->getKey())
        ->and(data_get($voucher->metadata, 'treasury.pay_code_reservation.funding_principal.voucher_owner_id'))
        ->toBe((string) $maker->getKey());

    $principalPositions = app(TreasuryAccountPortfolioProvisioningContract::class)
        ->provision($principal, ['netbank-primary'])
        ->positions;
    $balances = collect($principalPositions)->mapWithKeys(
        fn ($position): array => [$position->purpose->value => $position->balanceMinor],
    );
    $makerBalances = collect(app(TreasuryAccountPortfolioProvisioningContract::class)
        ->provision($maker, ['netbank-primary'])->positions)
        ->mapWithKeys(fn ($position): array => [$position->purpose->value => $position->balanceMinor]);
    expect($balances[TreasuryPositionPurpose::ClientFunds->value])->toBe(5_000)
        ->and($balances[TreasuryPositionPurpose::PayCodeReserve->value])->toBe(5_000)
        ->and($makerBalances[TreasuryPositionPurpose::ClientFunds->value])->toBe(0)
        ->and($makerBalances[TreasuryPositionPurpose::PayCodeReserve->value])->toBe(0)
        ->and(Voucher::query()->count())->toBe(2);

    $authoritativeMetadata = $voucher->metadata;
    $tamperedMetadata = $authoritativeMetadata;
    data_set(
        $tamperedMetadata,
        'treasury.pay_code_reservation.funding_principal.evidence_hash',
        str_repeat('0', 64),
    );
    $voucher->forceFill(['metadata' => $tamperedMetadata])->saveQuietly();
    expect(fn () => app(ReleasePayCodeTerminalReserve::class)->handle($voucher->refresh(), 'cancelled'))
        ->toThrow(RuntimeException::class, 'funding principal evidence is invalid');
    $voucher->forceFill(['metadata' => $authoritativeMetadata])->saveQuietly();

    $cancel = app(LifecycleScenarioEngine::class)->run(
        commercialPayCodeCommand(),
        'commercial_pay_code_issuance',
        new LifecycleScenarioRunOptions(
            maker: (string) $maker->getKey(),
            checker: (string) $checker->getKey(),
            amount: 50,
            runReference: 'COMMERCIAL-PC-001',
            phase: 'cancel',
            json: true,
        ),
    );

    expect($cancel->exitCode)->toBe(Command::SUCCESS)
        ->and($cancel->payload['phase'])->toBe('cancelled')
        ->and($cancel->payload['balances'])->toMatchArray([
            'commercial_client_funds_minor' => 10_000,
            'commercial_pay_code_reserve_minor' => 0,
            'commercial_revenue_minor' => 0,
        ]);
    $releasedBalances = collect(
        app(TreasuryAccountPortfolioProvisioningContract::class)
            ->provision($principal, ['netbank-primary'])
            ->positions,
    )->mapWithKeys(fn ($position): array => [$position->purpose->value => $position->balanceMinor]);

    expect($releasedBalances[TreasuryPositionPurpose::ClientFunds->value])->toBe(10_000)
        ->and($releasedBalances[TreasuryPositionPurpose::PayCodeReserve->value])->toBe(0)
        ->and((string) data_get($voucher->refresh()->metadata, 'treasury.pay_code_reservation.status'))
        ->toBe('released');
});

it('does not allocate funds or issue the Pay Code before checker approval', function (): void {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    enableNetbankTreasuryForTests();
    $maker = actingAsTestUser();
    $checker = User::query()->create([
        'name' => 'Commercial Checker',
        'email' => 'commercial-checker-two@example.test',
        'password' => 'password',
    ]);
    app(CommercialPrincipalProvisioningService::class)->provision();
    $principal = CommercialPrincipal::query()->sole();
    authorizeCommercialPayCodeActors($maker, $checker);

    app(LifecycleScenarioEngine::class)->run(
        commercialPayCodeCommand(),
        'commercial_pay_code_issuance',
        new LifecycleScenarioRunOptions(
            maker: (string) $maker->getKey(),
            checker: (string) $checker->getKey(),
            amount: 50,
            runReference: 'COMMERCIAL-PC-002',
            phase: 'prepare',
            json: true,
        ),
    );

    $positions = app(TreasuryAccountPortfolioProvisioningContract::class)
        ->provision($principal, ['netbank-primary'])
        ->positions;
    $balances = collect($positions)->mapWithKeys(
        fn ($position): array => [$position->purpose->value => $position->balanceMinor],
    );

    expect($balances[TreasuryPositionPurpose::ClientFunds->value])->toBe(10_000)
        ->and($balances[TreasuryPositionPurpose::PayCodeReserve->value])->toBe(0)
        ->and(Voucher::query()->count())->toBe(1);
});

it('renders the two-persona Commercial Pay Code browser runner for an authorized maker', function (): void {
    config()->set('x-change.lifecycle.commercial_pay_code.browser_enabled', true);
    enableNetbankTreasuryForTests();
    app(CommercialPrincipalProvisioningService::class)->provision();
    $maker = actingAsTestUser();
    $checker = User::query()->create([
        'name' => 'Browser Checker',
        'email' => 'browser-checker@example.test',
        'password' => 'password',
    ]);
    authorizeCommercialPayCodeActors($maker, $checker);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.commercial-pay-code-scenario-runner.show'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/CommercialPayCodeScenarioRunner')
        ->assertJsonPath('props.commercial_principal.reference', 'commercial-primary')
        ->assertJsonPath('props.commercial_principal.funding_source', 'Commercial Principal Client Funds')
        ->assertJsonPath('props.commercial_principal.balances.client_funds_minor', 0)
        ->assertJsonPath('props.commercial_principal.balances.pay_code_reserve_minor', 0)
        ->assertJsonPath('props.commercial_principal.balances.revenue_minor', 0)
        ->assertJsonPath('props.commercial_principal.revenue_account_excluded', true)
        ->assertJsonPath('props.maker.id', (string) $maker->getKey())
        ->assertJsonPath('props.maker_ready', true)
        ->assertJsonPath('props.checkers.0.id', (string) $checker->getKey())
        ->assertJsonPath('props.run', null);
});
