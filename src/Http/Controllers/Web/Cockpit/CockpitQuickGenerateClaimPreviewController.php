<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\ClaimWalkthrough\GenerateClaimPreview;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

final class CockpitQuickGenerateClaimPreviewController extends Controller
{
    public function __invoke(GeneratePayCodeRequest $request, GenerateClaimPreview $previews): JsonResponse
    {
        return $previews->handle($request);
    }
}
