<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Commissioning;

use Illuminate\Http\Response;
use LBHurtado\XChange\Http\Middleware\EnsureXChangeIsCommissioned;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;

final readonly class CommissioningStatusController
{
    public function __construct(private CommissioningStateResolver $commissioning) {}

    public function __invoke(): Response
    {
        $commissioning = $this->commissioning->resolve();

        return response()->view('x-change::commissioning.status', [
            'commissioning' => $commissioning,
            'checkedAt' => now(),
        ], $commissioning->isOperational()
            ? Response::HTTP_OK
            : Response::HTTP_SERVICE_UNAVAILABLE, EnsureXChangeIsCommissioned::headers());
    }
}
