<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetImportRepository;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetImportData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XCampaign\Data\CampaignWorksheetSummaryData;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\CreateCampaignWorksheetRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\CreateCampaignWorksheetRowRequest;

class CockpitCampaignWorksheetController extends Controller
{
    public function __construct(
        private readonly CampaignWorksheetRepository $worksheets,
        private readonly CampaignWorksheetImportRepository $imports,
    ) {}

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

    public function show(Request $request, string $worksheet): Response
    {
        return Inertia::render('x-change/cockpit/CampaignWorksheet', [
            'worksheet' => $this->worksheetFor($worksheet, $request->user()),
            'imports' => $this->importsFor($worksheet, $request->user()),
        ]);
    }

    public function addRow(
        CreateCampaignWorksheetRowRequest $request,
        string $worksheet,
    ): RedirectResponse {
        $owner = $request->user();
        $validated = $request->validated();
        $campaign = $this->worksheets->appendRow(
            $worksheet,
            $this->ownerType($owner),
            (string) $owner->getAuthIdentifier(),
            new CampaignWorksheetRowData(
                reference: null,
                ordinal: 0,
                beneficiary: array_filter([
                    'name' => $validated['name'] ?: null,
                    'mobile' => $validated['mobile'] ?: null,
                    'bank_account' => $validated['bank_account'] ?: null,
                    'email' => $validated['email'] ?: null,
                    'remarks' => $validated['remarks'] ?: null,
                    'external_reference' => $validated['external_reference'] ?: null,
                ]),
                amountMinor: $validated['amount_minor'],
                deliveryPreference: $validated['delivery_preference'],
            ),
        );

        return to_route('x-change.cockpit.campaigns.show', $campaign->reference)
            ->with('campaign_notice', 'Beneficiary added to the draft worksheet.');
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

    /**
     * @return array<string, mixed>
     */
    private function worksheetFor(string $reference, mixed $owner): array
    {
        $worksheet = $this->worksheets->findForOwner(
            $reference,
            $this->ownerType($owner),
            (string) $owner->getAuthIdentifier(),
        );

        abort_unless($worksheet instanceof CampaignWorksheetData, 404);

        return [
            'reference' => $worksheet->reference,
            'profile' => $worksheet->profile,
            'name' => $worksheet->name,
            'currency' => $worksheet->currency,
            'status' => $worksheet->status,
            'fulfillment_mode' => $worksheet->fulfillmentMode,
            'delivery_plan' => $worksheet->deliveryPlan,
            'rows' => array_map(fn (CampaignWorksheetRowData $row): array => [
                'reference' => $row->reference,
                'ordinal' => $row->ordinal,
                'beneficiary' => $row->beneficiary,
                'amount_minor' => $row->amountMinor,
                'delivery_preference' => $row->deliveryPreference,
                'status' => $row->status,
            ], $worksheet->rows),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function importsFor(string $reference, mixed $owner): array
    {
        return array_map(fn (CampaignWorksheetImportData $import): array => [
            'reference' => $import->reference,
            'status' => $import->status,
            'source_format' => $import->sourceFormat,
            'row_count' => $import->rowCount,
            'valid_count' => count($import->validRows),
            'validation_errors' => $import->validationErrors,
            'mapping' => $import->mapping,
        ], $this->imports->forOwner($reference, $this->ownerType($owner), (string) $owner->getAuthIdentifier()));
    }
}
