<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Affiliation;

use DomainException;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Affiliation\SponsorshipInvitationSnapshotData;
use LBHurtado\XChange\Models\AffiliationInvitationAuthority;
use LBHurtado\XProvisioning\Data\ProvisioningOfferCredentialData;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;

final readonly class LinkSponsorshipAuthorityToVoucher
{
    public function handle(
        Voucher $voucher,
        ProvisioningOfferCredentialData $credential,
    ): AffiliationInvitationAuthority {
        return DB::transaction(function () use ($voucher, $credential): AffiliationInvitationAuthority {
            $offer = $credential->offer->loadMissing('revision.request');

            if ($offer->request->profile !== ProvisioningProfile::AccountInvitation) {
                throw new DomainException('Only Account Invitation authority may be linked to onboarding.');
            }

            SponsorshipInvitationSnapshotData::fromArray((array) $offer->revision->snapshot);

            $existing = AffiliationInvitationAuthority::query()
                ->where('voucher_id', $voucher->getKey())
                ->orWhere('provisioning_offer_id', $offer->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing instanceof AffiliationInvitationAuthority) {
                if ((string) $existing->voucher_id !== (string) $voucher->getKey()
                    || (string) $existing->provisioning_offer_id !== (string) $offer->getKey()
                    || ! hash_equals($existing->snapshot_hash, (string) $offer->revision->snapshot_hash)) {
                    throw new DomainException('The onboarding authority is already linked to different immutable facts.');
                }

                return $existing;
            }

            return AffiliationInvitationAuthority::query()->create([
                'voucher_id' => $voucher->getKey(),
                'provisioning_offer_id' => $offer->getKey(),
                'encrypted_claim_token' => $credential->claimToken,
                'snapshot_hash' => $offer->revision->snapshot_hash,
            ]);
        }, attempts: 3);
    }
}
