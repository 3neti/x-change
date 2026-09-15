<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Actions\Leads\RunAuiInsuranceLeadScenario;
use LBHurtado\XChange\Models\LeadCampaign;

final class CockpitLeadCampaignScenarioRunnerController extends Controller
{
    public function show(Request $request): Response
    {
        $this->ensureEnabled();

        return Inertia::render('x-change/cockpit/LeadCampaignScenarioRunner', [
            'scenario' => [
                'schema' => 'x-change.cockpit.lead-campaign-scenario-runner.v1',
                'key' => 'aui_on_demand_insurance_payment',
                'title' => 'AUI On-Demand Insurance Payment',
                'description' => 'Create a public lead campaign endpoint that mints a zero-denominated intake Pay Code and routes the prospect through the normal claim UX.',
                'entry_point' => 'Public QR/link',
                'person_type' => 'Prospect',
                'pay_code_generation' => 'On scan',
                'claim_surface' => '/x/claim/{code}',
                'amount' => '₱0.00',
                'action_url' => route('x-change.cockpit.campaigns.lead-scenario-runner.store'),
                'fields' => [
                    'Name',
                    'Mobile',
                    'Email',
                    'Address',
                    'Birthday',
                    'Insurance product',
                    'Vehicle registration number',
                    'Driver license number',
                    'Payment reference',
                ],
            ],
            'recent_lead_campaigns' => $this->recentCampaigns($request),
        ]);
    }

    public function store(Request $request, RunAuiInsuranceLeadScenario $runner): RedirectResponse
    {
        $this->ensureEnabled();

        $campaign = $runner->handle($request->user());

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
