<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimSurfaceContributor;
use LBHurtado\XChange\Contracts\Claim\ClaimSurfaceResolverContract;
use LBHurtado\XChange\Contracts\Claim\ClaimViewerResolverContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceContextData;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceData;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceStateData;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Claim\Contributors\BaseClaimStatusContributor;
use LBHurtado\XChange\Services\Claim\Contributors\ClaimExperienceSummaryContributor;
use LBHurtado\XChange\Services\Claim\Contributors\IssuerClaimRequirementSummaryContributor;
use LBHurtado\XChange\Services\Claim\Contributors\NonActiveOutcomeContributor;
use LBHurtado\XChange\Services\Claim\Contributors\XRayPreviewContributor;

/**
 * This is the only place that decides what a claim-page viewer is allowed
 * to see. It runs entirely server-side; the Vue side only ever renders
 * whatever `components`/`actions` this produces (see `ClaimSurfaceRenderer`).
 *
 * Follow-up contributors this slice deliberately does not add yet:
 * `RedeemerReceiptContributor`, `OnboardingVoucherContributor`,
 * `CampaignAuthorizationContributor`, `PaynamicsOtpApprovalContributor`.
 */
final class DefaultClaimSurfaceResolver implements ClaimSurfaceResolverContract
{
    /**
     * @var list<class-string<ClaimSurfaceContributor>>
     */
    private const CONTRIBUTORS = [
        BaseClaimStatusContributor::class,
        XRayPreviewContributor::class,
        NonActiveOutcomeContributor::class,
        ClaimExperienceSummaryContributor::class,
        IssuerClaimRequirementSummaryContributor::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly ClaimViewerResolverContract $viewers,
        private readonly VoucherLifecycleServiceContract $vouchers,
    ) {}

    public function resolve(Voucher $voucher, ?Authenticatable $user): ClaimSurfaceData
    {
        $code = (string) $voucher->code;
        $viewer = $this->viewers->resolve($user, $voucher);
        $voucherSummary = (array) $this->vouchers->showByCode($code);
        if ($this->isCampaignPayoutRecovery($voucher)) {
            data_set($voucherSummary, 'status', 'active');
            data_set($voucherSummary, 'claimed', false);
            data_set($voucherSummary, 'fully_claimed', false);
            data_set($voucherSummary, 'operational_status.key', 'active');
            data_set($voucherSummary, 'operational_status.label', 'Ready to claim');
            data_set($voucherSummary, 'operational_status.can_claim', true);
            data_set($voucherSummary, 'operational_status.is_terminal', false);
            data_set($voucherSummary, 'operational_status.availability_label', 'Claimable');
        }
        $state = $this->state($voucherSummary);
        $claims = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->with('evidence')
            ->latest('id')
            ->get();

        $context = new ClaimSurfaceContextData(
            code: $code,
            voucher: $voucher,
            viewer: $viewer,
            state: $state,
            claims: $claims,
            approvalRequired: (bool) data_get($voucherSummary, 'approval.required', false),
            voucherSummary: $voucherSummary,
            approvalActionUrl: data_get($voucherSummary, 'approval.action_url'),
        );

        $builder = new ClaimSurfaceBuilder;

        foreach (self::CONTRIBUTORS as $contributorClass) {
            $this->container->make($contributorClass)->contribute($builder, $context);
        }

        return $builder->build($code, $viewer, $state);
    }

    /**
     * @param  array<string, mixed>  $voucherSummary
     */
    private function state(array $voucherSummary): ClaimSurfaceStateData
    {
        $operational = (array) data_get($voucherSummary, 'operational_status', []);

        return new ClaimSurfaceStateData(
            key: (string) ($operational['key'] ?? data_get($voucherSummary, 'status', 'unknown')),
            label: (string) ($operational['label'] ?? 'Unknown'),
            can_claim: (bool) ($operational['can_claim'] ?? false),
            terminal: (bool) ($operational['is_terminal'] ?? false),
        );
    }

    private function isCampaignPayoutRecovery(Voucher $voucher): bool
    {
        $metadata = $voucher->getAttribute('metadata');

        return data_get($metadata, 'instructions.metadata.custom.campaign.claim_activation') === 'provider_rejection'
            && data_get($metadata, 'treasury.pay_code_reservation.status') === 'recovery_pending';
    }
}
