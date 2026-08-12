<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim\Contributors;

use LBHurtado\XChange\Contracts\Claim\ClaimSurfaceContributor;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceContextData;
use LBHurtado\XChange\Services\Claim\ClaimSurfaceBuilder;

/**
 * Owns the `outcome_panel` component -- the calm replacement for the old
 * tilted `VoucherStatusStamp`. Runs whenever the state is terminal or no
 * longer claimable (redeemed/expired/cancelled/paid/etc.), for every
 * viewer, and also for issuer/admin viewers whenever a claim already
 * exists (so the issuer console always has an outcome summary, even for a
 * still-claimable partially-claimed voucher).
 */
final class NonActiveOutcomeContributor implements ClaimSurfaceContributor
{
    private const ISSUER_ROLES = ['issuer', 'admin'];

    public function contribute(ClaimSurfaceBuilder $surface, ClaimSurfaceContextData $context): void
    {
        $isIssuerViewer = in_array($context->viewer->role, self::ISSUER_ROLES, true);

        if (
            ! $context->state->terminal
            && $context->state->can_claim
            && ! ($isIssuerViewer && $context->hasClaimActivity())
        ) {
            return;
        }

        $props = [
            'status_key' => $context->state->key,
            'status_label' => $context->state->label,
            'code' => $context->code,
        ];

        if ($isIssuerViewer) {
            $props['formatted_amount'] = $this->formattedAmount($context);
            $props['redeemed_at'] = data_get($context->voucherSummary, 'redeemed_at');
            $props['payout_status'] = data_get($context->voucherSummary, 'redemption.status');
        }

        $surface->addComponent('outcome_panel', $props);
    }

    private function formattedAmount(ClaimSurfaceContextData $context): ?string
    {
        $amount = data_get($context->voucherSummary, 'amount');
        $currency = data_get($context->voucherSummary, 'currency', 'PHP');

        if (! is_numeric($amount)) {
            return null;
        }

        return (new \NumberFormatter('en_PH', \NumberFormatter::CURRENCY))
            ->formatCurrency((float) $amount, (string) $currency) ?: null;
    }
}
