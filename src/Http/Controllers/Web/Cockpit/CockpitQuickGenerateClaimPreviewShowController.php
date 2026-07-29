<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewArtifactAccess;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewWebManifestPresenter;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;

final class CockpitQuickGenerateClaimPreviewShowController extends Controller
{
    public function __invoke(
        Request $request,
        ClaimPreviewArtifact $claimPreviewArtifact,
        ClaimPreviewArtifactAccess $access,
        ClaimPreviewWebManifestPresenter $presenter,
    ): JsonResponse {
        $access->assertReadable($claimPreviewArtifact, $request->user());

        return response()
            ->json($presenter->present($claimPreviewArtifact, true))
            ->withHeaders($this->headers());
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
