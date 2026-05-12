<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

class FormFlowClaimPayloadNormalizer
{
    public function normalize(array $collectedData): array
    {
        $flatData = $this->normalizeFieldAliases(
            $this->flattenCollectedData($collectedData)
        );

        $inputs = $this->buildInputs($flatData, $collectedData);

        $mobile = $flatData['mobile'] ?? null;
        $country = $flatData['recipient_country'] ?? 'PH';

        return [
            'mobile' => $mobile,
            'country' => $country,
            'bank_code' => $flatData['bank_code'] ?? null,
            'account_number' => $flatData['account_number'] ?? null,
            'inputs' => $inputs,
            '_flat_data' => $flatData,
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
            ->except(['recipient_country', 'amount', 'settlement_rail'])
            ->toArray();

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
        $otpCode = $flatData['otp_code'] ?? null;

        if (! $otpCode) {
            return [];
        }

        return [
            'otp_code' => $otpCode,
            'verified' => true,
            'otp_verified' => true,
            'verified_at' => $flatData['verified_at'] ?? now()->toIso8601String(),
            'reference_id' => $flatData['reference_id'] ?? null,
        ];
    }
}
