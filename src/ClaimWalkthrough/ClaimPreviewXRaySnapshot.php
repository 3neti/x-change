<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Services\XRay\VoucherXRayProjectionBuilder;
use LBHurtado\XRay\Contracts\XRayInspectorContract;
use LBHurtado\XRay\Data\XRayActorData;
use LBHurtado\XRay\Data\XRayContextData;
use LBHurtado\XRay\Resources\XRayResultResource;

final class ClaimPreviewXRaySnapshot
{
    public function __construct(
        private readonly VoucherLifecycleServiceContract $vouchers,
        private readonly VoucherXRayProjectionBuilder $projection,
        private readonly XRayInspectorContract $inspector,
    ) {}

    /** @return array<string, mixed> */
    public function forGuest(Voucher $voucher): array
    {
        $code = (string) $voucher->code;
        $projected = $this->projection->build($this->vouchers->showByCode($code), $voucher);
        $result = $this->inspector->handle(
            new XRayContextData(
                code: $code,
                actor: new XRayActorData(audience: 'guest'),
                channel: 'claim',
                request: ['code' => $code, 'channel' => 'claim'],
            ),
            $projected,
        );
        $snapshot = (array) XRayResultResource::make($result)->resolve(request());

        if (is_array($projected['presentation'] ?? null)) {
            $snapshot['presentation'] = $projected['presentation'];
        }

        if (is_array($projected['claim_workflow'] ?? null)) {
            $snapshot['claim_workflow'] = $projected['claim_workflow'];
        }

        return $this->sampleCode($snapshot, $code);
    }

    /** @return array<string, mixed> */
    private function sampleCode(array $snapshot, string $temporaryCode): array
    {
        $replace = static function (mixed $value) use (&$replace, $temporaryCode): mixed {
            if (is_string($value)) {
                return str_replace($temporaryCode, ClaimPreviewJourneyManifestFactory::SampleCode, $value);
            }

            if (is_array($value)) {
                return array_map($replace, $value);
            }

            return $value;
        };

        return $replace($snapshot);
    }
}
