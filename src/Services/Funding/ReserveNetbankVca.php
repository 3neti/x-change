<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PaymentAttempt;
use LogicException;
use RuntimeException;

class ReserveNetbankVca
{
    public function recordHistoricalAddress(string $address): void
    {
        if (! preg_match('/\A[0-9]{12}\z/', $address)) {
            throw new InvalidArgumentException('A historical NetBank VCA must contain twelve digits.');
        }

        DB::transaction(function () use ($address): void {
            DB::table('xchange_funding_vca_reservations')->insertOrIgnore([
                'funding_intent_id' => null,
                'address_hash' => hash('sha256', $address),
                'address_ciphertext' => Crypt::encryptString($address),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $record = DB::table('xchange_funding_vca_reservations')
                ->where('address_hash', hash('sha256', $address))->lockForUpdate()->first();
            if ($record === null || $record->funding_intent_id !== null || $record->payment_attempt_id !== null) {
                throw new LogicException('The historical VCA is already assigned to a funding intent.');
            }
        }, 3);
    }

    public function reserve(FundingIntent $intent, string $alias): string
    {
        return $this->reserveFor($intent, $alias, 'funding_intent_id');
    }

    public function reservePaymentAttempt(PaymentAttempt $attempt, string $alias): string
    {
        return $this->reserveFor($attempt, $alias, 'payment_attempt_id');
    }

    private function reserveFor(FundingIntent|PaymentAttempt $subject, string $alias, string $column): string
    {
        if (! preg_match('/\A[0-9]{5}\z/', $alias)) {
            throw new InvalidArgumentException('A five-digit NetBank alias is required.');
        }

        return DB::transaction(function () use ($subject, $alias, $column): string {
            $subject::query()->lockForUpdate()->findOrFail($subject->getKey());
            $existing = DB::table('xchange_funding_vca_reservations')
                ->where($column, $subject->getKey())->first();

            if ($existing !== null) {
                $address = Crypt::decryptString($existing->address_ciphertext);
                if (! str_starts_with($address, $alias)) {
                    throw new LogicException('The funding intent already has a different NetBank alias.');
                }

                return $address;
            }

            $key = config('x-change.funding.reference_hash_key') ?: config('app.key');
            if (! is_string($key) || trim($key) === '') {
                throw new LogicException('A funding reference hash key is required.');
            }

            $referenceKeys = array_values(array_unique(array_filter([
                $key,
                ...(array) config('x-change.funding.previous_reference_hash_keys', []),
                ...(array) config('app.previous_keys', []),
            ], static fn (mixed $candidate): bool => is_string($candidate) && trim($candidate) !== '')));

            for ($attempt = 0; $attempt < 100; $attempt++) {
                $address = $alias.$this->nextReference();
                $plainHash = hash('sha256', $address);
                $keyedHashes = array_map(static fn (string $referenceKey): string => hash_hmac('sha256', $address, $referenceKey), $referenceKeys);
                if (DB::table('x_change_funding_intents')->whereIn('funding_address_hash', $keyedHashes)->exists()
                    || DB::table('x_change_standing_funding_addresses')->where('funding_address_hash', $plainHash)->exists()
                    || DB::table('x_change_payment_attempts')->whereIn('funding_address_hash', [$plainHash, ...$keyedHashes])->exists()) {
                    continue;
                }

                $inserted = DB::table('xchange_funding_vca_reservations')->insertOrIgnore([
                    $column => $subject->getKey(),
                    'address_hash' => $plainHash,
                    'address_ciphertext' => Crypt::encryptString($address),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($inserted === 1) {
                    return $address;
                }
            }

            throw new RuntimeException('Unable to reserve a unique NetBank funding reference.');
        }, 3);
    }

    protected function nextReference(): string
    {
        return str_pad((string) random_int(0, 9_999_999), 7, '0', STR_PAD_LEFT);
    }
}
