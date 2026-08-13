<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim\Contributors;

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Contracts\Claim\ClaimSurfaceContributor;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceContextData;
use LBHurtado\XChange\Services\Claim\ClaimSurfaceBuilder;
use LBHurtado\XChange\Support\Claim\ClaimRequirementSummaryBuilder;
use LBHurtado\XChange\Support\Claim\PayoutDestinationRegistry;

/**
 * The primary deliverable of this slice: an issuer opening a claim URL for
 * their own already-claimed Pay Code gets a review console instead of the
 * generic redeemer page. Summary only -- never raw evidence (see
 * `ClaimRequirementSummaryBuilder`).
 */
final class IssuerClaimRequirementSummaryContributor implements ClaimSurfaceContributor
{
    private const ISSUER_ROLES = ['issuer', 'admin'];

    public function __construct(
        private readonly ClaimRequirementSummaryBuilder $requirements,
        private readonly PayoutDestinationRegistry $destinations,
    ) {}

    public function contribute(ClaimSurfaceBuilder $surface, ClaimSurfaceContextData $context): void
    {
        if (! in_array($context->viewer->role, self::ISSUER_ROLES, true)) {
            return;
        }

        if (! $context->hasClaimActivity()) {
            return;
        }

        $claim = $context->latestClaim();

        $surface
            ->setVisibility('issuer_console')
            ->setHeadline('Your Pay Code was claimed')
            ->setDescription('Review the submitted claim requirements and payout status.')
            ->addComponent('claim_requirement_summary', [
                'items' => $this->requirements->build(
                    $context->voucher,
                    $claim,
                    $context->approvalRequired,
                ),
            ]);

        $payoutRoute = $this->payoutRoute($context, $claim);

        if ($payoutRoute !== null) {
            $surface->addComponent('payout_route', $payoutRoute);
        }

        $surface->addAction(
            key: 'open_pay_code',
            label: 'Open Pay Code',
            href: $this->payCodeUrl($context->code),
            method: 'get',
            variant: 'secondary',
        );

        if ($context->approvalRequired) {
            $surface->addAction(
                key: 'approve_payout',
                label: 'Approve Payout',
                href: $this->approvalUrl($context),
                method: 'get',
                variant: 'primary',
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payoutRoute(ClaimSurfaceContextData $context, mixed $claim): ?array
    {
        $bankCode = $claim?->bank_code ?? data_get($context->voucherSummary, 'redemption.bank_code');

        if (! is_string($bankCode) || trim($bankCode) === '') {
            return null;
        }

        return $this->destinations->snapshot(
            bankCode: $bankCode,
            accountNumber: null,
            settlementRail: data_get($context->voucherSummary, 'redemption.settlement_rail'),
        );
    }

    private function payCodeUrl(string $code): string
    {
        if (Route::has('x-change.cockpit.pay-codes.show')) {
            return route('x-change.cockpit.pay-codes.show', ['code' => $code]);
        }

        if (Route::has('x-change.pay-codes.show')) {
            return route('x-change.pay-codes.show', ['code' => $code]);
        }

        return '/x/cockpit/pay-codes/'.$code;
    }

    private function approvalUrl(ClaimSurfaceContextData $context): string
    {
        if (Route::has('x-change.pay-codes.approval')) {
            return route('x-change.pay-codes.approval', ['code' => $context->code]);
        }

        return '/x/pay-codes/'.$context->code.'/approval';
    }
}
