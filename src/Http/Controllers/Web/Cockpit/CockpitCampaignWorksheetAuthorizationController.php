<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetImportRepository;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\AuthorizeCampaignWorksheetRequest;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityIssuanceGuard;
use RuntimeException;

class CockpitCampaignWorksheetAuthorizationController extends Controller
{
    public function __construct(
        private readonly CampaignWorksheetRepository $worksheets,
        private readonly CampaignWorksheetImportRepository $imports,
        private readonly IssueCampaignWorksheetApprovalPayCode $approvalPayCodes,
        private readonly InstructionCapabilityIssuanceGuard $instructionCapabilities,
    ) {}

    public function store(AuthorizeCampaignWorksheetRequest $request, string $worksheet): RedirectResponse
    {
        $owner = $request->user();

        try {
            $hasUnappliedValidRows = collect($this->imports->forOwner(
                $worksheet,
                $owner->getMorphClass(),
                (string) $owner->getAuthIdentifier(),
            ))->contains(fn ($import): bool => collect($import->stagedRows)
                ->where('status', 'valid')
                ->whereNull('applied_at')
                ->isNotEmpty());
            if ($hasUnappliedValidRows) {
                throw new InvalidArgumentException(
                    'Add or discard every valid staged import before freezing the worksheet.',
                );
            }

            $campaign = $this->worksheets->findForOwner(
                $worksheet,
                $owner->getMorphClass(),
                (string) $owner->getAuthIdentifier(),
            );

            if ($campaign === null) {
                throw new InvalidArgumentException('The Campaign worksheet was not found.');
            }

            $this->instructionCapabilities->ensureAvailable($campaign->instructionBlueprint);

            $this->worksheets->freeze($worksheet, $owner->getMorphClass(), (string) $owner->getAuthIdentifier());
            $authorization = $this->approvalPayCodes->handle($worksheet, $owner);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', $exception->getMessage());
        }

        return to_route('x-change.cockpit.campaigns.show', $worksheet)->with(
            'campaign_notice',
            sprintf('Worksheet frozen. Officer approval Pay Code %s is ready to share.', $authorization->approval_pay_code),
        );
    }
}
