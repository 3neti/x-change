<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Support\Arr;

class FormFlowClaimPayloadNormalizer
{
    public function __construct(
        protected PayoutDestinationRegistry $destinations,
    ) {}

    public function normalize(array $collectedData): array
    {
        $flatData = $this->normalizeFieldAliases(
            $this->flattenCollectedData($collectedData)
        );

        $inputs = $this->buildInputs($flatData, $collectedData);

        $mobile = $flatData['mobile'] ?? null;
        $country = $flatData['recipient_country'] ?? 'PH';
        $bankCode = $flatData['bank_code'] ?? null;
        $accountNumber = $flatData['account_number'] ?? null;
        $settlementRail = $flatData['settlement_rail'] ?? null;
        $destination = $this->destinations->snapshot($bankCode, $accountNumber, $settlementRail);

        return [
            'secret' => $flatData['secret'] ?? null,
            'mobile' => $mobile,
            'country' => $country,
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
            'amount' => $flatData['amount'] ?? null,
            'slice_ids' => $flatData['slice_ids'] ?? [],
            'settlement_rail' => $settlementRail,
            'destination' => $destination,
            'inputs' => $inputs,
            '_flat_data' => Arr::except($flatData, [
                'otp_code',
                'secret',
                'verification_reference',
                'verification_purpose',
            ]),
        ];
    }

    public function flattenCollectedData(array $collectedData): array
    {
        $mapped = [];

        foreach ($collectedData as $stepData) {
            if (is_array($stepData)) {
                $mapped = array_merge($mapped, $stepData);
            }
        }

        return $mapped;
    }

    protected function buildInputs(array $flatData, array $collectedData): array
    {
        $inputs = collect($flatData)
            ->except([
                'recipient_country',
                'amount',
                'settlement_rail',
                'slice_ids',
                'secret',
                'otp_code',
                'verified_at',
                'reference_id',
                'verification_reference',
                'verification_purpose',
                'latitude',
                'longitude',
                'formatted_address',
                'address_components',
                'map',
                'accuracy',
                'timestamp',
            ])
            ->toArray();

        $locationData = $this->extractLocationData($collectedData);

        if ($locationData !== []) {
            $inputs['location'] = $locationData;
        }

        $kycData = $this->extractKycData($flatData, $collectedData);

        if ($kycData !== []) {
            $inputs['kyc'] = $kycData;

            foreach ($this->kycCompatibilityKeys() as $key) {
                if (array_key_exists($key, $kycData) && ! array_key_exists($key, $inputs)) {
                    $inputs[$key] = $kycData[$key];
                }
            }
        }

        $otpData = $this->extractOtpData($flatData);

        if ($otpData !== []) {
            $inputs['otp'] = $otpData;
            $inputs['otp_verified'] = true;
        }

        return $inputs;
    }

    /**
     * @param  array<int|string, mixed>  $collectedData
     * @return array<string, mixed>
     */
    protected function extractLocationData(array $collectedData): array
    {
        foreach ($collectedData as $stepData) {
            if (! is_array($stepData)
                || ! is_numeric($stepData['latitude'] ?? null)
                || ! is_numeric($stepData['longitude'] ?? null)) {
                continue;
            }

            return collect($stepData)
                ->only([
                    'latitude',
                    'longitude',
                    'formatted_address',
                    'address_components',
                    'map',
                    'accuracy',
                    'timestamp',
                ])
                ->reject(static fn (mixed $value): bool => $value === null || $value === '')
                ->all();
        }

        return [];
    }

    protected function extractKycData(array $flatData, array $collectedData): array
    {
        $candidates = [];

        if (isset($collectedData['kyc_verification']) && is_array($collectedData['kyc_verification'])) {
            $candidates[] = $collectedData['kyc_verification'];
        }

        if (isset($flatData['kyc']) && is_array($flatData['kyc'])) {
            $candidates[] = $flatData['kyc'];
        }

        $flatCandidate = [];

        foreach ($this->kycCompatibilityKeys() as $key) {
            if (array_key_exists($key, $flatData)) {
                $flatCandidate[$key] = $flatData[$key];
            }
        }

        if ($flatCandidate !== []) {
            $candidates[] = $flatCandidate;
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeKycData($candidate);

            if ($normalized !== []) {
                return $normalized;
            }
        }

        return [];
    }

    protected function normalizeKycData(array $kycData): array
    {
        if (isset($kycData['kyc']) && is_array($kycData['kyc'])) {
            $kycData = array_merge($kycData['kyc'], $kycData);
            unset($kycData['kyc']);
        }

        if (isset($kycData['status']) && is_string($kycData['status'])) {
            $kycData['status'] = strtolower($kycData['status']);
        }

        if (($kycData['status'] ?? null) === 'auto_approved') {
            $kycData['status'] = 'approved';
        }

        if (($kycData['status'] ?? null) === 'success') {
            $kycData['status'] = 'approved';
        }

        if (! isset($kycData['transaction_id']) && isset($kycData['transactionId'])) {
            $kycData['transaction_id'] = $kycData['transactionId'];
        }

        if (! isset($kycData['completed_at'])) {
            $kycData['completed_at'] = now()->toIso8601String();
        }

        return array_filter(
            $kycData,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    protected function kycCompatibilityKeys(): array
    {
        return [
            'transaction_id',
            'transactionId',
            'status',
            'completed_at',
            'rejection_reasons',
            'name',
            'email',
            'date_of_birth',
            'birth_date',
            'address',
            'id_type',
            'id_number',
            'nationality',
            'id_card_full',
            'id_card_cropped',
            'selfie',
        ];
    }

    protected function normalizeFieldAliases(array $flatData): array
    {
        $aliases = [
            'name' => ['full_name'],
            'birth_date' => ['date_of_birth'],
        ];

        foreach ($aliases as $canonical => $candidates) {
            if (array_key_exists($canonical, $flatData)) {
                continue;
            }

            foreach ($candidates as $candidate) {
                if (array_key_exists($candidate, $flatData)) {
                    $flatData[$canonical] = $flatData[$candidate];
                    break;
                }
            }
        }

        return $flatData;
    }

    protected function extractOtpData(array $flatData): array
    {
        $reference = $flatData['verification_reference'] ?? null;
        $purpose = $flatData['verification_purpose'] ?? null;
        $verifiedAt = $flatData['verified_at'] ?? null;

        if (! is_string($reference) || trim($reference) === ''
            || ! is_string($purpose) || trim($purpose) === ''
            || ! is_string($verifiedAt) || trim($verifiedAt) === '') {
            return [];
        }

        return [
            'verified' => true,
            'verified_at' => $verifiedAt,
            'reference_id' => $flatData['reference_id'] ?? null,
            'verification_reference' => $reference,
            'verification_purpose' => $purpose,
            'mobile' => $flatData['mobile'] ?? null,
        ];
    }
}
