<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Reconciliations;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\ReconciliationLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Reconciliations\ResolveReconciliationRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Reconciliations\ReconciliationResolutionResource;

class ResolveReconciliationController extends Controller
{
    public function __invoke(
        string $reconciliation,
        ResolveReconciliationRequest $request,
        ReconciliationLifecycleServiceContract $reconciliations,
    ): JsonResponse {
        $result = $reconciliations->resolve($reconciliation, $request->validated());

        return ReconciliationResolutionResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
