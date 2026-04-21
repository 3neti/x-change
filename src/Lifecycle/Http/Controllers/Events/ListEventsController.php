<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Events;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\EventLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Events\EventCollectionResource;

class ListEventsController extends Controller
{
    public function __invoke(
        Request $request,
        EventLifecycleServiceContract $events,
    ): JsonResponse {
        $result = $events->list($request->query());

        return EventCollectionResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
