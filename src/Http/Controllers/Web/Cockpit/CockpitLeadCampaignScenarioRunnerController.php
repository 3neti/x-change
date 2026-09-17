<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Actions\Leads\RunAuiInsuranceLeadScenario;
use LBHurtado\XChange\Actions\Leads\RunDisbursableFeedbackEndpointScenario;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\RunLeadCampaignScenarioRequest;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Services\Leads\LeadCampaignBrowserScenarioCatalog;

final class CockpitLeadCampaignScenarioRunnerController extends Controller
{
    public function __construct(
        private readonly LeadCampaignBrowserScenarioCatalog $scenarios,
    ) {}

    public function show(Request $request): Response
    {
        $this->ensureEnabled();

        return Inertia::render('x-change/cockpit/LeadCampaignScenarioRunner', [
            'scenario' => $this->scenarios->find($request->string('scenario')->toString()),
            'scenarios' => $this->scenarios->all(),
            'recent_lead_campaigns' => $this->recentCampaigns($request),
        ]);
    }

    public function store(
        RunLeadCampaignScenarioRequest $request,
        RunAuiInsuranceLeadScenario $auiScenario,
        RunDisbursableFeedbackEndpointScenario $feedbackScenario,
    ): RedirectResponse {
        $this->ensureEnabled();

        $campaign = match ($request->validated('scenario')) {
            'aui_on_demand_insurance_payment' => $auiScenario->handle($request->user()),
            default => $feedbackScenario->handle($request->user()),
        };

        return to_route('x-change.leads.start', [
            'merchant_slug' => $campaign->merchant_slug,
            'endpoint_slug' => $campaign->endpoint_slug,
        ]);
    }

    private function ensureEnabled(): void
    {
        abort_unless(
            (bool) config('x-change.leads.scenario_runner.enabled', ! app()->isProduction()),
            404,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentCampaigns(Request $request): array
    {
        $owner = $request->user();

        return LeadCampaign::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getAuthIdentifier())
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (LeadCampaign $campaign): array => [
                'reference' => $campaign->reference,
                'title' => $campaign->title,
                'merchant_display_name' => $campaign->merchant_display_name,
                'public_url' => route('x-change.leads.start', [
                    'merchant_slug' => $campaign->merchant_slug,
                    'endpoint_slug' => $campaign->endpoint_slug,
                ]),
                'usage_count' => $campaign->usage_count,
                'last_started_at' => $campaign->last_started_at?->toIso8601String(),
                'status' => $campaign->status,
            ])
            ->values()
            ->all();
    }
}
