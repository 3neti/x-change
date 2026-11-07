<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LBHurtado\XChange\Actions\Disbursement\CorrectPayoutLocality;
use LBHurtado\XChange\Models\DisbursementReconciliation;

function correctionAddress(): array
{
    return ['address1' => 'Corrected Barangay', 'city' => 'Manila', 'country' => 'PH', 'postal_code' => '1000'];
}

it('denies locality corrections unless explicitly authorized', function (): void {
    $actor = actingAsTestUser();
    $row = DisbursementReconciliation::query()->create(['voucher_code' => 'NOACCESS', 'status' => 'failed']);
    expect(fn () => app(CorrectPayoutLocality::class)->handle($actor, $row, 'recipient', correctionAddress(), 'Correct locality'))
        ->toThrow(AuthorizationException::class);
    expect(DB::table('x_change_payout_locality_revisions')->count())->toBe(0);
});

it('audits an authorized correction without exposing locality in the audit row', function (): void {
    $actor = actingAsTestUser();
    Gate::define('x-change.correct-payout-locality', fn ($user, $payout) => $user->getKey() === $actor->getKey());
    $row = DisbursementReconciliation::query()->create([
        'voucher_code' => 'LOCALITY-REVISION', 'status' => 'failed', 'provider' => 'unknown',
        'payout_identity_snapshot' => ['sender' => null, 'recipient' => ['role' => 'recipient', 'name' => 'Person', 'address' => ['address1' => 'Original Barangay', 'city' => 'Manila', 'country' => 'PH', 'postal_code' => '1000', 'source' => 'synthetic']]],
    ]);
    app(CorrectPayoutLocality::class)->handle($actor, $row, 'recipient', correctionAddress(), 'Correct locality');
    $audit = DB::table('x_change_payout_locality_revisions')->sole();
    expect($row->fresh()->payout_identity_snapshot['recipient']['address']['address1'])->toBe('Corrected Barangay')
        ->and($row->fresh()->payout_identity_snapshot['recipient']['address']['source'])->toBe('operator_corrected')
        ->and($audit->actor_id)->toBe((string) $actor->getKey())
        ->and($audit->previous_snapshot)->not->toContain('Original Barangay')
        ->and($audit->corrected_snapshot)->not->toContain('Corrected Barangay');
});

it('rejects corrections to pending provider outcomes', function (): void {
    $actor = actingAsTestUser();
    Gate::define('x-change.correct-payout-locality', fn () => true);
    $row = DisbursementReconciliation::query()->create(['voucher_code' => 'PENDING-OUTCOME', 'status' => 'pending']);
    expect(fn () => app(CorrectPayoutLocality::class)->handle($actor, $row, 'recipient', correctionAddress(), 'Correct locality'))
        ->toThrow(RuntimeException::class);
    expect(DB::table('x_change_payout_locality_revisions')->count())->toBe(0);
});
