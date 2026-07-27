<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

class CockpitPayCodeExplorerPageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly CockpitTreasuryAccessContract $treasuryAccess,
    ) {}

    public function __invoke(Request $request): Response
    {
        $actor = $request->user();

        return Inertia::render('x-change/cockpit/PayCodeExplorer', $this->props->toPayCodeExplorerArray(
            campaignPlanningKey: $this->optionalString($request->query('campaign_planning_key')),
            campaignExecutionId: $this->optionalString($request->query('campaign_execution_id')),
            campaignId: $this->optionalString($request->query('campaign_id')),
            campaignAudienceId: $this->optionalString($request->query('campaign_audience_id')),
            campaignRecipientId: $this->optionalString($request->query('campaign_recipient_id')),
            campaignSource: $this->optionalString($request->query('campaign_source')),
            activityCode: $this->optionalString($request->query('activity_code')),
            activitySource: $this->optionalString($request->query('activity_source')),
            search: $this->optionalString($request->query('search')),
            status: $this->optionalString($request->query('status')),
            canViewTechnicalDetails: $actor !== null
                && $this->treasuryAccess->canViewTreasuryControls($actor),
        ));
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
