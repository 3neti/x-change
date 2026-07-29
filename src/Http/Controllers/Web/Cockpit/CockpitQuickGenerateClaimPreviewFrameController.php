<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewArtifactAccess;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CockpitQuickGenerateClaimPreviewFrameController extends Controller
{
    public function __invoke(
        Request $request,
        ClaimPreviewArtifact $claimPreviewArtifact,
        string $step,
        ClaimPreviewArtifactAccess $access,
    ): BinaryFileResponse {
        $access->assertReadable($claimPreviewArtifact, $request->user());
        $path = $access->framePath($claimPreviewArtifact, $step);

        return response()->file($path, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
