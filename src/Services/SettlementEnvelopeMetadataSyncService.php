<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Settlement\V2EvidenceEventJournal;

class SettlementEnvelopeMetadataSyncService
{
    public function __construct(private readonly V2EvidenceEventJournal $events) {}

    public function syncPatientAttestation(Voucher $voucher, array $payload, ?VoucherClaim $evidenceClaim = null): Voucher
    {
        return DB::transaction(function () use ($voucher, $payload, $evidenceClaim): Voucher {
            $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            $updated = $this->persistPatientAttestation($locked, $payload, $evidenceClaim);
            $this->events->settlementReadyAfterReview($updated);

            return $updated;
        });
    }

    /** @param array{payload?: array, documents?: array, checklist?: array} $evidence */
    public function syncEvidence(Voucher $voucher, array $evidence): Voucher
    {
        if (array_diff(array_keys($evidence), ['payload', 'documents', 'checklist']) !== []) {
            throw new \InvalidArgumentException('Unsupported settlement evidence section.');
        }
        foreach ($evidence as $section) {
            if (! is_array($section)) {
                throw new \InvalidArgumentException('Settlement evidence sections must be arrays.');
            }
        }

        return DB::transaction(function () use ($voucher, $evidence): Voucher {
            $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            $metadata = $this->metadata($locked);
            $driver = $this->validatedInstructionDriver($metadata);
            if ($driver !== null && array_key_exists('amount_verified', $evidence['checklist'] ?? [])
                && data_get($metadata, 'settlement_envelope.checklist.amount_verified') !== $evidence['checklist']['amount_verified']) {
                throw new \InvalidArgumentException('Amount verification requires an authorized settlement review.');
            }

            $envelope = (array) data_get($metadata, 'settlement_envelope', []);
            if ($driver !== null) {
                $envelope['driver'] = $driver;
            }
            foreach (['payload', 'documents', 'checklist'] as $section) {
                $value = [...(array) ($envelope[$section] ?? $metadata['settlement_'.$section] ?? []), ...($evidence[$section] ?? [])];
                $envelope[$section] = $value;
                $metadata['settlement_'.$section] = $value;
            }
            $envelope['updated_at'] = now()->toISOString();
            $metadata['settlement_envelope'] = $envelope;
            $locked->forceFill(['metadata' => $metadata])->save();
            $this->events->settlementReadyAfterReview($locked);

            return $locked->refresh();
        });
    }

    private function persistPatientAttestation(Voucher $voucher, array $payload, ?VoucherClaim $evidenceClaim): Voucher
    {
        $metadata = $this->metadata($voucher);

        $existingEnvelope = (array) Arr::get($metadata, 'settlement_envelope', []);

        $instructionDriver = $this->validatedInstructionDriver($metadata);

        $bioFields = (array) Arr::get($payload, 'bio_fields', []);
        $walletInfo = (array) Arr::get($payload, 'wallet_info', []);
        $inputs = (array) Arr::get($payload, 'inputs', []);

        $patientName = Arr::get($payload, 'patient_name')
            ?? Arr::get($bioFields, 'name')
            ?? Arr::get($bioFields, 'full_name')
            ?? Arr::get($inputs, 'name')
            ?? Arr::get($inputs, 'full_name');

        $patientMobile = Arr::get($payload, 'patient_mobile')
            ?? Arr::get($walletInfo, 'mobile')
            ?? Arr::get($payload, 'mobile')
            ?? Arr::get($inputs, 'mobile');

        $derivedPayload = array_filter([
            'patient_name' => $patientName,
            'patient_mobile' => $patientMobile,
        ], fn ($value) => filled($value));

        $explicitPayload = (array) Arr::get($payload, 'settlement.payload', []);

        $envelopePayload = [
            ...(array) Arr::get($existingEnvelope, 'payload', []),
            ...$derivedPayload,
            ...$explicitPayload,
        ];

        $attestation = [
            ...(array) Arr::get($existingEnvelope, 'attestation', []),
            'attested' => true,
            'attested_at' => $evidenceClaim?->attempted_at?->toISOString() ?? now()->toISOString(),
            ...($evidenceClaim === null ? [] : [
                'claim_id' => $evidenceClaim->getKey(),
                'evidence_manifest_sha256' => data_get($evidenceClaim->meta, 'evidence.manifest_sha256'),
            ]),
            'claim_type' => Arr::get($payload, 'claim_type', 'redeem'),
            'mobile' => Arr::get($payload, 'mobile'),
            'inputs' => $inputs,
            'bio_fields' => $bioFields,
            'wallet_info' => $walletInfo,
            'signature' => Arr::get($payload, 'signature')
                ?? Arr::get($inputs, 'signature'),
            'selfie' => Arr::get($payload, 'selfie')
                ?? Arr::get($inputs, 'selfie'),
            'location' => Arr::get($payload, 'location')
                ?? Arr::get($inputs, 'location'),
        ];

        $envelope = [
            ...$existingEnvelope,
            'driver' => $instructionDriver
                ?? Arr::get($existingEnvelope, 'driver')
                ?? Arr::get($metadata, 'settlement_driver')
                    ?? Arr::get($metadata, 'instructions.metadata.settlement_driver')
                    ?? Arr::get($metadata, 'instructions.metadata.envelope_driver')
                    ?? Arr::get($metadata, 'instructions.metadata.custom.settlement.driver')
                    ?? (Arr::get($metadata, 'instructions.claim.default_outcome') === 'lead_intake'
                        ? 'claim-intake'
                        : null)
                    ?? config('x-change.settlement.default_driver', 'philhealth-bst'),
            'payload' => $envelopePayload,
            'documents' => (array) Arr::get($existingEnvelope, 'documents', []),
            'checklist' => (array) Arr::get($existingEnvelope, 'checklist', []),
            'attestation' => array_filter($attestation, fn ($value) => $value !== null),
            'updated_at' => now()->toISOString(),
        ];

        $metadata = [
            ...$metadata,
            'flow_type' => 'settlement',
            'settlement_driver' => $envelope['driver'],
            'settlement_envelope' => $envelope,

            /*
             * Backward-compatible flattened keys.
             */
            'settlement_payload' => $envelope['payload'],
            'settlement_documents' => $envelope['documents'],
            'settlement_checklist' => $envelope['checklist'],
        ];

        $voucher->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $voucher->refresh();
    }

    protected function metadata(Voucher $voucher): array
    {
        $raw = $voucher->getAttributes()['metadata'] ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($raw)) {
            return $raw;
        }

        $metadata = $voucher->metadata ?? null;

        return is_array($metadata) ? $metadata : [];
    }

    public function assertInstructionCompatible(Voucher $voucher): void
    {
        $this->validatedInstructionDriver($this->metadata($voucher));
    }

    private function validatedInstructionDriver(array $metadata): ?string
    {
        if (Arr::get($metadata, 'instructions.execution.schema') !== 'voucher.execution.v2') {
            return null;
        }

        $driver = Arr::get($metadata, 'instructions.execution.settlement.driver');
        if (! is_string($driver) || trim($driver) === '') {
            throw new \InvalidArgumentException('V2 settlement requires an explicit driver.');
        }

        $envelopeDriver = Arr::get($metadata, 'settlement_envelope.driver');
        if ($envelopeDriver !== null && $envelopeDriver !== $driver) {
            throw new \InvalidArgumentException('Settlement envelope driver does not match the v2 instruction.');
        }

        return $driver;
    }
}
