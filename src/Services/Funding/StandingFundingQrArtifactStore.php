<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;
use LBHurtado\EmiCore\Data\Funding\FundingQrMerchantData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressData;
use LBHurtado\XChange\Data\Funding\StandingFundingAddressBindingData;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingQrArtifact;

final class StandingFundingQrArtifactStore
{
    public function fingerprint(
        StandingFundingAddress $address,
        ?FundingQrMerchantData $merchant,
    ): string {
        return hash('sha256', implode("\0", [
            $address->funding_address_hash,
            $address->provider_code,
            $merchant?->profileFingerprint ?? 'provider-config-default',
            (string) config(
                'x-change.funding.standing_addresses.qr_artifact_version',
                'standing-funding-qr-v1',
            ),
        ]));
    }

    public function find(
        StandingFundingAddress $address,
        string $fingerprint,
    ): ?StandingFundingQrArtifact {
        return $address->qrArtifacts()
            ->where('status', 'active')
            ->where('artifact_fingerprint', $fingerprint)
            ->first();
    }

    public function persist(
        StandingFundingAddress $address,
        StandingFundingAddressData $providerAddress,
        string $fingerprint,
        ?FundingQrMerchantData $merchant,
    ): StandingFundingQrArtifact {
        return DB::transaction(function () use (
            $address,
            $providerAddress,
            $fingerprint,
            $merchant,
        ): StandingFundingQrArtifact {
            $existing = StandingFundingQrArtifact::query()
                ->where('artifact_fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof StandingFundingQrArtifact) {
                return $existing;
            }

            $address->qrArtifacts()
                ->where('status', 'active')
                ->update([
                    'status' => 'stale',
                    'invalidated_at' => now(),
                    'updated_at' => now(),
                ]);

            return $address->qrArtifacts()->create([
                'status' => 'active',
                'version' => 1,
                'artifact_fingerprint' => $fingerprint,
                'merchant_profile_fingerprint' => $merchant?->profileFingerprint,
                'mime_type' => $providerAddress->qrCode->mimeType,
                'qr_mode' => $providerAddress->qrCode->qrMode,
                'transaction_type' => $providerAddress->qrCode->transactionType,
                'embedded_amount' => $providerAddress->qrCode->embeddedAmount,
                'provider_generated' => $providerAddress->qrCode->providerGenerated,
                'payload_ciphertext' => $providerAddress->qrCode->base64Payload,
                'display_snapshot_ciphertext' => $providerAddress->displayData,
                'generated_at' => now(),
            ]);
        }, attempts: 3);
    }

    public function toProviderData(
        StandingFundingAddress $address,
        StandingFundingQrArtifact $artifact,
        ?StandingFundingAddressBindingData $binding = null,
    ): StandingFundingAddressData {
        return new StandingFundingAddressData(
            provider: $address->provider_code,
            providerReference: $address->provider_reference,
            fundingAddress: $address->funding_address_ciphertext,
            accountReference: $binding?->accountReference ?? $address->account_reference,
            purpose: $address->purpose,
            currency: $address->currency,
            qrCode: new FundingQrCodeData(
                mimeType: $artifact->mime_type,
                base64Payload: $artifact->payload_ciphertext,
                qrMode: $artifact->qr_mode,
                transactionType: $artifact->transaction_type,
                embeddedAmount: $artifact->embedded_amount,
                providerGenerated: $artifact->provider_generated,
            ),
            reusable: true,
            displayData: $artifact->display_snapshot_ciphertext,
        );
    }
}
