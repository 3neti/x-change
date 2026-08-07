<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewArtifactAccess;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;
use Symfony\Component\HttpFoundation\Response;

final class CockpitQuickGenerateClaimPreviewStepController extends Controller
{
    public function __invoke(
        Request $request,
        ClaimPreviewArtifact $claimPreviewArtifact,
        string $step,
        ClaimPreviewArtifactAccess $access,
    ): Response {
        $access->assertReadable($claimPreviewArtifact, $request->user());

        $response = Inertia::render('x-change/claim/Preview', [
            'step' => $access->step($claimPreviewArtifact, $step),
            'viewport' => [
                'profile' => (string) data_get($claimPreviewArtifact->metadata, 'journey.viewport.profile', 'mobile_claim_v1'),
                'width' => (int) data_get($claimPreviewArtifact->metadata, 'journey.viewport.width', 360),
                'height' => (int) data_get($claimPreviewArtifact->metadata, 'journey.viewport.height', 720),
            ],
        ])->toResponse($request);

        $response->headers->add([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "frame-ancestors 'self'",
        ]);

        return $response;
    }
}
