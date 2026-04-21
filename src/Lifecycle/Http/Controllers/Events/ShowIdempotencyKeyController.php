<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Events;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\EventLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Events\IdempotencyKeyResource;

class ShowIdempotencyKeyController extends Controller
{
    public function __invoke(
        string $key,
        EventLifecycleServiceContract $events,
    ): JsonResponse {
        $result = $events->showIdempotencyKey($key);

        return IdempotencyKeyResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
