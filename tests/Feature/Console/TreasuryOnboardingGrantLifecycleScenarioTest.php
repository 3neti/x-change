<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Tests\Fakes\User;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function (): void {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.onboarding.voucher.require_otp', false);
    config()->set('queue.default', 'sync');
});

it('issues one replay-safe browser grant from the system Account Funding Reserve', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestSystemAccountFundingReserve(
        $system,
        1_802,
        'treasury-onboarding-grant-browser',
    );

    $first = runTreasuryOnboardingGrant([
        '--no-claim' => true,
        '--run-reference' => 'treasury-onboarding-grant-sofia-20260730-001',
        '--json' => true,
    ]);
    $this->travel(2)->minutes();
    $replay = runTreasuryOnboardingGrant([
        '--no-claim' => true,
        '--run-reference' => 'treasury-onboarding-grant-sofia-20260730-001',
        '--json' => true,
    ]);

    expect($first['exit_code'])->toBe(0, $first['rendered'])
        ->and($first['payload']['success'])->toBeTrue()
        ->and(data_get($first, 'payload.pay_code.claimed'))->toBeFalse()
        ->and(data_get($first, 'payload.pay_code.execution_driver'))
        ->toBe('onboarding_account_provisioning')
        ->and(data_get($first, 'payload.pay_code.default_outcome'))
        ->toBe('account_funding')
        ->and(data_get($first, 'payload.recipient.mobile'))->toEndWith('6237')
        ->and(data_get($first, 'payload.accounting.system_after.account_funding_reserve_minor'))
        ->toBe(302)
        ->and(data_get($first, 'payload.accounting.system_after.pay_code_reserve_minor'))
        ->toBe(1_500)
        ->and(data_get($first, 'payload.controls.journal_events'))->toBe([
            'account_funding.pay_code.issued',
        ])
        ->and(data_get($replay, 'payload.pay_code.code'))
        ->toBe(data_get($first, 'payload.pay_code.code'))
        ->and(treasuryOnboardingGrantPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(302)
        ->and(treasuryOnboardingGrantPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(1_500)
        ->and(Voucher::query()->count())->toBe(1);
});

it('claims the grant into the newly provisioned Sofia Account without changing Inventory', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestSystemAccountFundingReserve(
        $system,
        1_802,
        'treasury-onboarding-grant-claim',
    );
    $inventoryBefore = (int) TreasuryInventory::query()
        ->sum('balance_minor');

    $run = runTreasuryOnboardingGrant([
        '--run-reference' => 'treasury-onboarding-grant-sofia-claim-20260730-001',
        '--json' => true,
    ]);
    $replay = runTreasuryOnboardingGrant([
        '--no-claim' => true,
        '--run-reference' => 'treasury-onboarding-grant-sofia-claim-20260730-001',
        '--json' => true,
    ]);
    $sofia = User::query()
        ->where('mobile', '639399236237')
        ->sole();

    expect($run['exit_code'])->toBe(0, $run['rendered'])
        ->and($run['payload']['success'])->toBeTrue()
        ->and(data_get($run, 'payload.pay_code.claimed'))->toBeTrue()
        ->and(data_get($run, 'payload.recipient.name'))->toBe('Sofia Hurtado')
        ->and(data_get($run, 'payload.recipient.email'))->toBe('sofia@hurtado.ph')
        ->and(data_get($run, 'payload.recipient.positions.client_funds_minor'))
        ->toBe(1_500)
        ->and(data_get($run, 'payload.accounting.system_after.account_funding_reserve_minor'))
        ->toBe(302)
        ->and(data_get($run, 'payload.accounting.system_after.pay_code_reserve_minor'))
        ->toBe(0)
        ->and(data_get($run, 'payload.accounting.inventory_unchanged'))->toBeTrue()
        ->and(TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBefore)
        ->and(treasuryOnboardingGrantPositionBalance(
            $sofia,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe(1_500)
        ->and(data_get($run, 'payload.controls.provider_calls'))->toBeFalse()
        ->and(data_get($run, 'payload.controls.provider_attempt_count'))->toBe(0)
        ->and(data_get($run, 'payload.controls.claim_count'))->toBe(1)
        ->and(data_get($replay, 'payload.pay_code.claimed'))->toBeTrue()
        ->and(data_get($replay, 'payload.recipient.account_id'))->toBe($sofia->getKey())
        ->and(data_get($replay, 'payload.recipient.positions.client_funds_minor'))
        ->toBe(1_500)
        ->and(data_get($replay, 'payload.controls.claim_count'))->toBe(1)
        ->and(data_get($run, 'payload.controls.journal_events'))->toBe([
            'account_funding.pay_code.issued',
            'account_funding.pay_code.outcome_selected',
            'account_funding.pay_code.applied',
        ])
        ->and(DisbursementReconciliation::query()->count())->toBe(0);

    fakePayoutProvider()->assertNoDisbursementAttempted();
});

it('requires a stable run reference before reserving funds', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestSystemAccountFundingReserve(
        $system,
        1_802,
        'treasury-onboarding-grant-missing-reference',
    );

    $run = runTreasuryOnboardingGrant(['--json' => true]);

    expect($run['exit_code'])->toBe(1)
        ->and($run['payload']['success'])->toBeFalse()
        ->and($run['payload']['message'])
        ->toBe('Treasury onboarding grants require a stable --run-reference.')
        ->and(Voucher::query()->count())->toBe(0)
        ->and(treasuryOnboardingGrantPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(1_802);
});

/**
 * @param  array<string, mixed>  $options
 * @return array{exit_code:int,rendered:string,payload:array<string,mixed>}
 */
function runTreasuryOnboardingGrant(array $options): array
{
    $output = new BufferedOutput;
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'treasury_onboarding_grant',
        ...$options,
    ], $output);
    $rendered = $output->fetch();

    return [
        'exit_code' => $exitCode,
        'rendered' => $rendered,
        'payload' => json_decode($rendered, true, flags: JSON_THROW_ON_ERROR),
    ];
}

function treasuryOnboardingGrantPositionBalance(
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
