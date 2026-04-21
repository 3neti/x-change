<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\PricelistServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Pricelist\ListPricelistItemsRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Pricelist\PricelistItemCollectionResource;

class ListPricelistItemsController extends Controller
{
    public function __invoke(
        ListPricelistItemsRequest $request,
        PricelistServiceContract $pricelist,
    ): JsonResponse {
        $result = $pricelist->listItems($request->validated());

        return PricelistItemCollectionResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
