<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Settlement\V2EvidenceEventJournal;
use LogicException;

final class VerifyPhilhealthSettlementAmount
{
    public function __construct(private readonly V2EvidenceEventJournal $events) {}

    public function handle(Voucher $voucher, Model&Authenticatable $reviewer, int $amountMinor, string $reference, ?string $expectedManifest = null, ?string $claimReference = null): Voucher
    {
        Gate::forUser($reviewer)->authorize('x-change.settlement.verify-amount', $voucher);
        $reference = trim($reference);
        if ($reference === '' || $amountMinor <= 0) {
            throw new LogicException('A positive verified amount and review reference are required.');
        }

        return DB::transaction(function () use ($voucher, $reviewer, $amountMinor, $reference, $expectedManifest, $claimReference): Voucher {
            $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            Gate::forUser($reviewer)->authorize('x-change.settlement.verify-amount', $locked);
            $metadata = (array) $locked->metadata;
            if (data_get($metadata, 'instructions.execution.schema') !== 'voucher.execution.v2'
                || data_get($metadata, 'instructions.execution.settlement.driver') !== 'philhealth-bst') {
                throw new LogicException('This review requires a v2 PhilHealth settlement instruction.');
            }
            $claimId = data_get($metadata, 'settlement_envelope.attestation.claim_id');
            $manifest = data_get($metadata, 'settlement_envelope.attestation.evidence_manifest_sha256');
            $claim = $locked->claims()->whereKey($claimId)->first();
            if ($claim === null || ! is_string($manifest) || ! hash_equals($manifest, (string) data_get($claim->meta, 'evidence.manifest_sha256'))) {
                throw new LogicException('A matching persisted attestation evidence manifest is required.');
            }
            if ($expectedManifest !== null && ! hash_equals($manifest, $expectedManifest)) {
                throw new LogicException('The attestation changed. Review the current evidence before confirming.');
            }
            if ((int) round((float) $locked->target_amount * 100) !== $amountMinor) {
                throw new LogicException('The verified amount must match the settlement target.');
            }
            $existing = data_get($metadata, 'settlement_envelope.amount_review');
            if (is_array($existing)) {
                if ($claimReference !== null && $claimReference !== data_get($metadata, 'settlement_envelope.payload.reference')) {
                    throw new LogicException('The PhilHealth envelope reference cannot be changed.');
                }
                if (($existing['reference'] ?? null) === $reference
                    && ($existing['reviewer_type'] ?? null) === $reviewer->getMorphClass()
                    && ($existing['reviewer_id'] ?? null) === (string) $reviewer->getKey()
                    && ($existing['amount_minor'] ?? null) === $amountMinor
                    && ($existing['evidence_manifest_sha256'] ?? null) === $manifest) {
                    $this->events->settlementReadyAfterReview($locked);

                    return $locked;
                }
                throw new LogicException('The settlement amount was already reviewed with different evidence.');
            }

            $envelope = $locked->envelope()->lockForUpdate()->first();
            if ($envelope !== null && $claimReference !== null) {
                if (! preg_match('/^[A-Za-z0-9-]{3,50}$/D', $claimReference)) {
                    throw new LogicException('The claim reference is invalid.');
                }
                $oldReference = data_get($envelope->payload, 'reference');
                if ($oldReference !== null && $oldReference !== $claimReference) {
                    throw new LogicException('The PhilHealth envelope reference cannot be changed.');
                }
                $name = data_get($metadata, 'settlement_envelope.payload.patient_name') ?? data_get($envelope->payload, 'patient_name');
                $mobile = data_get($metadata, 'settlement_envelope.payload.patient_mobile') ?? data_get($envelope->payload, 'patient_mobile');
                if (! filled($name) || ! filled($mobile)) {
                    throw new LogicException('Patient name and mobile are required before recording the claim reference.');
                }
                app(EnvelopeService::class)->updatePayload($envelope, ['patient_name' => $name, 'patient_mobile' => $mobile, 'reference' => $claimReference], $reviewer);
                data_set($metadata, 'settlement_envelope.payload.reference', $claimReference);
                data_set($metadata, 'settlement_payload.reference', $claimReference);
            }
            data_set($metadata, 'settlement_envelope.amount_review', [
                'reference' => $reference, 'reviewer_type' => $reviewer->getMorphClass(),
                'reviewer_id' => (string) $reviewer->getKey(), 'amount_minor' => $amountMinor,
                'evidence_manifest_sha256' => $manifest, 'reviewed_at' => now()->toISOString(),
            ]);
            data_set($metadata, 'settlement_envelope.checklist.amount_verified', true);
            data_set($metadata, 'settlement_checklist.amount_verified', true);
            $locked->forceFill(['metadata' => $metadata])->save();
            if ($envelope !== null) {
                if ($envelope->driver_id !== 'philhealth.bst.demo' || $envelope->driver_version !== '1.0.0') {
                    throw new LogicException('The attached envelope is not the supported PhilHealth review envelope.');
                }
                app(EnvelopeService::class)->setSignal($envelope, 'amount_verified', true, $reviewer);
            }
            $this->events->amountReviewed($locked, $reference, $manifest, $amountMinor);
            $this->events->settlementReadyAfterReview($locked);

            return $locked->refresh();
        }, attempts: 5);
    }
}
