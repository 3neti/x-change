<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\PricelistServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Pricelist\PricelistResource;

class ShowPricelistController extends Controller
{
    public function __invoke(PricelistServiceContract $pricelist): JsonResponse
    {
        $result = $pricelist->showPricelist();

        return PricelistResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
