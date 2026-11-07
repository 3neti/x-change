<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LBHurtado\XChange\Actions\Disbursement\ReviewTreasuryPayoutOutbox;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;

function treasuryReviewFixture(): array
{
    $actor = actingAsTestUser();
    $voucher = issueVoucher();
    $id = DB::table('x_change_treasury_payout_outbox')->insertGetId([
        'voucher_id' => $voucher->id, 'status' => 'review', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $payout = DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->id, 'voucher_code' => $voucher->code, 'claim_type' => 'withdraw',
        'provider' => 'netbank', 'provider_reference' => 'review-ref', 'provider_transaction_id' => 'provider-123',
        'status' => 'unknown', 'needs_review' => true,
    ]);

    return [$actor, $id, $payout];
}

it('denies an unauthorized treasury payout review', function (): void {
    [$actor, $id] = treasuryReviewFixture();
    $service = Mockery::mock(DisbursementReconciliationContract::class);
    $service->shouldNotReceive('reconcile');
    expect(fn () => (new ReviewTreasuryPayoutOutbox($service))->handle($actor, $id, 'Checked with provider'))
        ->toThrow(AuthorizationException::class);
});

it('records a provider-verified terminal outcome without submitting another payout', function (): void {
    [$actor, $id, $payout] = treasuryReviewFixture();
    Gate::define('x-change.recovery.manage', fn ($user) => $user->is($actor));
    $service = Mockery::mock(DisbursementReconciliationContract::class);
    $service->shouldReceive('reconcile')->once()->andReturnUsing(function () use ($payout): array {
        $payout->forceFill(['status' => 'succeeded', 'needs_review' => false])->save();

        return ['resolved_status' => 'succeeded'];
    });
    (new ReviewTreasuryPayoutOutbox($service))->handle($actor, $id, 'Verified provider outcome');
    expect(DB::table('x_change_treasury_payout_outbox')->where('id', $id)->value('status'))->toBe('completed');
    $audit = DB::table('x_change_treasury_payout_review_audits')->sole();
    expect($audit->outcome)->toBe('succeeded')->and($audit->actor_id)->toBe((string) $actor->getKey())
        ->and($audit->reason_ciphertext)->not->toContain('Verified provider outcome');
});

it('keeps an uncertain payout in review and records the check', function (): void {
    [$actor, $id] = treasuryReviewFixture();
    Gate::define('x-change.recovery.manage', fn () => true);
    $service = Mockery::mock(DisbursementReconciliationContract::class);
    $service->shouldReceive('reconcile')->once()->andReturn(['resolved_status' => 'unknown']);
    (new ReviewTreasuryPayoutOutbox($service))->handle($actor, $id, 'Provider outcome is still unknown');
    expect(DB::table('x_change_treasury_payout_outbox')->where('id', $id)->value('status'))->toBe('review')
        ->and(DB::table('x_change_treasury_payout_review_audits')->value('outcome'))->toBe('unknown');
});

it('does not guess or resend when there is no provider transaction identifier', function (): void {
    [$actor, $id, $payout] = treasuryReviewFixture();
    Gate::define('x-change.recovery.manage', fn () => true);
    $payout->forceFill(['provider_transaction_id' => null])->save();
    $service = Mockery::mock(DisbursementReconciliationContract::class);
    $service->shouldNotReceive('reconcile');
    expect(fn () => (new ReviewTreasuryPayoutOutbox($service))->handle($actor, $id, 'No provider transaction found'))
        ->toThrow(RuntimeException::class, 'Provider transaction evidence');
    expect(DB::table('x_change_treasury_payout_outbox')->where('id', $id)->value('status'))->toBe('review');
});

it('records an operator investigation without closing or resending an unidentified payout', function (): void {
    [$actor, $id, $payout] = treasuryReviewFixture();
    Gate::define('x-change.recovery.manage', fn () => true);
    $payout->forceFill(['provider_transaction_id' => null])->save();
    $service = Mockery::mock(DisbursementReconciliationContract::class);
    $service->shouldNotReceive('reconcile');
    (new ReviewTreasuryPayoutOutbox($service))->recordInvestigation($actor, $id, 'Provider investigation opened');
    expect(DB::table('x_change_treasury_payout_outbox')->where('id', $id)->value('status'))->toBe('review')
        ->and(DB::table('x_change_treasury_payout_review_audits')->value('outcome'))->toBe('investigating');
});

it('requires operator authorization for the treasury review endpoint', function (): void {
    [$actor, $id] = treasuryReviewFixture();
    $this->actingAs($actor)->post(route('x-change.operations.recovery.treasury-payout.review', ['outbox' => $id]), ['reason' => 'Provider status checked'])
        ->assertForbidden();
});

it('allows an authorized operator to check provider status through the review endpoint', function (): void {
    [$actor, $id, $payout] = treasuryReviewFixture();
    Gate::define('x-change.recovery.manage', fn ($user) => $user->is($actor));
    $service = Mockery::mock(DisbursementReconciliationContract::class);
    $service->shouldReceive('reconcile')->once()->andReturnUsing(function () use ($payout): array {
        $payout->forceFill(['status' => 'succeeded', 'needs_review' => false])->save();

        return ['resolved_status' => 'succeeded'];
    });
    app()->instance(DisbursementReconciliationContract::class, $service);
    $this->actingAs($actor)->post(route('x-change.operations.recovery.treasury-payout.review', ['outbox' => $id]), ['reason' => 'Provider status checked'])->assertRedirect();
    expect(DB::table('x_change_treasury_payout_outbox')->where('id', $id)->value('status'))->toBe('completed');
});

it('allows only an authorized operator to record an unresolved investigation', function (): void {
    [$actor, $id, $payout] = treasuryReviewFixture();
    $payout->forceFill(['provider_transaction_id' => null])->save();
    $url = route('x-change.operations.recovery.treasury-payout.investigate', ['outbox' => $id]);
    $this->actingAs($actor)->post($url, ['reason' => 'Provider investigation opened'])->assertForbidden();
    Gate::define('x-change.recovery.manage', fn ($user) => $user->is($actor));
    $this->post($url, ['reason' => 'Provider investigation opened'])->assertRedirect();
    expect(DB::table('x_change_treasury_payout_review_audits')->value('outcome'))->toBe('investigating')
        ->and(DB::table('x_change_treasury_payout_outbox')->where('id', $id)->value('status'))->toBe('review');
});
