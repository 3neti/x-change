<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Events;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\EventLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Events\EventResource;

class ShowEventController extends Controller
{
    public function __invoke(
        string $event,
        EventLifecycleServiceContract $events,
    ): JsonResponse {
        $result = $events->show($event);

        return EventResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
