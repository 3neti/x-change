<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\SendCampaignApprovalPayCode;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\SendCampaignApprovalPayCodeRequest;
use RuntimeException;

class CockpitCampaignApprovalDeliveryController extends Controller
{
    public function __construct(private readonly SendCampaignApprovalPayCode $sender) {}

    public function store(
        SendCampaignApprovalPayCodeRequest $request,
        string $worksheet,
        string $authorization,
        string $channel,
    ): RedirectResponse {
        abort_unless(in_array($channel, ['sms', 'email'], true), 404);
        abort_unless((bool) config("x-change.campaigns.delivery.{$channel}.enabled", false), 403);
        $owner = $request->user();
        abort_unless($owner instanceof Model, 403);

        $record = CampaignWorksheetAuthorization::query()
            ->where('reference', $authorization)
            ->whereHas('worksheet', fn ($query) => $query
                ->where('reference', $worksheet)
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getAuthIdentifier()))
            ->firstOrFail();
        $validated = $request->validated();

        try {
            $outcome = $this->sender->handle(
                $record,
                $owner,
                $channel,
                $validated['recipient'],
                $validated['request_token'],
            );
        } catch (RuntimeException $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)
                ->with('campaign_notice', $exception->getMessage());
        }

        $message = match ($outcome) {
            'queued' => sprintf('Approval Pay Code queued for %s delivery.', strtoupper($channel)),
            'already_requested' => 'This approval delivery request was already processed.',
            default => sprintf('%s delivery was not queued.', strtoupper($channel)),
        };

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', $message);
    }
}
