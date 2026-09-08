<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Console\Commands\BootstrapXChangeFromManifestCommand;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Services\Commissioning\CommissioningManifestRepository;
use LBHurtado\XChange\Services\CheckNetbankSourceAccountReadiness;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Services\Configuration\LocalEnvironmentFileWriter;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;

it('requires the x payout manifest to commission against netbank readiness', function (): void {
    $manifest = app(CommissioningManifestRepository::class)
        ->load('x-change://commissioning/manifests/x-payout.default.yaml');

    expect(data_get($manifest, 'deployment.profile'))->toBe('netbank')
        ->and(data_get($manifest, 'deployment.runtime_tier'))->toBe('local')
        ->and(data_get($manifest, 'onboarding.invitation_amount'))->toBe(0)
        ->and(data_get($manifest, 'onboarding.currency'))->toBe('PHP')
        ->and(data_get($manifest, 'bootstrap.environment.defaults.XCHANGE_DEPLOYMENT_PROFILE'))->toBe('netbank')
        ->and(data_get($manifest, 'bootstrap.environment.defaults.SESSION_DRIVER'))->toBe('database')
        ->and(data_get($manifest, 'bootstrap.environment.defaults.XCHANGE_FUNDING_NETBANK_ENABLED'))->toBeTrue()
        ->and(data_get($manifest, 'bootstrap.environment.defaults.NETBANK_FUNDING_QR_MERCHANT_NAME'))->toBe('x-PayOut')
        ->and(data_get($manifest, 'bootstrap.environment.defaults.NETBANK_FUNDING_QR_MERCHANT_CITY'))->toBe('Manila')
        ->and(collect(data_get($manifest, 'bootstrap.environment.required', []))->pluck('key')->all())
        ->toContain(
            'NETBANK_DISBURSEMENT_ENDPOINT',
            'NETBANK_TOKEN_ENDPOINT',
            'NETBANK_QR_ENDPOINT',
            'NETBANK_STATUS_ENDPOINT',
            'NETBANK_BALANCE_ENDPOINT',
            'NETBANK_CLIENT_ID',
            'NETBANK_CLIENT_SECRET',
            'NETBANK_CLIENT_ALIAS',
            'NETBANK_SOURCE_ACCOUNT_NUMBER',
            'NETBANK_SENDER_CUSTOMER_ID',
            'NETBANK_FUNDING_CLIENT_ID',
            'NETBANK_FUNDING_CLIENT_SECRET',
            'NETBANK_FUNDING_CORPORATE_ACCOUNT_NUMBER',
            'NETBANK_FUNDING_BALANCE_ENDPOINT',
            'NETBANK_FUNDING_VCA_ALIAS',
        )
        ->not->toContain('NETBANK_FUNDING_QR_MERCHANT_NAME')
        ->not->toContain('NETBANK_FUNDING_QR_MERCHANT_CITY');

    expect(collect(data_get($manifest, 'bootstrap.environment.required', []))
        ->where('key', 'NETBANK_FUNDING_CLIENT_ID')
        ->first())
        ->toMatchArray([
            'same_as' => 'NETBANK_CLIENT_ID',
            'secret' => true,
        ]);
});

it('keeps bootstrap strict while allowing interactive credential capture', function (): void {
    $source = file_get_contents((new ReflectionClass(BootstrapXChangeFromManifestCommand::class))->getFileName());

    expect($source)
        ->toContain('LocalEnvironmentFileWriter')
        ->toContain('$this->input->isInteractive()')
        ->toContain('$this->secret($prompt)')
        ->toContain('aliasedEnvironmentValue')
        ->toContain("\$requirement['same_as']")
        ->toContain('applyPreparedEnvironment')
        ->toContain("'APP_KEY' => \$this->environmentFileValue('APP_KEY') ?? ''")
        ->toContain('{--force : Force database migrations when bootstrapping in production}')
        ->toContain('migrationCommand')
        ->toContain("\$this->option('force') || ! \$this->input->isInteractive()")
        ->toContain("\$command[] = '--force'")
        ->toContain("'config:clear'")
        ->toContain("'x-change:doctor', '--pre-install', '--strict'")
        ->toContain("'x-change:doctor', '--pre-commission', '--strict'")
        ->toContain("'x-change:doctor', '--strict'")
        ->toContain("'--profile='.\$profile")
        ->toContain("'npm', 'install', '--include=dev'")
        ->toContain("'npm', 'run', 'build'")
        ->not->toContain("'key:generate'");
});

it('derives manifest environment aliases from existing source variables', function (): void {
    $command = app(BootstrapXChangeFromManifestCommand::class);
    $method = new ReflectionMethod($command, 'aliasedEnvironmentValue');

    putenv('NETBANK_CLIENT_ID=source-client-id');

    try {
        expect($method->invoke($command, [
            'key' => 'NETBANK_FUNDING_CLIENT_ID',
            'same_as' => 'NETBANK_CLIENT_ID',
            'secret' => true,
        ]))->toBe('source-client-id')
            ->and($method->invoke($command, [
                'key' => 'NETBANK_FUNDING_CLIENT_ID',
                'same_as' => 'netbank-client-id',
            ]))->toBeNull();
    } finally {
        putenv('NETBANK_CLIENT_ID');
    }
});

it('writes generated application keys without quotes so laravel key generation remains stable', function (): void {
    $directory = storage_path('framework/testing/env-writer');
    $environmentPath = $directory.'/.env';
    $examplePath = $directory.'/.env.example';

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    @unlink($environmentPath);
    file_put_contents($examplePath, "APP_KEY=\nAPP_NAME=Laravel\n");

    app(LocalEnvironmentFileWriter::class)->write(
        path: $environmentPath,
        examplePath: $examplePath,
        values: ['XCHANGE_DEPLOYMENT_PROFILE' => 'netbank'],
    );

    $contents = file_get_contents($environmentPath);

    expect($contents)
        ->toContain('APP_KEY=base64:')
        ->not->toContain('APP_KEY="base64:');
});

it('propagates prepared manifest values over stale process values', function (): void {
    $command = app(BootstrapXChangeFromManifestCommand::class);
    $method = new ReflectionMethod($command, 'applyPreparedEnvironment');

    putenv('XCHANGE_DEPLOYMENT_PROFILE=development');
    $_ENV['XCHANGE_DEPLOYMENT_PROFILE'] = 'development';
    $_SERVER['XCHANGE_DEPLOYMENT_PROFILE'] = 'development';

    try {
        $method->invoke($command, [
            'XCHANGE_DEPLOYMENT_PROFILE' => 'netbank',
        ]);

        expect(getenv('XCHANGE_DEPLOYMENT_PROFILE'))->toBe('netbank')
            ->and($_ENV['XCHANGE_DEPLOYMENT_PROFILE'])->toBe('netbank')
            ->and($_SERVER['XCHANGE_DEPLOYMENT_PROFILE'])->toBe('netbank');
    } finally {
        putenv('XCHANGE_DEPLOYMENT_PROFILE');
        unset($_ENV['XCHANGE_DEPLOYMENT_PROFILE'], $_SERVER['XCHANGE_DEPLOYMENT_PROFILE']);
    }
});

it('passes treasury opening capitalization options for funded onboarding bootstrap manifests', function (): void {
    $manifestPath = fundedCommissioningManifestPath([
        'invitation_amount: 100.00',
        'currency: PHP',
        'connection_reference: netbank-primary',
        'funding_source: treasury_account_funding_reserve',
        'authorization_reference: commissioning:x-payout:system-capital',
    ]);
    $manifest = app(CommissioningManifestRepository::class)->load($manifestPath);
    $command = app(BootstrapXChangeFromManifestCommand::class);
    $method = new ReflectionMethod($command, 'treasuryOpeningInstallOptions');

    $options = $method->invoke($command, $manifest);

    expect($options)->toContain(
        '--treasury-opening-policy=system-capital',
        '--capitalization-authorization-reference=commissioning:x-payout:system-capital',
        '--confirm-system-ownership',
    );
});

it('commissions maker and checker onboarding invitations from the package manifest idempotently', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    $this->artisan('x-change:commission:manifest', [
        '--manifest' => 'x-change://commissioning/manifests/x-payout.default.yaml',
    ])
        ->expectsOutputToContain('Commissioning invitation Pay Codes are ready.')
        ->assertSuccessful();

    $this->artisan('x-change:commission:manifest', [
        '--manifest' => 'x-change://commissioning/manifests/x-payout.default.yaml',
    ])->assertSuccessful();

    $vouchers = Voucher::query()->get();
    $roles = $vouchers
        ->map(fn (Voucher $voucher): mixed => data_get(
            $voucher->metadata,
            'instructions.metadata.custom.x_payout_commissioning.role',
        ))
        ->filter()
        ->sort()
        ->values();

    expect($vouchers)->toHaveCount(2)
        ->and($roles->all())->toBe(['checker', 'maker'])
        ->and($vouchers->every(fn (Voucher $voucher): bool => $voucher->redeemed_at === null))->toBeTrue()
        ->and($vouchers->every(fn (Voucher $voucher): bool => data_get($voucher->metadata, 'instructions.onboarding') === true))->toBeTrue()
        ->and($vouchers->every(fn (Voucher $voucher): bool => data_get($voucher->metadata, 'instructions.metadata.flow_type') === 'disbursable'))->toBeTrue()
        ->and($vouchers->every(fn (Voucher $voucher): bool => data_get($voucher->metadata, 'instructions.execution.driver') === OnboardingVoucherInstructionPolicy::ExecutionDriver))->toBeTrue();

    $vouchers->each(function (Voucher $voucher): void {
        expect(route('x-change.claim.show', ['code' => $voucher->code]))
            ->toContain('/x/claim/'.(string) $voucher->code);
    });
});


it('commissions funded maker and checker invitations from the system Account Funding Reserve idempotently', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestSystemAccountFundingReserve(
        $system,
        450_693,
        'x-payout-funded-commissioning',
    );
    $manifestPath = fundedCommissioningManifestPath([
        'invitation_amount: 100.00',
        'currency: PHP',
        'connection_reference: netbank-primary',
        'funding_source: treasury_account_funding_reserve',
        'authorization_reference: commissioning:x-payout:system-capital',
        'funding_instruction: Client funds ready',
    ]);
    mockCommissioningNetbankLiquidityRefresh(450_693, 2);

    $firstExit = Artisan::call('x-change:commission:manifest', [
        '--manifest' => $manifestPath,
        '--json' => true,
    ]);
    $firstOutput = Artisan::output();
    $first = json_decode(trim($firstOutput), true);

    expect($firstExit)->toBe(0, $firstOutput);

    $codes = collect($first['invitations'])->pluck('code', 'role');

    expect($first['count'])->toBe(2)
        ->and($codes->get('maker'))->toStartWith('MAKE-')
        ->and($codes->get('checker'))->toStartWith('CHKR-')
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(2)
        ->and(data_get($first, 'funding.opening_reserve_minor'))->toBe(450_693)
        ->and(data_get($first, 'funding.account_funding_reserve_after_minor'))->toBe(430_693)
        ->and(data_get($first, 'funding.pay_code_reserve_after_minor'))->toBe(20_000)
        ->and(data_get($first, 'funding.provider_liquidity.status'))->toBe('ready')
        ->and(ProviderBalanceSnapshot::query()
            ->where('provider_code', 'netbank')
            ->where('balance_key', 'netbank_source_account')
            ->sole()
            ->available_balance_minor)->toBe(450_693)
        ->and(commissioningSystemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(430_693)
        ->and(commissioningSystemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(20_000);

    $secondExit = Artisan::call('x-change:commission:manifest', [
        '--manifest' => $manifestPath,
        '--json' => true,
    ]);
    $secondOutput = Artisan::output();
    $second = json_decode(trim($secondOutput), true);

    expect($secondExit)->toBe(0, $secondOutput)
        ->and(collect($second['invitations'])->pluck(
            'code',
            'role',
        )->all())->toBe($codes->all())
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(2)
        ->and(Voucher::query()->count())->toBe(2)
        ->and(commissioningSystemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(430_693)
        ->and(commissioningSystemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(20_000);


    Voucher::query()->get()->each(function (Voucher $voucher): void {
        expect((float) data_get($voucher->metadata, 'instructions.cash.amount'))->toBe(100.0)
            ->and(data_get(
                $voucher->metadata,
                'instructions.claim.default_outcome',
            ))->toBe('account_funding')
            ->and(data_get(
                $voucher->metadata,
                'instructions.claim.onboarding.mode',
            ))->toBe('required')
            ->and(data_get(
                $voucher->metadata,
                'instructions.metadata.custom.x_payout_commissioning.funding_instruction',
            ))->toBe('Client funds ready')
            ->and(data_get(
                $voucher->metadata,
                'treasury.pay_code_reservation.source_position_purpose',
            ))->toBe(TreasuryPositionPurpose::AccountFundingReserve->value);
    });
});


it('prints funded commissioning reserve feedback for operators', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestSystemAccountFundingReserve(
        $system,
        450_693,
        'x-payout-funded-commissioning-output',
    );
    $manifestPath = fundedCommissioningManifestPath([
        'invitation_amount: 100.00',
        'currency: PHP',
        'connection_reference: netbank-primary',
        'funding_source: treasury_account_funding_reserve',
        'authorization_reference: commissioning:x-payout:system-capital',
        'funding_instruction: Client funds ready',
    ]);
    mockCommissioningNetbankLiquidityRefresh(450_693);

    $humanExit = Artisan::call('x-change:commission:manifest', [
        '--manifest' => $manifestPath,
    ]);
    $humanOutput = Artisan::output();

    expect($humanExit)->toBe(0, $humanOutput)
        ->and($humanOutput)->toContain('Opening reserve:')
        ->and($humanOutput)->toContain('₱4,506.93')
        ->and($humanOutput)->toContain('After reserve:')
        ->and($humanOutput)->toContain('₱4,306.93')
        ->and($humanOutput)->toContain('Pay Code reserve:')
        ->and($humanOutput)->toContain('₱200.00')
        ->and($humanOutput)->toContain('Issuance guard:')
        ->and($humanOutput)->toContain('Ready (fresh provider liquidity)');
});

it('rejects funded commissioning invitations when provider liquidity cannot be refreshed', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestSystemAccountFundingReserve(
        $system,
        450_693,
        'x-payout-funded-commissioning-no-liquidity',
    );
    $manifestPath = fundedCommissioningManifestPath([
        'invitation_amount: 100.00',
        'currency: PHP',
        'connection_reference: netbank-primary',
        'funding_source: treasury_account_funding_reserve',
        'authorization_reference: commissioning:x-payout:system-capital',
        'funding_instruction: Client funds ready',
    ]);
    $readiness = Mockery::mock(CheckNetbankSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')
        ->once()
        ->with()
        ->andReturn([
            'enabled' => true,
            'ready' => false,
            'checked' => true,
        ]);
    app()->instance(CheckNetbankSourceAccountReadiness::class, $readiness);

    $this->artisan('x-change:commission:manifest', [
        '--manifest' => $manifestPath,
    ])
        ->expectsOutputToContain(
            'Funded commissioning invitations require a fresh provider liquidity snapshot before onboarding can be marked ready.',
        )
        ->assertFailed();

    expect(Voucher::query()->count())->toBe(0)
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(0)
        ->and(ProviderBalanceSnapshot::query()->count())->toBe(0)
        ->and(commissioningSystemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(450_693)
        ->and(commissioningSystemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(0);
});

it('rejects funded commissioning invitations without an authorization reference', function (): void {
    enableNetbankTreasuryForTests();
    $manifestPath = fundedCommissioningManifestPath([
        'invitation_amount: 100.00',
        'currency: PHP',
        'connection_reference: netbank-primary',
        'funding_source: treasury_account_funding_reserve',
    ]);

    $this->artisan('x-change:commission:manifest', [
        '--manifest' => $manifestPath,
    ])
        ->expectsOutputToContain(
            'Funded commissioning invitations require [onboarding.authorization_reference].',
        )
        ->assertFailed();
});

it('rejects funded commissioning invitations when the Account Funding Reserve cannot cover every role', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestSystemAccountFundingReserve(
        $system,
        15_000,
        'x-payout-funded-commissioning-insufficient',
    );
    $manifestPath = fundedCommissioningManifestPath([
        'invitation_amount: 100.00',
        'currency: PHP',
        'connection_reference: netbank-primary',
        'funding_source: treasury_account_funding_reserve',
        'authorization_reference: commissioning:x-payout:system-capital',
    ]);

    $this->artisan('x-change:commission:manifest', [
        '--manifest' => $manifestPath,
    ])
        ->expectsOutputToContain(
            'The system Account Funding Reserve does not cover funded commissioning invitations.',
        )
        ->assertFailed();

    expect(Voucher::query()->count())->toBe(0)
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(0)
        ->and(commissioningSystemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(15_000)
        ->and(commissioningSystemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(0);
});

/**
 * @param  list<string>  $onboardingLines
 */
function fundedCommissioningManifestPath(array $onboardingLines): string
{
    $path = storage_path('framework/testing/funded-commissioning-'.str()->uuid().'.yaml');

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, implode("\n", [
        'extends: x-change://commissioning/manifests/x-payout.default.yaml',
        'onboarding:',
        ...array_map(static fn (string $line): string => '  '.$line, $onboardingLines),
        '',
    ]));

    return $path;
}

function mockCommissioningNetbankLiquidityRefresh(int $availableMinor, int $times = 1): void
{
    $readiness = Mockery::mock(CheckNetbankSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')
        ->times($times)
        ->with()
        ->andReturn([
            'enabled' => true,
            'ready' => true,
            'checked' => true,
            'account_number_masked' => '********0019',
            'balance_minor' => $availableMinor,
            'available_balance_minor' => $availableMinor,
            'currency' => 'PHP',
            'as_of' => now()->subSecond()->toIso8601String(),
            'fetched_at' => now()->toIso8601String(),
            'message' => 'NetBank source account balance was refreshed.',
        ]);
    app()->instance(CheckNetbankSourceAccountReadiness::class, $readiness);
}

function commissioningSystemFundingPositionBalance(
    object $owner,
    TreasuryPositionPurpose $purpose,
): int {
    $principal = app(
        TreasuryPrincipalReferenceResolverContract::class,
    )->resolve($owner);

    return collect(
        app(TreasuryPositionReadModelContract::class)->forPrincipal($principal),
    )->first(
        static fn ($position): bool => $position->purpose === $purpose,
    )?->balanceMinor ?? 0;
}
