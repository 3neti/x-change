<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use Symfony\Component\HttpFoundation\Response;

class ShareCockpitHeaderReadModel
{
    public function __construct(
        private readonly CockpitHeaderReadModelProviderContract $headerReadModels,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share(
            'cockpit_header_read_model',
            fn (): array => $this->headerReadModels->forOperator($request->user())->toArray(),
        );

        return $next($request);
    }
}
