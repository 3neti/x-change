<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Reconciliations;

use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

#[ExcludeAllRoutesFromDocs]
class ShowReconciliationController extends Controller
{
    public function __invoke(mixed $reconciliation): JsonResponse
    {
        // TODO: Serialize existing reconciliation model/data with ReconciliationResource.

        return response()->json([
            'data' => [],
            'meta' => ['message' => 'ShowReconciliationController scaffolded.'],
        ], 501);
    }
}
