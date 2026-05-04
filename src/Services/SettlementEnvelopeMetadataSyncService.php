<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;

class SettlementEnvelopeMetadataSyncService
{
    public function syncPatientAttestation(Voucher $voucher, array $payload): Voucher
    {
        $metadata = $this->metadata($voucher);

        $existingEnvelope = (array) Arr::get($metadata, 'settlement_envelope', []);

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
            'attested_at' => now()->toISOString(),
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
            'driver' => Arr::get($existingEnvelope, 'driver')
                ?? Arr::get($metadata, 'settlement_driver')
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
}
