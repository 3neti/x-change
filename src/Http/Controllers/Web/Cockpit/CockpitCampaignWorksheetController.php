<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetSummaryData;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\CreateCampaignWorksheetRequest;

class CockpitCampaignWorksheetController extends Controller
{
    public function __construct(private readonly CampaignWorksheetRepository $worksheets) {}

    public function index(Request $request): Response
    {
        $owner = $request->user();

        return Inertia::render('x-change/cockpit/Campaigns', [
            'worksheets' => $this->summariesFor($owner),
        ]);
    }

    public function store(CreateCampaignWorksheetRequest $request): RedirectResponse
    {
        $owner = $request->user();
        $validated = $request->validated();
        $worksheet = $this->worksheets->put(new CampaignWorksheetData(
            reference: null,
            ownerType: $this->ownerType($owner),
            ownerId: (string) $owner->getAuthIdentifier(),
            profile: $validated['profile'],
            name: $validated['name'],
            fulfillmentMode: $validated['fulfillment_mode'],
            deliveryPlan: $validated['delivery_plan'],
        ));

        return to_route('x-change.cockpit.campaigns.index')
            ->with('campaign_notice', sprintf('%s is ready for beneficiary entries.', $worksheet->name));
    }

    /**
     * @return array<int, array<string, int|string|null|array<int, string>>>
     */
    private function summariesFor(mixed $owner): array
    {
        return array_map(
            fn (CampaignWorksheetSummaryData $worksheet): array => [
                'reference' => $worksheet->reference,
                'profile' => $worksheet->profile,
                'name' => $worksheet->name,
                'currency' => $worksheet->currency,
                'status' => $worksheet->status,
                'fulfillment_mode' => $worksheet->fulfillmentMode,
                'delivery_plan' => $worksheet->deliveryPlan,
                'beneficiary_count' => $worksheet->beneficiaryCount,
                'principal_minor' => $worksheet->principalMinor,
                'updated_at' => $worksheet->updatedAt,
            ],
            $this->worksheets->summariesForOwner(
                $this->ownerType($owner),
                (string) $owner->getAuthIdentifier(),
            ),
        );
    }

    private function ownerType(mixed $owner): string
    {
        return $owner instanceof Model ? $owner->getMorphClass() : $owner::class;
    }
}
