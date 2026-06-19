<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PayCode;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Exceptions\VoucherNotFound;
use LBHurtado\XChange\Services\XRay\VoucherXRayProjectionBuilder;
use LBHurtado\XRay\Contracts\XRayActorResolverContract;
use LBHurtado\XRay\Contracts\XRayInspectorContract;
use LBHurtado\XRay\Data\XRayContextData;
use LBHurtado\XRay\Resources\XRayResultResource;

class InspectPayCodeXRayController extends Controller
{
    public function __invoke(
        Request $request,
        VoucherLifecycleServiceContract $vouchers,
        VoucherXRayProjectionBuilder $projection,
        XRayActorResolverContract $actors,
        XRayInspectorContract $inspector,
    ): JsonResponse {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'channel' => ['nullable', 'string', 'max:32'],
        ]);

        $code = strtoupper(trim((string) $validated['code']));
        $channel = trim((string) ($validated['channel'] ?? 'claim')) ?: 'claim';

        try {
            $voucher = $vouchers->showByCode($code);
            $projected = $projection->build($voucher);
        } catch (VoucherNotFound) {
            $projected = [
                'status' => 'not_found',
                'requirements' => [],
                'next_actions' => [],
            ];
        }

        $result = $inspector->handle(
            new XRayContextData(
                code: $code,
                actor: $actors->resolve($request),
                channel: $channel,
                request: $request->only(['code', 'channel']),
            ),
            $projected,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'xray' => XRayResultResource::make($result)->resolve($request),
            ],
        ]);
    }
}
