<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Contracts\VoucherClaimOutcomeHandlerContract;
use LBHurtado\XChange\Exceptions\VoucherClaimOutcomeConflict;
use LBHurtado\XChange\Models\VoucherClaimOutcomeSelection;
use LBHurtado\XChange\Services\Claim\VoucherClaimantReference;
use LBHurtado\XChange\Services\Claim\VoucherClaimOutcomeRegistry;
use LBHurtado\XChange\Services\Claim\VoucherClaimPolicyResolver;
use LBHurtado\XChange\Tests\Fakes\User;

it('persists one atomic outcome selection and permits an idempotent replay', function () {
    $claimant = actingAsTestUser();
    $voucher = outcomeDispatcherVoucher([
        'claim' => [
            'outcomes' => [
                ['key' => 'provider_disbursement'],
                ['key' => 'account_funding'],
            ],
            'selection' => 'claimant',
            'consumption' => 'one_of',
            'default_outcome' => 'provider_disbursement',
        ],
    ]);
    $dispatcher = outcomeDispatcher();

    $first = $dispatcher->handle(
        $voucher,
        'account_funding',
        ['request' => 'first'],
        $claimant,
    );
    $replay = $dispatcher->handle(
        $voucher,
        'account_funding',
        ['request' => 'replay'],
        $claimant,
    );

    expect($first)->toBe('account_funding:first')
        ->and($replay)->toBe('account_funding:replay')
        ->and(VoucherClaimOutcomeSelection::query()->count())->toBe(1)
        ->and(VoucherClaimOutcomeSelection::query()->sole()->outcome_key)
        ->toBe('account_funding');
});

it('rejects a different outcome after one-of selection', function () {
    $claimant = actingAsTestUser();
    $voucher = outcomeDispatcherVoucher([
        'claim' => [
            'outcomes' => [
                ['key' => 'provider_disbursement'],
                ['key' => 'account_funding'],
            ],
            'selection' => 'claimant',
            'consumption' => 'one_of',
        ],
    ]);
    $dispatcher = outcomeDispatcher();
    $dispatcher->handle($voucher, 'account_funding', [], $claimant);

    expect(fn () => $dispatcher->handle(
        $voucher,
        'provider_disbursement',
        [],
        $claimant,
    ))->toThrow(
        VoucherClaimOutcomeConflict::class,
        'This Voucher has already selected a different claim outcome.',
    )->and(VoucherClaimOutcomeSelection::query()->count())->toBe(1);
});

it('enforces recipient binding before selecting an outcome', function () {
    $recipient = actingAsTestUser();
    $other = User::query()->create([
        'name' => 'Other Claimant',
        'email' => 'other-claimant@example.test',
        'password' => 'password',
    ]);
    $recipientReference = app(VoucherClaimantReference::class)->for($recipient);
    $voucher = outcomeDispatcherVoucher([
        'claim' => [
            'outcomes' => [['key' => 'account_funding']],
            'selection' => 'server',
            'consumption' => 'one_of',
            'default_outcome' => 'account_funding',
            'claimant' => [
                'mode' => 'recipient',
                'reference' => $recipientReference,
            ],
        ],
    ]);

    expect(fn () => outcomeDispatcher()->handle(
        $voucher,
        'account_funding',
        [],
        $other,
    ))->toThrow(
        VoucherClaimOutcomeConflict::class,
        'This Voucher belongs to another recipient.',
    )->and(VoucherClaimOutcomeSelection::query()->count())->toBe(0);
});

it('rejects unregistered and mismatched outcome handlers', function () {
    $registry = new VoucherClaimOutcomeRegistry(
        app(Container::class),
        ['account_funding' => FakeProviderDisbursementOutcomeHandler::class],
    );

    expect(fn () => $registry->handler('payment'))->toThrow(
        VoucherClaimOutcomeConflict::class,
        'Voucher claim outcome [payment] is not registered.',
    )->and(fn () => $registry->handler('account_funding'))->toThrow(
        VoucherClaimOutcomeConflict::class,
        'is invalid.',
    );
});

function outcomeDispatcher(): DispatchVoucherClaimOutcome
{
    $registry = new VoucherClaimOutcomeRegistry(
        app(Container::class),
        [
            'provider_disbursement' => FakeProviderDisbursementOutcomeHandler::class,
            'account_funding' => FakeAccountFundingOutcomeHandler::class,
        ],
    );

    return new DispatchVoucherClaimOutcome(
        app(VoucherClaimPolicyResolver::class),
        $registry,
        app(VoucherClaimantReference::class),
    );
}

/**
 * @param  array<string, mixed>  $instructionOverrides
 */
function outcomeDispatcherVoucher(array $instructionOverrides): Voucher
{
    return Voucher::query()->forceCreate([
        'code' => 'OUT-'.mb_strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8)),
        'metadata' => [
            'instructions' => array_replace_recursive([
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                    'validation' => ['country' => 'PH'],
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
                'count' => 1,
                'prefix' => 'OUT',
                'mask' => '****',
            ], $instructionOverrides),
        ],
        'voucher_type' => 'redeemable',
        'state' => 'active',
        'expires_at' => now()->addHour(),
    ]);
}

final class FakeAccountFundingOutcomeHandler implements VoucherClaimOutcomeHandlerContract
{
    public function key(): string
    {
        return 'account_funding';
    }

    public function execute(
        Voucher $voucher,
        array $payload,
        ?Authenticatable $claimant = null,
    ): mixed {
        return 'account_funding:'.($payload['request'] ?? 'none');
    }
}

final class FakeProviderDisbursementOutcomeHandler implements VoucherClaimOutcomeHandlerContract
{
    public function key(): string
    {
        return 'provider_disbursement';
    }

    public function execute(
        Voucher $voucher,
        array $payload,
        ?Authenticatable $claimant = null,
    ): mixed {
        return 'provider_disbursement';
    }
}
