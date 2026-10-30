<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceRequirements;
use LBHurtado\XChange\Services\Settlement\V2EvidenceEventJournal;
use LBHurtado\XChange\Services\SettlementEnvelopeMetadataSyncService;

final class PrepareVoucherClaimEvidence
{
    public function __construct(
        private readonly ClaimEvidenceRequirements $requirements,
        private readonly PersistVoucherClaimEvidence $persistEvidence,
        private readonly V2EvidenceEventJournal $v2Events,
        private readonly SettlementEnvelopeMetadataSyncService $envelopeSync,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(Voucher $voucher, array $payload, bool $recordSettlementAttestation = false): ?VoucherClaim
    {
        $isV2Attestation = data_get($voucher->metadata, 'instructions.execution.schema') === 'voucher.execution.v2'
            && is_array(data_get($voucher->metadata, 'instructions.execution.events.attest'));
        if ($this->requirements->forVoucher($voucher) === []) {
            if ($isV2Attestation) {
                throw new \LogicException('V2 attestation requires configured claim evidence.');
            }

            return null;
        }

        $idempotencyKey = data_get($payload, '_meta.idempotency_key');
        if ($isV2Attestation && (! is_string($idempotencyKey) || trim($idempotencyKey) === '')) {
            throw new \LogicException('V2 attestation requires an idempotency key.');
        }
        $fingerprint = $isV2Attestation ? $this->evidenceFingerprint($payload) : null;

        if (is_string($idempotencyKey) && trim($idempotencyKey) !== '') {
            $existing = VoucherClaim::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof VoucherClaim) {
                $this->assertReplayMatches($existing, $fingerprint);

                return $existing;
            }
        }

        return DB::transaction(function () use ($voucher, $payload, $idempotencyKey, $fingerprint, $recordSettlementAttestation): VoucherClaim {
            $lockedVoucher = Voucher::query()
                ->lockForUpdate()
                ->findOrFail($voucher->getKey());
            if (is_string($idempotencyKey) && trim($idempotencyKey) !== '') {
                $existing = VoucherClaim::query()->where('voucher_id', $lockedVoucher->getKey())
                    ->where('idempotency_key', $idempotencyKey)->first();
                if ($existing instanceof VoucherClaim) {
                    $this->assertReplayMatches($existing, $fingerprint);

                    return $existing;
                }
            }
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
                        ...($fingerprint === null ? [] : ['request_fingerprint' => $fingerprint]),
                    ],
                ],
            ]);

            $this->persistEvidence->handle(
                $lockedVoucher,
                $claim,
                (array) data_get($payload, 'inputs', []),
            );

            if ($recordSettlementAttestation) {
                $claim->refresh();
                $this->envelopeSync->syncPatientAttestation($lockedVoucher->refresh(), $payload, $claim);
            }

            $this->v2Events->attestationEvidenceCaptured($lockedVoucher, $claim->refresh());

            return $claim->fresh(['evidence']) ?? $claim;
        });
    }

    private function evidenceFingerprint(array $payload): string
    {
        unset($payload['_meta']);

        return hash_hmac('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->canonicalize(...), $value);
    }

    private function assertReplayMatches(VoucherClaim $claim, ?string $fingerprint): void
    {
        if ($fingerprint === null) {
            return;
        }
        $recorded = data_get($claim->meta, 'evidence.request_fingerprint');
        if (! is_string($recorded) || ! hash_equals($recorded, $fingerprint)) {
            throw new \LogicException('Attestation idempotency key was reused with different evidence.');
        }
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
