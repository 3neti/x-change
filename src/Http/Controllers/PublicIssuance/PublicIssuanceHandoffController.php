<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Requests\PublicIssuance\PublicIssuanceRequest;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceHandoffService;

final class PublicIssuanceHandoffController extends Controller
{
    public function __invoke(PublicIssuanceRequest $request, PublicIssuanceHandoffService $handoffs): JsonResponse
    {
        return response()->json($handoffs->prepare($request->validated())->toArray());
    }
}
