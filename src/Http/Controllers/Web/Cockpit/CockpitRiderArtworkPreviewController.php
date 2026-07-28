<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Requests\Cockpit\ResolveRiderArtworkPreviewRequest;
use LBHurtado\XChange\Services\Cockpit\RiderUrlArtworkPreviewResolver;

final class CockpitRiderArtworkPreviewController extends Controller
{
    public function __invoke(
        ResolveRiderArtworkPreviewRequest $request,
        RiderUrlArtworkPreviewResolver $resolver,
    ): JsonResponse {
        return response()->json([
            'schema' => 'x-change.cockpit.rider-artwork-preview.v1',
            ...$resolver->resolve((string) $request->validated('url')),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
