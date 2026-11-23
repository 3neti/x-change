<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Banking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\BankAccountIdentity;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\Banking\NetbankAccountAssignmentPreflight;
use Throwable;

final class RotateBankAccountIdentityKey
{
    public function __construct(private readonly NetbankAccountAssignmentPreflight $preflight) {}

    /** @return array{identities: int, assignments: int} */
    public function handle(Model $actor): array
    {
        if (! $actor->exists) {
            $this->fail();
        }

        return DB::transaction(function (): array {
            $guard = DB::table('xchange_bank_identity_key_guard')->where('id', 1)->lockForUpdate()->first();
            $newKey = (string) config('app.key');
            if (! $guard || $newKey === '') {
                $this->fail();
            }
            $verifier = static fn (string $key): string => hash_hmac('sha256', 'x-change:bank-account-identity-key:v1', $key);
            if (hash_equals($guard->key_verifier, $verifier($newKey))) {
                return ['identities' => 0, 'assignments' => 0];
            }
            $matchingKeys = array_values(array_unique(array_filter((array) config('app.previous_keys', []),
                static fn ($key): bool => is_string($key) && $key !== '' && hash_equals($guard->key_verifier, $verifier($key)))));
            if (count($matchingKeys) !== 1 || ! $this->preflight->inspect()['ready']) {
                $this->fail();
            }
            $oldKey = $matchingKeys[0];
            $records = [];
            $assignments = BankAccountAssignment::query()->lockForUpdate()->get();
            $links = ProviderAccountLink::query()->where('provider', 'netbank')->where('topology', 'dedicated')->lockForUpdate()->get();
            try {
                foreach ($assignments as $assignment) {
                    if ($assignment->provider !== 'netbank') {
                        $this->fail();
                    }
                    $this->record($records, $assignment, $assignment->details_ciphertext['account_number'] ?? null, $oldKey, $newKey);
                    if (! hash_equals($assignment->account_fingerprint, hash_hmac('sha256', 'netbank|'.preg_replace('/\D+/', '', $assignment->details_ciphertext['account_number']), $oldKey))) {
                        $this->fail();
                    }
                }
                foreach ($links as $link) {
                    $this->record($records, $link, $link->routing_profile_ciphertext['bank_account_number'] ?? null, $oldKey, $newKey);
                }
            } catch (Throwable $exception) {
                if ($exception instanceof ValidationException) {
                    throw $exception;
                }
                $this->fail();
            }
            $identities = BankAccountIdentity::query()->lockForUpdate()->get();
            foreach ($identities as $identity) {
                $record = $records[$identity->account_fingerprint] ?? null;
                if (! $record || $identity->provider !== 'netbank' || $identity->owner_type !== $record['owner_type'] || (string) $identity->owner_id !== (string) $record['owner_id']) {
                    $this->fail();
                }
            }
            foreach ($identities as $identity) {
                $identity->update(['account_fingerprint' => $records[$identity->account_fingerprint]['new']]);
            }
            foreach ($assignments as $assignment) {
                $assignment->update(['account_fingerprint' => $records[$assignment->account_fingerprint]['new']]);
            }
            foreach ($records as $oldFingerprint => $record) {
                if (! $identities->contains('account_fingerprint', $record['new'])) {
                    BankAccountIdentity::query()->create(['account_fingerprint' => $record['new'], 'provider' => 'netbank', 'owner_type' => $record['owner_type'], 'owner_id' => $record['owner_id']]);
                }
            }
            DB::table('xchange_bank_identity_key_guard')->where('id', 1)->update(['key_verifier' => $verifier($newKey)]);

            return ['identities' => count($records), 'assignments' => $assignments->count()];
        }, attempts: 3);
    }

    private function record(array &$records, Model $model, mixed $number, string $oldKey, string $newKey): void
    {
        $number = is_string($number) ? preg_replace('/\D+/', '', $number) : '';
        if (strlen($number) < 8 || strlen($number) > 32 || ! $model->owner_type || ! $model->owner_id) {
            $this->fail();
        }
        $old = hash_hmac('sha256', 'netbank|'.$number, $oldKey);
        $record = ['new' => hash_hmac('sha256', 'netbank|'.$number, $newKey), 'owner_type' => $model->owner_type, 'owner_id' => $model->owner_id];
        if (isset($records[$old]) && $records[$old] !== $record) {
            $this->fail();
        }
        $records[$old] = $record;
    }

    private function fail(): never
    {
        throw ValidationException::withMessages(['account_number' => 'Account identity key rotation needs reconciliation. No changes were made.']);
    }
}
