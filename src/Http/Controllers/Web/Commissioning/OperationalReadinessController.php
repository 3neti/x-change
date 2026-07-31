<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Commissioning;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use LBHurtado\XChange\Http\Middleware\EnsureXChangeIsCommissioned;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;

final readonly class OperationalReadinessController
{
    public function __construct(private CommissioningStateResolver $commissioning) {}

    public function __invoke(): JsonResponse
    {
        $state = $this->commissioning->resolve();
        $status = $state->isOperational()
            ? Response::HTTP_OK
            : Response::HTTP_SERVICE_UNAVAILABLE;
        $headers = $state->isOperational()
            ? ['Cache-Control' => 'no-store, private, max-age=0']
            : EnsureXChangeIsCommissioned::headers();

        return response()->json([
            'ready' => $state->isOperational(),
            'state' => $state->state->value,
        ], $status, $headers);
    }
}
