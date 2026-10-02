<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Requests\PublicIssuance\PublicIssuanceRequest;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceEstimateService;

final class PublicIssuanceEstimateController extends Controller
{
    public function __invoke(PublicIssuanceRequest $request, PublicIssuanceEstimateService $estimates): JsonResponse
    {
        return response()->json($estimates->estimate($request->validated())->toArray());
    }
}
