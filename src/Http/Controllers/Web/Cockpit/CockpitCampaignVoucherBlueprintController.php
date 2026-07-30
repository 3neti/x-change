<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\UpdateCampaignVoucherBlueprintRequest;
use LBHurtado\XChange\Services\Campaigns\CampaignVoucherInstructionBlueprintSanitizer;

class CockpitCampaignVoucherBlueprintController extends Controller
{
    public function __construct(
        private readonly CampaignWorksheetRepository $worksheets,
        private readonly CampaignVoucherInstructionBlueprintSanitizer $sanitizer,
    ) {}

    public function __invoke(
        UpdateCampaignVoucherBlueprintRequest $request,
        string $worksheet,
    ): RedirectResponse {
        $owner = $request->user();
        $validated = $request->validated();

        try {
            $this->worksheets->updateInstructionBlueprint(
                $worksheet,
                $owner->getMorphClass(),
                (string) $owner->getAuthIdentifier(),
                $this->sanitizer->sanitize($validated['blueprint']),
                CampaignVoucherInstructionBlueprintSanitizer::SCHEMA,
                (int) $validated['expected_revision'],
            );
        } catch (InvalidArgumentException $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)
                ->withErrors(['blueprint' => $exception->getMessage()]);
        }

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', 'The common Pay Code experience was saved for every beneficiary.');
    }
}
