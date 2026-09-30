<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Affiliation;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\AffiliationInvitationAuthority;
use LBHurtado\XProvisioning\Actions\AcceptProvisioningOffer;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;

final readonly class ActivateVoucherSponsorship
{
    public function __construct(private AcceptProvisioningOffer $acceptOffer) {}

    /** @param array<string, mixed> $evidence */
    public function handle(Voucher $voucher, Model $account, array $evidence): ?ProvisioningOffer
    {
        $authority = AffiliationInvitationAuthority::query()
            ->with('provisioningOffer.revision.request')
            ->where('voucher_id', $voucher->getKey())
            ->lockForUpdate()
            ->first();

        if (! $authority instanceof AffiliationInvitationAuthority) {
            return null;
        }

        return $this->acceptOffer->handle(
            claimToken: $authority->encrypted_claim_token,
            candidateType: $account->getMorphClass(),
            candidateReference: (string) $account->getKey(),
            evidence: $evidence,
        );
    }
}
