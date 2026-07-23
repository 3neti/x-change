<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Funding\FundingIntentOwnerGuard;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CockpitFundingVerificationCheckController extends Controller
{
    public function __invoke(
        Request $request,
        FundingIntent $intent,
        FundingIntentOwnerGuard $owners,
    ): RedirectResponse {
        $operator = $request->user();
        $owners->authorize($intent, $operator);

        if ($intent->provider_code !== 'netbank'
            || ! (bool) config('x-change.funding.providers.netbank.enabled', false)
            || $intent->status !== FundingIntentStatus::AwaitingFunds
            || $intent->expires_at?->isPast() !== false) {
            throw new ConflictHttpException('This Funding Intent is not eligible for a NetBank check.');
        }

        VerifyFundingIntentJob::dispatch(
            fundingIntentId: $intent->getKey(),
            providerCode: $intent->provider_code,
            trigger: FundingVerificationTrigger::Operator,
            actorId: (string) $operator->getAuthIdentifier(),
        )->afterCommit();

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with('funding_notice', 'NetBank verification queued. Provider history will determine the result.');
    }
}
