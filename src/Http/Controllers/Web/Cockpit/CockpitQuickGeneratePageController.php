<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

class CockpitQuickGeneratePageController extends Controller
{
    public function __construct(private readonly CockpitReadOnlyPageProps $props) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('x-change/cockpit/QuickGenerate', $this->props->toQuickGenerateArray(
            campaignPlanningKey: $this->optionalString($request->query('campaign_planning_key')),
            campaignExecutionId: $this->optionalString($request->query('campaign_execution_id')),
            campaignId: $this->optionalString($request->query('campaign_id')),
            campaignAudienceId: $this->optionalString($request->query('campaign_audience_id')),
            campaignRecipientId: $this->optionalString($request->query('campaign_recipient_id')),
            campaignSource: $this->optionalString($request->query('campaign_source')),
            campaignTemplateKey: $this->optionalString($request->query('campaign_template_key')),
            campaignAmount: $this->optionalScalar($request->query('campaign_amount')),
            campaignCurrency: $this->optionalString($request->query('campaign_currency')),
            campaignRecipientReference: $this->optionalString($request->query('campaign_recipient_reference')),
            campaignPurpose: $this->optionalString($request->query('campaign_purpose')),
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

    private function optionalScalar(mixed $value): int|float|string|null
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
