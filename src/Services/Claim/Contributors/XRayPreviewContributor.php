<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim\Contributors;

use Illuminate\Http\Request;
use LBHurtado\XChange\Contracts\Claim\ClaimSurfaceContributor;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceContextData;
use LBHurtado\XChange\Services\Claim\ClaimSurfaceBuilder;
use LBHurtado\XChange\Services\XRay\VoucherXRayProjectionBuilder;
use LBHurtado\XRay\Contracts\XRayActorResolverContract;
use LBHurtado\XRay\Contracts\XRayInspectorContract;
use LBHurtado\XRay\Data\XRayContextData;
use LBHurtado\XRay\Resources\XRayResultResource;

/**
 * Adds the same policy-filtered x-ray preview `InspectPayCodeXRayController`
 * already exposes over the API, but server-resolved for the initial page
 * load. This never adds new disclosure logic -- it reuses the existing
 * `VoucherXRayProjectionBuilder` + `XRayInspectorContract` pipeline as-is.
 */
final class XRayPreviewContributor implements ClaimSurfaceContributor
{
    private const ELIGIBLE_ROLES = ['guest', 'other_authenticated', 'redeemer'];

    public function __construct(
        private readonly VoucherXRayProjectionBuilder $projection,
        private readonly XRayActorResolverContract $actors,
        private readonly XRayInspectorContract $inspector,
        private readonly Request $request,
    ) {}

    public function contribute(ClaimSurfaceBuilder $surface, ClaimSurfaceContextData $context): void
    {
        if (! in_array($context->viewer->role, self::ELIGIBLE_ROLES, true)) {
            return;
        }

        if ($context->state->terminal || ! $context->state->can_claim) {
            return;
        }

        $projected = $this->projection->build($context->voucherSummary, $context->voucher);

        $result = $this->inspector->handle(
            new XRayContextData(
                code: $context->code,
                actor: $this->actors->resolve($this->request),
                channel: 'claim',
                request: ['code' => $context->code, 'channel' => 'claim'],
            ),
            $projected,
        );

        $xray = (array) XRayResultResource::make($result)->resolve($this->request);

        if (is_array($projected['presentation'] ?? null)) {
            $xray['presentation'] = $projected['presentation'];
        }

        $surface->addComponent('xray_preview', $xray);
    }
}
