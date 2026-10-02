<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceDiscoveryService;

final class PublicIssuanceDiscoveryController extends Controller
{
    public function __invoke(PublicIssuanceDiscoveryService $discovery): JsonResponse
    {
        return response()->json($discovery->describe()->toArray());
    }
}
