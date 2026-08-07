<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewArtifactAccess;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewWebManifestPresenter;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;
use Symfony\Component\HttpFoundation\Response;

final class CockpitQuickGenerateClaimPreviewStepController extends Controller
{
    public function __invoke(
        Request $request,
        ClaimPreviewArtifact $claimPreviewArtifact,
        string $step,
        ClaimPreviewArtifactAccess $access,
        ClaimPreviewWebManifestPresenter $presenter,
    ): Response {
        $access->assertReadable($claimPreviewArtifact, $request->user());
        $access->step($claimPreviewArtifact, $step);
        $manifest = $presenter->present($claimPreviewArtifact, true);
        $presentedStep = collect(data_get($manifest, 'journey.steps', []))
            ->first(fn (mixed $candidate): bool => is_array($candidate)
                && ($candidate['key'] ?? null) === $step);

        $response = Inertia::render('x-change/claim/Preview', [
            'step' => $presentedStep,
            'viewport' => data_get($manifest, 'journey.viewport'),
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
