<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivitySearchFilterData;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

class CockpitDashboardPageController extends Controller
{
    public function __construct(private readonly CockpitReadOnlyPageProps $props) {}

    public function __invoke(Request $request): Response
    {
        $operatorId = $request->user()?->getAuthIdentifier();

        return Inertia::render('x-change/cockpit/Dashboard', $this->props->toDashboardArray(
            campaignPlanningKey: $this->optionalString($request->query('campaign_planning_key')),
            campaignExecutionId: $this->optionalString($request->query('campaign_execution_id')),
            operatorId: is_scalar($operatorId) ? (string) $operatorId : null,
            operatorActivityFilters: CockpitOperatorIssuanceActivitySearchFilterData::normalize(
                search: $this->optionalString($request->query('activity_search')),
                statuses: $this->queryStringList($request->query('activity_status')),
                handoffStatuses: $this->queryStringList($request->query('activity_handoff_status')),
            ),
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

    /**
     * @return array<int, string>
     */
    private function queryStringList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->map(fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '')
            ->filter(fn (string $item): bool => $item !== '')
            ->values()
            ->all();
    }
}
