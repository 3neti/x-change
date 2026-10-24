<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitEntryPageController;
use LBHurtado\XChange\Services\Configuration\ApplicationProfileCompiler;
use LBHurtado\XChange\Services\Funding\FundingProjectionChannel;
use LBHurtado\XChange\Services\QrArtifactFactory;
use Symfony\Component\HttpFoundation\Response;

class ShareCockpitHeaderReadModel
{
    public function __construct(
        private readonly CockpitHeaderReadModelProviderContract $headerReadModels,
        private readonly FundingProjectionChannel $fundingChannels,
        private readonly QrArtifactFactory $qrArtifacts,
        private readonly ApplicationProfileCompiler $profiles,
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
        Inertia::share(
            'cockpit_claim_entry_artifact',
            fn (): array => $this->qrArtifacts->claimEntry()->toArray(),
        );
        Inertia::share(
            'application_profile',
            fn (): array => $this->profiles->fromConfig()->toArray(),
        );
        Inertia::share(
            'application_journey',
            function () use ($request): ?array {
                $requested = $request->query('journey');
                if (! is_string($requested)) {
                    return null;
                }

                foreach ($this->profiles->fromConfig()->navigation as $journey) {
                    if ($journey['key'] === $requested) {
                        return $journey;
                    }
                }

                return null;
            },
        );

        return $next($request);
    }
}
