<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitEntryPageController;
use LBHurtado\XChange\Services\Funding\FundingProjectionChannel;
use Symfony\Component\HttpFoundation\Response;

class ShareCockpitHeaderReadModel
{
    public function __construct(
        private readonly CockpitHeaderReadModelProviderContract $headerReadModels,
        private readonly FundingProjectionChannel $fundingChannels,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share(
            'cockpit_header_read_model',
            function () use ($request): array {
                $readModel = $this->headerReadModels->forOperator($request->user())->toArray();
                $operator = $request->user();

                if ($operator instanceof Model) {
                    $readModel['funding_realtime'] = [
                        'enabled' => (bool) config('x-change.funding.broadcast_enabled', false),
                        'channel' => $this->fundingChannels->nameForOwner($operator),
                        'event' => '.FundingProjectionChanged',
                    ];
                }

                return $readModel;
            },
        );
        Inertia::share(
            'cockpit_entry_notice',
            fn (): mixed => $request->session()->get(CockpitEntryPageController::NOTICE_SESSION_KEY),
        );

        return $next($request);
    }
}
