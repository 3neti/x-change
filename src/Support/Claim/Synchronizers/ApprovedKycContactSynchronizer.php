<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim\Synchronizers;

use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;

class ApprovedKycContactSynchronizer
{
    public function sync(array $payload): void
    {
        $mobile = $payload['mobile'] ?? null;
        $country = $payload['country'] ?? 'PH';
        $kycData = $payload['inputs']['kyc'] ?? [];

        if (! is_string($mobile) || $mobile === '' || ! is_array($kycData)) {
            return;
        }

        $status = strtolower((string) ($kycData['status'] ?? ''));

        if ($status !== 'approved') {
            return;
        }

        try {
            $contact = Contact::fromPhoneNumber(phone($mobile, $country));

            $contact->forceFill([
                'kyc_status' => 'approved',
                'kyc_transaction_id' => $kycData['transaction_id'] ?? $contact->kyc_transaction_id,
                'kyc_submitted_at' => $contact->kyc_submitted_at ?? now(),
                'kyc_completed_at' => $kycData['completed_at'] ?? now(),
                'kyc_rejection_reasons' => null,
            ])->save();

            Log::info('[ApprovedKycContactSynchronizer] Synced approved KYC to contact', [
                'contact_id' => $contact->id,
                'mobile' => $mobile,
                'country' => $country,
                'transaction_id' => $kycData['transaction_id'] ?? null,
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ApprovedKycContactSynchronizer] Failed to sync KYC to contact', [
                'mobile' => $mobile,
                'country' => $country,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
