<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopePayloadVersion;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Settlement\CompletionClaimEvidenceProjectionData;
use LBHurtado\XChange\Events\CompletionClaimEvidenceProjected;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;
use LBHurtado\XChange\Models\CompletionPayCodeIssuance;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimEvidence;

final readonly class ProjectCompletionClaimEvidence
{
    public function __construct(private EnvelopeService $envelopes, private AuditLoggerContract $audit) {}

    public function handle(VoucherClaim $claim): ?CompletionClaimEvidenceProjectionData
    {
        $issuance = CompletionPayCodeIssuance::query()->where('voucher_id', $claim->voucher_id)->first();

        if (! $issuance instanceof CompletionPayCodeIssuance) {
            return null;
        }

        $result = DB::transaction(function () use ($claim, $issuance): array {
            $lockedIssuance = CompletionPayCodeIssuance::query()->with(['coverage', 'voucher'])->lockForUpdate()->findOrFail($issuance->getKey());
            $lockedClaim = VoucherClaim::query()->with('evidence')->where('voucher_id', $lockedIssuance->voucher_id)->lockForUpdate()->findOrFail($claim->getKey());
            $envelope = Envelope::query()->lockForUpdate()->findOrFail($lockedIssuance->envelope_id);
            $this->assertProjectable($lockedIssuance, $lockedClaim, $envelope);
            $manifest = $this->manifest($lockedIssuance, $lockedClaim);
            $manifestHash = $this->hash($manifest);
            $projectionHash = $this->hash(['issuance_reference' => $lockedIssuance->reference, 'claim_number' => $lockedClaim->claim_number, 'manifest_hash' => $manifestHash]);
            $existing = CompletionClaimEvidenceProjection::query()->where('completion_pay_code_issuance_id', $lockedIssuance->getKey())->first();

            if ($existing instanceof CompletionClaimEvidenceProjection) {
                if (! hash_equals($existing->manifest_hash, $manifestHash)
                    || ! hash_equals($existing->projection_hash, $projectionHash)
                    || $existing->voucher_claim_id !== $lockedClaim->getKey()) {
                    throw new InvalidArgumentException('Existing completion evidence projection does not match the claim evidence.');
                }

                return ['projection' => $existing, 'created' => false];
            }

            $updated = $this->envelopes->updatePayload($envelope, ['applicant' => ['evidence' => $manifest]]);
            $payloadVersion = EnvelopePayloadVersion::query()->where('envelope_id', $updated->getKey())->where('version', $updated->payload_version)->sole();
            $projection = CompletionClaimEvidenceProjection::query()->create([
                'completion_pay_code_issuance_id' => $lockedIssuance->getKey(),
                'voucher_claim_id' => $lockedClaim->getKey(),
                'envelope_id' => $updated->getKey(),
                'envelope_payload_version_id' => $payloadVersion->getKey(),
                'manifest_hash' => $manifestHash,
                'projection_hash' => $projectionHash,
                'source_snapshot' => ['schema' => 'x-change.completion-claim-evidence-source.v1', 'voucher_claim_id' => $lockedClaim->getKey(), 'evidence_record_ids' => $this->declaredEvidence($lockedIssuance, $lockedClaim)->pluck('id')->sort()->values()->all()],
                'projected_at' => now(),
            ]);

            return ['projection' => $projection, 'created' => true];
        }, attempts: 5);

        if ($result['created']) {
            $projection = $result['projection'];
            $payload = [
                'schema' => 'x-change.completion-claim-evidence-projected.v1',
                'projection_reference' => $projection->reference,
                'issuance_reference' => $issuance->reference,
                'envelope_reference' => $issuance->envelope->reference_code,
                'claim_number' => $claim->claim_number,
                'evidence_count' => count($projection->source_snapshot['evidence_record_ids']),
            ];
            $this->audit->log('campaign.completion_claim_evidence.projected', $payload + ['financial_side_effects' => false]);
            CompletionClaimEvidenceProjected::dispatch($payload);
        }

        return new CompletionClaimEvidenceProjectionData($result['projection'], $result['created']);
    }

    private function assertProjectable(CompletionPayCodeIssuance $issuance, VoucherClaim $claim, Envelope $envelope): void
    {
        if ($claim->status !== 'redeemed' || $claim->completed_at === null) {
            throw new InvalidArgumentException('Only a completed completion claim can be projected.');
        }
        if (data_get($issuance->voucher->metadata, 'instructions.claim.default_outcome') !== 'envelope_completion') {
            throw new InvalidArgumentException('Completion claim outcome does not target the settlement envelope.');
        }
        if ($issuance->envelope_id !== $envelope->getKey()
            || $issuance->driver_id !== $envelope->driver_id
            || $issuance->driver_version !== $envelope->driver_version
            || $issuance->coverage->envelope_id !== $envelope->getKey()) {
            throw new InvalidArgumentException('Completion issuance and envelope identities do not match.');
        }

        $expected = $this->declaredKeys($issuance);
        $actual = $this->declaredEvidence($issuance, $claim)->pluck('requirement_key')->sort()->values();

        if ($expected->all() !== $actual->all()
            || ($expected->isNotEmpty() && data_get($claim->meta, 'evidence.persisted') !== true)
            || data_get($claim->meta, 'evidence.execution_status') !== 'finalized') {
            throw new InvalidArgumentException('Completion claim evidence is incomplete or does not match its declared requirements.');
        }
    }

    /** @return Collection<int, string> */
    private function declaredKeys(CompletionPayCodeIssuance $issuance): Collection
    {
        $keys = collect((array) data_get($issuance->requirements_snapshot, 'applicant_fields', []));
        if (data_get($issuance->requirements_snapshot, 'requires_otp') === true) {
            $keys->push('otp');
        }

        return $keys->map(static fn (mixed $key): string => trim((string) $key))->filter()->unique()->sort()->values();
    }

    /** @return Collection<int, VoucherClaimEvidence> */
    private function declaredEvidence(CompletionPayCodeIssuance $issuance, VoucherClaim $claim): Collection
    {
        return $claim->evidence->whereIn('requirement_key', $this->declaredKeys($issuance)->all());
    }

    /** @return array<string, mixed> */
    private function manifest(CompletionPayCodeIssuance $issuance, VoucherClaim $claim): array
    {
        return [
            'schema' => 'x-change.completion-claim-evidence-manifest.v1',
            'claim_number' => $claim->claim_number,
            'items' => $this->declaredEvidence($issuance, $claim)->sortBy('requirement_key')->values()->map(fn (VoucherClaimEvidence $evidence): array => [
                'key' => $evidence->requirement_key,
                'kind' => $evidence->kind->value,
                'status' => $evidence->status->value,
                'opaque_evidence_reference' => hash_hmac('sha256', $issuance->reference.':'.$evidence->getKey(), (string) config('app.key')),
                'artifact_sha256' => $evidence->sha256,
                'captured_at' => $evidence->captured_at?->toIso8601String(),
                'verified_at' => $evidence->verified_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
