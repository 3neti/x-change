<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Data\Funding\SystemAccountFundingPayCodeAuthorizationData;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Services\Funding\ConfigSystemAccountFundingPayCodeAuthorization;
use LBHurtado\XChange\Tests\Fakes\User;

afterEach(function (): void {
    Date::useDefault();
});

it('previews without mutation then issues and replays one recipient-bound code', function (): void {
    Date::useClass(CarbonImmutable::class);
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);
    config()->set('auth.providers.users.model', User::class);
    config()->set(
        'x-change.funding.system_pay_codes.enabled',
        true,
    );

    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$system->wallet->uuid,
        provider: 'netbank',
        amountMinor: 500_000,
        currency: 'PHP',
        evidenceReference: 'netbank:system-command:1001',
    );

    $arguments = [
        '--amount' => '1250.00',
        '--recipient-id' => (string) $recipient->getKey(),
        '--connection' => 'netbank-primary',
        '--reference' => 'system-command-account-funding-1001',
        '--evidence-reference' => 'evidence:system-command:1001',
        '--json' => true,
    ];

    $previewExit = Artisan::call(
        'x-change:funding:issue-pay-code',
        $arguments,
    );
    $previewOutput = Artisan::output();
    $preview = json_decode($previewOutput, true);

    expect($previewExit)->toBe(Command::SUCCESS, $previewOutput)
        ->and($preview['status'])->toBe('preview_ready')
        ->and($preview['mode'])->toBe('preview')
        ->and($preview['amount']['minor'])->toBe(125_000)
        ->and($preview['positions']['before']['client_funds_minor'])
        ->toBe(500_000)
        ->and($preview['positions']['after']['client_funds_minor'])
        ->toBe(375_000)
        ->and($preview['positions']['after']['pay_code_reserve_minor'])
        ->toBe(125_000)
        ->and($preview['provider_calls'])->toBeFalse()
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(0)
        ->and(Voucher::query()->count())->toBe(0);

    $firstExit = Artisan::call(
        'x-change:funding:issue-pay-code',
        [...$arguments, '--commit' => true],
    );
    $firstOutput = Artisan::output();
    $first = json_decode($firstOutput, true);

    expect($firstExit)->toBe(Command::SUCCESS, $firstOutput)
        ->and($first['status'])->toBe('issued')
        ->and($first['recipient'])->toBe([
            'mode' => 'bound',
            'id' => $recipient->getKey(),
        ])
        ->and($first['pay_code']['code'])->not->toBeEmpty()
        ->and($first['positions']['after']['client_funds_minor'])
        ->toBe(375_000)
        ->and($first['positions']['after']['pay_code_reserve_minor'])
        ->toBe(125_000);

    $replayExit = Artisan::call(
        'x-change:funding:issue-pay-code',
        [...$arguments, '--commit' => true],
    );
    $replayOutput = Artisan::output();
    $replay = json_decode($replayOutput, true);

    expect($replayExit)->toBe(Command::SUCCESS, $replayOutput)
        ->and($replay['status'])->toBe('replayed')
        ->and($replay['pay_code']['code'])
        ->toBe($first['pay_code']['code'])
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(1)
        ->and(Voucher::query()->count())->toBe(1)
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe(375_000)
        ->and(systemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(125_000);

    fakePayoutProvider()->assertNoDisbursementAttempted();
});

it('reports insufficient system funds without issuing a code', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);
    config()->set('auth.providers.users.model', User::class);

    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$system->wallet->uuid,
        provider: 'netbank',
        amountMinor: 10_000,
        currency: 'PHP',
        evidenceReference: 'netbank:system-command:1002',
    );

    $exitCode = Artisan::call(
        'x-change:funding:issue-pay-code',
        [
            '--amount' => '101.00',
            '--recipient-id' => (string) $recipient->getKey(),
            '--connection' => 'netbank-primary',
            '--reference' => 'system-command-account-funding-1002',
            '--json' => true,
        ],
    );
    $output = Artisan::output();
    $payload = json_decode($output, true);

    expect($exitCode)->toBe(Command::FAILURE, $output)
        ->and($payload['status'])->toBe('insufficient_system_funds')
        ->and($payload['success'])->toBeFalse()
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(0)
        ->and(Voucher::query()->count())->toBe(0);
});

it('requires every production control before authorizing a commit', function (): void {
    config()->set(
        'x-change.funding.system_pay_codes.maximum_amount_minor',
        500_000,
    );
    config()->set(
        'x-change.funding.system_pay_codes.enabled',
        true,
    );
    config()->set(
        'x-change.funding.system_pay_codes.allow_production',
        false,
    );
    config()->set(
        'x-change.funding.system_pay_codes.bearer_enabled',
        true,
    );

    $application = Mockery::mock(Application::class);
    $application->shouldReceive('environment')
        ->with('production')
        ->andReturnTrue();
    $authorization = new ConfigSystemAccountFundingPayCodeAuthorization(
        $application,
    );
    $base = [
        'amountMinor' => 100_000,
        'connectionReference' => 'netbank-primary',
        'bearer' => false,
        'commit' => true,
        'productionConfirmed' => true,
        'idempotencyReference' => 'production-account-funding-1001',
        'evidenceReference' => 'evidence:production:1001',
        'authorizationReference' => 'approval:production:1001',
    ];

    expect(fn () => $authorization->authorize(
        new SystemAccountFundingPayCodeAuthorizationData(...$base),
    ))->toThrow(
        RuntimeException::class,
        'Production System Account Funding Pay Code issuance is disabled.',
    );

    config()->set(
        'x-change.funding.system_pay_codes.allow_production',
        true,
    );

    expect(fn () => $authorization->authorize(
        new SystemAccountFundingPayCodeAuthorizationData(
            ...array_replace($base, ['productionConfirmed' => false]),
        ),
    ))->toThrow(
        RuntimeException::class,
        'requires --confirm-production',
    );

    expect(fn () => $authorization->authorize(
        new SystemAccountFundingPayCodeAuthorizationData(
            ...array_replace($base, ['authorizationReference' => null]),
        ),
    ))->toThrow(
        RuntimeException::class,
        'requires evidence and authorization references',
    );

    $authorization->authorize(
        new SystemAccountFundingPayCodeAuthorizationData(...$base),
    );

    expect(true)->toBeTrue();
});
