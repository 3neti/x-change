<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Disbursement;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use RuntimeException;

final class CorrectPayoutLocality
{
    /** @param array<string, mixed> $address */
    public function handle(Authenticatable $actor, DisbursementReconciliation $payout, string $role, array $address, string $reason): void
    {
        Gate::forUser($actor)->authorize('x-change.correct-payout-locality', $payout);
        if (! in_array($role, ['sender', 'recipient'], true) || trim($reason) === '' || mb_strlen($reason) > 1000
            || array_diff(array_keys($address), ['address1', 'city', 'country', 'postal_code']) !== []) {
            throw new InvalidArgumentException('Invalid locality correction.');
        }
        foreach (['address1', 'city', 'country', 'postal_code'] as $field) {
            if (! is_string($address[$field] ?? null) || trim($address[$field]) === '' || mb_strlen($address[$field]) > 128) {
                throw new InvalidArgumentException('Invalid locality correction.');
            }
            $address[$field] = trim($address[$field]);
        }
        if (strtoupper($address['country']) !== 'PH' || ! preg_match('/\A[0-9]{4}\z/', $address['postal_code'])) {
            throw new InvalidArgumentException('Invalid locality correction.');
        }
        $address['country'] = 'PH';
        $address['source'] = 'operator_corrected';

        $lock = Cache::lock('x-change:disbursement-retry:'.mb_strtoupper(trim((string) $payout->voucher_code)), 120);
        if (! $lock->get()) {
            throw new RuntimeException('Another disbursement recovery is already running.');
        }
        try {
            DB::transaction(function () use ($actor, $payout, $role, $address, $reason): void {
                $locked = DisbursementReconciliation::query()->lockForUpdate()->findOrFail($payout->getKey());
                Gate::forUser($actor)->authorize('x-change.correct-payout-locality', $locked);
                if ($locked->status !== 'failed' || $locked->provider_transaction_id !== null
                    || ! in_array($locked->provider, [null, '', 'unknown'], true)
                    || ! is_array(data_get($locked->payout_identity_snapshot, $role))) {
                    throw new RuntimeException('Payout locality cannot be corrected while a provider outcome is unresolved.');
                }
                $previous = $locked->payout_identity_snapshot;
                $corrected = $previous;
                $corrected[$role]['address'] = $address;
                DB::table('x_change_payout_locality_revisions')->insert([
                    'disbursement_reconciliation_id' => $locked->getKey(),
                    'actor_type' => $actor::class,
                    'actor_id' => (string) $actor->getAuthIdentifier(),
                    'role' => $role,
                    'reason' => Crypt::encryptString(trim($reason)),
                    'previous_snapshot' => Crypt::encryptString(json_encode($previous, JSON_THROW_ON_ERROR)),
                    'corrected_snapshot' => Crypt::encryptString(json_encode($corrected, JSON_THROW_ON_ERROR)),
                    'created_at' => now(),
                ]);
                $locked->forceFill(['payout_identity_snapshot' => $corrected])->save();
            }, 5);
        } finally {
            $lock->release();
        }
    }
}
