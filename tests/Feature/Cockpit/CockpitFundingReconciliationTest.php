<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingReconciliationRequest;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Tests\Fakes\User;

it('requires distinct maker and checker operators before compensating a verified posting', function () {
    allowTreasuryTestOperators();
    enableNetbankTreasuryForTests();

    $maker = actingAsTestUser(0);
    $makerWallet = $maker->wallet()->where('slug', 'platform')->firstOrFail();
    $case = cockpitVerifiedPostingCase($maker, $makerWallet);

    $this->post(route('x-change.cockpit.funding.suspense.reconciliation-requests.store', [
        'case' => $case->reference,
    ]), [
        'action' => 'compensate_verified_posting',
    ])->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas('funding_notice', 'Reconciliation request submitted for independent approval.');

    $reconciliation = FundingReconciliationRequest::query()->sole();

    expect($reconciliation->status)->toBe('pending_approval')
        ->and($reconciliation->requested_by_type)->toBe($maker::class)
        ->and($reconciliation->requested_by_id)->toBe((string) $maker->getAuthIdentifier())
        ->and((int) $makerWallet->refresh()->balanceInt)->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0);

    $this->withoutExceptionHandling();

    expect(fn () => $this->from(route('x-change.cockpit.funding.index'))
        ->post(route('x-change.cockpit.funding.reconciliations.approve', [
            'reconciliationRequest' => $reconciliation->reference,
        ])))->toThrow(
            ValidationException::class,
            'The reconciliation requester cannot approve their own request.',
        );

    $this->withExceptionHandling();

    expect($reconciliation->refresh()->status)->toBe('pending_approval')
        ->and((int) $makerWallet->refresh()->balanceInt)->toBe(0);

    $checker = User::query()->create([
        'name' => 'Independent Checker',
        'email' => 'checker+'.Str::uuid().'@example.com',
        'password' => bcrypt('password'),
    ]);
    $this->actingAs($checker);

    $this->post(route('x-change.cockpit.funding.reconciliations.approve', [
        'reconciliationRequest' => $reconciliation->reference,
    ]))->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas('funding_notice', 'Reconciliation approved and executed.');

    expect($reconciliation->refresh()->status)->toBe('executed')
        ->and($reconciliation->approved_by_type)->toBe($checker::class)
        ->and($reconciliation->approved_by_id)->toBe((string) $checker->getAuthIdentifier())
        ->and($case->refresh()->status)->toBe('resolved')
        ->and(FundingSettlement::query()->count())->toBe(1)
        ->and((int) $makerWallet->refresh()->balanceInt)->toBe(0)
        ->and(app(AccountBalanceReadModelContract::class)->providerBalanceMinor(
            $maker,
            'netbank',
            'PHP',
        ))->toBe(24_950);
});

it('prohibits operator supplied amounts and provider evidence identifiers', function () {
    allowTreasuryTestOperators();
    $maker = actingAsTestUser(0);
    $wallet = $maker->wallet()->where('slug', 'platform')->firstOrFail();
    $case = cockpitVerifiedPostingCase($maker, $wallet);

    $this->from(route('x-change.cockpit.funding.index'))
        ->post(route('x-change.cockpit.funding.suspense.reconciliation-requests.store', [
            'case' => $case->reference,
        ]), [
            'action' => 'compensate_verified_posting',
            'amount_minor' => 9_999_999,
            'provider_observation_id' => 12345,
        ])
        ->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHasErrors(['amount_minor', 'provider_observation_id']);

    expect(FundingReconciliationRequest::query()->count())->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0);
});

it('forbids ordinary Account holders from Treasury reconciliation controls', function () {
    enableNetbankTreasuryForTests();
    $accountHolder = actingAsTestUser(0);
    $wallet = $accountHolder->wallet()->where('slug', 'platform')->firstOrFail();
    $case = cockpitVerifiedPostingCase($accountHolder, $wallet);

    $this->post(route('x-change.cockpit.funding.suspense.reconciliation-requests.store', [
        'case' => $case->reference,
    ]), [
        'action' => 'compensate_verified_posting',
    ])->assertForbidden();

    expect(FundingReconciliationRequest::query()->count())->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0);
});

function allowTreasuryTestOperators(): void
{
    app()->instance(
        CockpitTreasuryAccessContract::class,
        new class implements CockpitTreasuryAccessContract
        {
            public function canViewTreasuryControls(Authenticatable $actor): bool
            {
                return true;
            }

            public function canRefreshProviderLiquidity(Authenticatable $actor): bool
            {
                return true;
            }

            public function canManageTreasuryReconciliation(Authenticatable $actor): bool
            {
                return true;
            }

            public function authorizeProviderLiquidityRefresh(Authenticatable $actor): void {}

            public function authorizeTreasuryReconciliation(Authenticatable $actor): void {}
        },
    );
}

function cockpitVerifiedPostingCase(User $operator, Wallet $wallet): FundingSuspenseCase
{
    $transactionId = 'NB-'.Str::upper(Str::random(12));
    $observation = ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', $transactionId),
        'provider_code' => 'netbank',
        'provider_transaction_id' => $transactionId,
        'provider_operation_id' => 'OP-'.$transactionId,
        'request_id' => 'REQ-'.$transactionId,
        'funding_address' => '001234567890',
        'provider_account_reference' => 'corporate-vca',
        'gross_amount_minor' => 25_000,
        'fee_amount_minor' => 50,
        'net_amount_minor' => 24_950,
        'currency' => 'PHP',
        'provider_status' => 'settled',
        'occurred_at' => now()->subMinute(),
        'settled_at' => now(),
        'verification_source' => 'transaction_history',
        'payload_hash' => hash('sha256', 'payload-'.$transactionId),
        'metadata' => ['destination_verified' => true],
    ]);
    $idempotency = (string) Str::uuid();
    $intent = FundingIntent::query()->create([
        'account_reference' => 'wallet:'.$wallet->uuid,
        'provider_code' => 'netbank',
        'expected_amount_minor' => 25_000,
        'currency' => 'PHP',
        'status' => FundingIntentStatus::Verified,
        'version' => 4,
        'idempotency_key_hash' => hash('sha256', $idempotency),
        'idempotency_fingerprint' => hash('sha256', 'fingerprint-'.$idempotency),
        'created_by_type' => $operator::class,
        'created_by_id' => (string) $operator->getAuthIdentifier(),
        'provider_reference' => 'sha256:'.hash('sha256', $idempotency),
        'provider_request_id' => $observation->request_id,
        'funding_address_ciphertext' => $observation->funding_address,
        'funding_address_hash' => hash('sha256', (string) $observation->funding_address),
        'matched_observation_id' => $observation->getKey(),
        'provider_transaction_id' => $observation->provider_transaction_id,
        'instructions_created_at' => now()->subMinutes(2),
        'evidence_received_at' => now()->subMinute(),
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(30),
        'metadata' => ['source' => 'test'],
    ]);

    return FundingSuspenseCase::query()->create([
        'case_key' => hash('sha256', 'case-'.$idempotency),
        'funding_intent_id' => $intent->getKey(),
        'provider_funding_observation_id' => $observation->getKey(),
        'provider_code' => 'netbank',
        'reason_code' => 'verified_posting_interrupted',
        'status' => 'open',
        'details' => [],
        'opened_at' => now(),
    ]);
}
