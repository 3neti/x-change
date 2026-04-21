<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Reconciliations;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\ReconciliationLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Reconciliations\ReconciliationCollectionResource;

class ListReconciliationsController extends Controller
{
    public function __invoke(
        Request $request,
        ReconciliationLifecycleServiceContract $reconciliations,
    ): JsonResponse {
        $result = $reconciliations->list($request->query());

        return ReconciliationCollectionResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
