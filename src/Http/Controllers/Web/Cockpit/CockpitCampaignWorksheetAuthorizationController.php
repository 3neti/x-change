<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\AuthorizeCampaignWorksheetRequest;
use RuntimeException;

class CockpitCampaignWorksheetAuthorizationController extends Controller
{
    public function __construct(
        private readonly CampaignWorksheetRepository $worksheets,
        private readonly IssueCampaignWorksheetApprovalPayCode $approvalPayCodes,
    ) {}

    public function store(AuthorizeCampaignWorksheetRequest $request, string $worksheet): RedirectResponse
    {
        $owner = $request->user();

        try {
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
