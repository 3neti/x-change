<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Leads\CreateLeadCampaign;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StoreCampaignEndpointRequest;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;

final class CockpitCampaignEndpointController extends Controller
{
    public function store(
        StoreCampaignEndpointRequest $request,
        CreateLeadCampaign $createLeadCampaign,
    ): RedirectResponse {
        $owner = $request->user();
        $validated = $request->validated();

        $template = PayCodeTemplate::query()
            ->where('owner_type', $owner instanceof Model ? $owner->getMorphClass() : $owner::class)
            ->where('owner_id', (string) $owner->getAuthIdentifier())
            ->whereKey($validated['pay_code_template_id'])
            ->firstOrFail();

        $usageProfiles = (array) config('x-change.campaigns.usage_profiles', []);
        $usageProfile = (array) data_get($usageProfiles, (string) $validated['usage_key'], []);

        $campaign = $createLeadCampaign->handle($owner, $template, [
            ...$validated,
            'settings' => [
                'usage_key' => (string) $validated['usage_key'],
                'usage_label' => (string) data_get($usageProfile, 'label', $validated['usage_key']),
                'capabilities' => array_values(array_unique((array) ($validated['capabilities'] ?? []))),
                'entry_point' => (string) data_get($usageProfile, 'entry_point', 'public_qr_link'),
                'person_type' => (string) data_get($usageProfile, 'person_type', 'participant'),
                'pay_code_generation' => (string) data_get($usageProfile, 'pay_code_generation', 'on_scan'),
                'limits' => [
                    'budget_cap_minor' => $validated['budget_cap_minor'] ?? null,
                ],
                'availability' => [
                    'starts_at' => $validated['starts_at'] ?? null,
                    'daily_window_start' => $validated['daily_window_start'] ?? null,
                    'daily_window_end' => $validated['daily_window_end'] ?? null,
                    'timezone' => $validated['timezone'] ?? config('app.timezone', 'UTC'),
                ],
            ],
        ]);

        return to_route('x-change.cockpit.campaigns.index')
            ->with('campaign_notice', sprintf('%s is ready to share.', $campaign->title));
    }

    public function pause(Request $request, string $campaign, AuditLoggerContract $audit): RedirectResponse
    {
        $endpoint = $this->endpointForOwner($request, $campaign);
        $this->setEndpointStatus($endpoint, 'paused', $request, $audit);

        return to_route('x-change.cockpit.campaigns.index')
            ->with('campaign_notice', sprintf('%s is paused. Existing Pay Codes remain untouched.', $endpoint->title));
    }

    public function resume(Request $request, string $campaign, AuditLoggerContract $audit): RedirectResponse
    {
        $endpoint = $this->endpointForOwner($request, $campaign);
        $this->setEndpointStatus($endpoint, 'active', $request, $audit);

        return to_route('x-change.cockpit.campaigns.index')
            ->with('campaign_notice', sprintf('%s is open again.', $endpoint->title));
    }

    private function endpointForOwner(Request $request, string $reference): LeadCampaign
    {
        $owner = $request->user();

        return LeadCampaign::query()
            ->where('owner_type', $owner instanceof Model ? $owner->getMorphClass() : $owner::class)
            ->where('owner_id', (string) $owner->getAuthIdentifier())
            ->where('reference', $reference)
            ->firstOrFail();
    }

    private function setEndpointStatus(
        LeadCampaign $campaign,
        string $status,
        Request $request,
        AuditLoggerContract $audit,
    ): void {
        if ($campaign->status === $status) {
            return;
        }

        $previous = $campaign->status;
        $campaign->forceFill(['status' => $status])->save();

        $audit->log('campaign.endpoint.status_changed', [
            'campaign_reference' => $campaign->reference,
            'previous_status' => $previous,
            'status' => $status,
            'actor_type' => $request->user() instanceof Model ? $request->user()->getMorphClass() : $request->user()::class,
            'actor_id' => (string) $request->user()->getAuthIdentifier(),
        ]);
    }
}
