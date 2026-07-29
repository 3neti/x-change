<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewArtifactAccess;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CockpitQuickGenerateClaimPreviewExportController extends Controller
{
    public function __invoke(
        Request $request,
        ClaimPreviewArtifact $claimPreviewArtifact,
        string $format,
        ClaimPreviewArtifactAccess $access,
    ): BinaryFileResponse {
        $access->assertReadable($claimPreviewArtifact, $request->user());
        $path = $access->exportPath($claimPreviewArtifact, $format);
        $filename = $format === 'pdf'
            ? 'claim-experience-preview.pdf'
            : 'claim-experience-preview.html';
        $disposition = $format === 'pdf' ? 'inline' : 'attachment';

        return response()->file($path, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
