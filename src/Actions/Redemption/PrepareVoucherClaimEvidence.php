<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceRequirements;

final class PrepareVoucherClaimEvidence
{
    public function __construct(
        private readonly ClaimEvidenceRequirements $requirements,
        private readonly PersistVoucherClaimEvidence $persistEvidence,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(Voucher $voucher, array $payload): ?VoucherClaim
    {
        if ($this->requirements->forVoucher($voucher) === []) {
            return null;
        }

        $idempotencyKey = data_get($payload, '_meta.idempotency_key');

        if (is_string($idempotencyKey) && trim($idempotencyKey) !== '') {
            $existing = VoucherClaim::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof VoucherClaim) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($voucher, $payload, $idempotencyKey): VoucherClaim {
            $lockedVoucher = Voucher::query()
                ->lockForUpdate()
                ->findOrFail($voucher->getKey());
            $claim = VoucherClaim::query()->create([
                'voucher_id' => $lockedVoucher->getKey(),
                'claim_number' => (int) $lockedVoucher->claims()->count() + 1,
                'claim_type' => 'claim',
                'status' => 'prepared',
                'currency' => (string) data_get(
                    $lockedVoucher->metadata,
                    'instructions.cash.currency',
                    'PHP',
                ),
                'claimer_mobile' => data_get($payload, 'mobile'),
                'recipient_country' => data_get($payload, 'recipient_country', data_get($payload, 'country')),
                'bank_code' => data_get($payload, 'bank_account.bank_code', data_get($payload, 'bank_code')),
                'account_number_masked' => $this->maskAccountNumber(
                    data_get($payload, 'bank_account.account_number', data_get($payload, 'account_number')),
                ),
                'idempotency_key' => is_string($idempotencyKey) ? $idempotencyKey : null,
                'reference' => data_get($payload, 'reference'),
                'attempted_at' => now(),
                'meta' => [
                    'evidence' => [
                        'execution_status' => 'not_started',
                    ],
                ],
            ]);

            $this->persistEvidence->handle(
                $lockedVoucher,
                $claim,
                (array) data_get($payload, 'inputs', []),
            );

            return $claim->fresh(['evidence']) ?? $claim;
        });
    }

    private function maskAccountNumber(mixed $accountNumber): ?string
    {
        if (! is_scalar($accountNumber) || trim((string) $accountNumber) === '') {
            return null;
        }

        $normalized = trim((string) $accountNumber);

        return str_repeat('*', max(0, strlen($normalized) - 4)).substr($normalized, -4);
    }
}
