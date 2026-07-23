<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Cockpit\FundingInstructionPresenter;
use LBHurtado\XChange\Services\Funding\FundingIntentOwnerGuard;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

class CockpitFundingInstructionController extends Controller
{
    public function __invoke(
        Request $request,
        FundingIntent $intent,
        FundingIntentOwnerGuard $owners,
        FundingInstructionPresenter $instructions,
    ): JsonResponse {
        $owners->authorize($intent, $request->user());

        if ($intent->expires_at?->isPast() !== false
            || ! in_array($intent->status, [
                FundingIntentStatus::AwaitingFunds,
                FundingIntentStatus::EvidenceReceived,
                FundingIntentStatus::Verifying,
            ], true)) {
            throw new GoneHttpException('These funding instructions are no longer available.');
        }

        return response()
            ->json([
                'instruction' => $instructions->forIntent($intent),
            ])
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }
}
