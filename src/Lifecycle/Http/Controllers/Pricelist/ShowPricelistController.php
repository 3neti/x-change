<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ShowPricelistController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // TODO: Delegate to pricing/tariff service and serialize with PricelistResource.

        return response()->json([
            'data' => [],
            'meta' => ['message' => 'ShowPricelistController scaffolded.'],
        ], 501);
    }
}
