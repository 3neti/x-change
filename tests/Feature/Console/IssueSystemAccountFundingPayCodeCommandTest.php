<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Funding\SystemAccountFundingPayCodeAuthorizationData;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Services\Funding\ConfigSystemAccountFundingPayCodeAuthorization;
use LBHurtado\XChange\Tests\Fakes\User;

afterEach(function (): void {
    Date::setTestNow();
    Date::useDefault();
});

it('previews without mutation then issues and replays one recipient-bound code', function (): void {
    Date::useClass(CarbonImmutable::class);
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);
    $recipient->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ])->save();
    config()->set('auth.providers.users.model', User::class);
    config()->set(
        'x-change.funding.system_pay_codes.enabled',
        true,
    );

    fundTestSystemAccountFundingReserve(
        $system,
        500_000,
        'system-command-1001',
    );

    $arguments = [
        '--amount' => '1250.00',
        '--recipient-mobile' => '0917 301 1987',
        '--connection' => 'netbank-primary',
        '--reference' => 'system-command-account-funding-1001',
        '--evidence-reference' => 'evidence:system-command:1001',
        '--authorization-reference' => 'authorization:system-command:1001',
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
        ->and($preview['positions']['before']['account_funding_reserve_minor'])
        ->toBe(500_000)
        ->and($preview['positions']['after']['account_funding_reserve_minor'])
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
        ->and($firstOutput)->not->toContain('0917 301 1987')
        ->and($firstOutput)->not->toContain('639173011987')
        ->and($first['pay_code']['code'])->not->toBeEmpty()
        ->and($first['positions']['after']['account_funding_reserve_minor'])
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
        ->and(commandSystemFundingPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(375_000)
        ->and(commandSystemFundingPositionBalance(
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

    fundTestSystemAccountFundingReserve(
        $system,
        10_000,
        'system-command-1002',
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

it('fails closed for an unverified or ambiguous mobile recipient', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $unverified = actingAsTestUser(0);
    $unverified->forceFill([
        'mobile' => '639171111111',
        'mobile_verified_at' => null,
    ])->save();
    config()->set('auth.providers.users.model', User::class);
    fundTestSystemAccountFundingReserve(
        $system,
        50_000,
        'system-command-mobile-guards',
    );
    $base = [
        '--amount' => '100.00',
        '--connection' => 'netbank-primary',
        '--reference' => 'system-command-mobile-guards',
        '--json' => true,
    ];

    $unverifiedExit = Artisan::call(
        'x-change:funding:issue-pay-code',
        [
            ...$base,
            '--recipient-mobile' => '0917 111 1111',
        ],
    );
    $unverifiedOutput = Artisan::output();
    $unverifiedPayload = json_decode($unverifiedOutput, true);

    expect($unverifiedExit)->toBe(Command::FAILURE, $unverifiedOutput)
        ->and($unverifiedPayload['status'])->toBe('rejected')
        ->and($unverifiedPayload['message'])
        ->toBe(
            'The Account Funding recipient mobile must be verified before issuance.',
        )
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(0);

    foreach (['639172222222', '09172222222'] as $index => $mobile) {
        $recipient = User::query()->create([
            'name' => 'Ambiguous Mobile Recipient '.$index,
            'email' => 'ambiguous-mobile-'.$index.'@example.test',
            'password' => 'password',
        ]);
        $recipient->forceFill([
            'mobile' => $mobile,
            'mobile_verified_at' => now(),
        ])->save();
    }

    $ambiguousExit = Artisan::call(
        'x-change:funding:issue-pay-code',
        [
            ...$base,
            '--recipient-mobile' => '+63 917 222 2222',
        ],
    );
    $ambiguousOutput = Artisan::output();
    $ambiguousPayload = json_decode($ambiguousOutput, true);

    expect($ambiguousExit)->toBe(Command::FAILURE, $ambiguousOutput)
        ->and($ambiguousPayload['status'])->toBe('rejected')
        ->and($ambiguousPayload['message'])
        ->toBe(
            'The verified Account Funding recipient could not be resolved uniquely.',
        )
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(0);
});

it('rejects conflicting recipient selectors without exposing the mobile', function (): void {
    $recipient = actingAsTestUser(0);
    $recipient->forceFill([
        'mobile' => '639173333333',
        'mobile_verified_at' => now(),
    ])->save();
    config()->set('auth.providers.users.model', User::class);

    $exitCode = Artisan::call(
        'x-change:funding:issue-pay-code',
        [
            '--amount' => '100.00',
            '--recipient-mobile' => '0917 333 3333',
            '--recipient-id' => (string) $recipient->getKey(),
            '--connection' => 'netbank-primary',
            '--reference' => 'system-command-selector-conflict',
            '--json' => true,
        ],
    );
    $output = Artisan::output();
    $payload = json_decode($output, true);

    expect($exitCode)->toBe(Command::FAILURE, $output)
        ->and($payload['status'])->toBe('rejected')
        ->and($payload['message'])
        ->toBe('Use either --recipient-mobile or --recipient-id, not both.')
        ->and($output)->not->toContain('0917 333 3333')
        ->and($output)->not->toContain('639173333333')
        ->and(SystemAccountFundingPayCodeIssuance::query()->count())->toBe(0);
});

it('guides an interactive issuance with safe defaults and a generated reference', function (): void {
    Date::useClass(CarbonImmutable::class);
    Date::setTestNow('2026-07-26 10:15:30');
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);
    $recipient->forceFill([
        'mobile' => '639174444444',
        'mobile_verified_at' => now(),
    ])->save();
    config()->set('auth.providers.users.model', User::class);
    config()->set(
        'x-change.funding.system_pay_codes.enabled',
        true,
    );

    fundTestSystemAccountFundingReserve(
        $system,
        50_000,
        'system-command-interactive-1001',
    );

    $this->artisan('x-change:funding:issue-pay-code')
        ->expectsQuestion(
            'Treasury connection',
            'netbank-primary',
        )
        ->expectsQuestion('Recipient mode', 'Recipient-bound')
        ->expectsQuestion(
            'Recipient verified mobile',
            '+63 917 444 4444',
        )
        ->expectsQuestion('Exact amount', null)
        ->expectsQuestion('Idempotency reference', null)
        ->expectsQuestion('Expiry (ISO-8601)', null)
        ->expectsConfirmation(
            'Reserve system funds and issue now?',
            'yes',
        )
        ->expectsQuestion(
            'Backing evidence reference',
            'evidence:interactive:1001',
        )
        ->expectsQuestion(
            'Authorization reference',
            'authorization:interactive:1001',
        )
        ->expectsOutputToContain(
            'reference: account-funding-user-'
            .$recipient->getKey()
            .'-20260726-101530-',
        )
        ->assertSuccessful();

    $issuance = SystemAccountFundingPayCodeIssuance::query()->sole();

    expect($issuance->amount_minor)->toBe(10_000)
        ->and($issuance->recipient_id)->toBe(
            (string) $recipient->getKey(),
        )
        ->and($issuance->evidence_reference)
        ->toBe('evidence:interactive:1001')
        ->and($issuance->authorization_reference)
        ->toBe('authorization:interactive:1001')
        ->and($issuance->status)->toBe('issued')
        ->and($issuance->voucher)->toBeInstanceOf(Voucher::class);
});

it('requires evidence and authorization references for every committed issuance', function (): void {
    config()->set(
        'x-change.funding.system_pay_codes.enabled',
        true,
    );
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('environment')
        ->with('production')
        ->andReturnFalse();
    $authorization = new ConfigSystemAccountFundingPayCodeAuthorization(
        $application,
    );
    $base = [
        'amountMinor' => 100_000,
        'connectionReference' => 'netbank-primary',
        'bearer' => false,
        'commit' => true,
        'productionConfirmed' => false,
        'idempotencyReference' => 'local-account-funding-1001',
        'evidenceReference' => 'evidence:local:1001',
        'authorizationReference' => 'approval:local:1001',
    ];

    expect(fn () => $authorization->authorize(
        new SystemAccountFundingPayCodeAuthorizationData(
            ...array_replace($base, ['evidenceReference' => null]),
        ),
    ))->toThrow(
        RuntimeException::class,
        'Committed issuance requires evidence and authorization references.',
    );

    $authorization->authorize(
        new SystemAccountFundingPayCodeAuthorizationData(...$base),
    );

    expect(true)->toBeTrue();
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
        'Committed issuance requires evidence and authorization references.',
    );

    $authorization->authorize(
        new SystemAccountFundingPayCodeAuthorizationData(...$base),
    );

    expect(true)->toBeTrue();
});

function commandSystemFundingPositionBalance(
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
