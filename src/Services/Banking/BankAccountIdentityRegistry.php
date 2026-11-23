<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\BankAccountIdentity;

final class BankAccountIdentityRegistry
{
    public function claim(Model $owner, string $provider, string $accountNumber): BankAccountIdentity
    {
        $provider = strtolower(trim($provider));
        $number = preg_replace('/\D+/', '', $accountNumber);
        if ($provider === '' || $number === '' || strlen($number) < 8 || strlen($number) > 32 || ! $owner->exists) {
            throw ValidationException::withMessages(['account_number' => 'A valid account and owner are required.']);
        }

        return DB::transaction(function () use ($owner, $provider, $number): BankAccountIdentity {
            $key = (string) config('app.key');
            $this->verifyIdentityKey($key);
            $fingerprint = hash_hmac('sha256', $provider.'|'.$number, $key);
            $identity = BankAccountIdentity::query()->firstOrCreate(
                ['account_fingerprint' => $fingerprint],
                ['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'provider' => $provider],
            );

            if ($identity->provider !== $provider || $identity->owner_type !== $owner->getMorphClass()
                || (string) $identity->owner_id !== (string) $owner->getKey()) {
                throw ValidationException::withMessages(['account_number' => 'This account is already assigned.']);
            }

            return $identity;
        }, attempts: 3);
    }

    private function verifyIdentityKey(string $key): void
    {
        if ($key === '') {
            throw ValidationException::withMessages(['account_number' => 'The account identity key is unavailable.']);
        }

        $table = DB::table('xchange_bank_identity_key_guard');
        $stored = $table->where('id', 1)->lockForUpdate()->value('key_verifier');
        $verifier = hash_hmac('sha256', 'x-change:bank-account-identity-key:v1', $key);

        if ($stored === null) {
            if (BankAccountIdentity::query()->exists()) {
                throw ValidationException::withMessages(['account_number' => 'Account identity key state needs reconciliation.']);
            }

            DB::table('xchange_bank_identity_key_guard')->insertOrIgnore(['id' => 1, 'key_verifier' => $verifier]);
            $stored = DB::table('xchange_bank_identity_key_guard')->where('id', 1)->lockForUpdate()->value('key_verifier');
        }

        if (! is_string($stored) || ! hash_equals($stored, $verifier)) {
            throw ValidationException::withMessages(['account_number' => 'Account identity key changed and needs reconciliation.']);
        }
    }
}
