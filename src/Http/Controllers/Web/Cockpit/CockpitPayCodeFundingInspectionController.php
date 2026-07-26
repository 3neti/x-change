<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\InspectCockpitPayCodeFundingRequest;
use LBHurtado\XChange\Services\Funding\AccountFundingPayCodeJournal;
use LBHurtado\XChange\Services\Funding\PayCodeFundingEligibility;
use LBHurtado\XChange\Services\Funding\PayCodeFundingInspectionStore;

final class CockpitPayCodeFundingInspectionController extends Controller
{
    public function __invoke(
        InspectCockpitPayCodeFundingRequest $request,
        PayCodeFundingEligibility $eligibility,
        PayCodeFundingInspectionStore $inspections,
        AccountFundingPayCodeJournal $journal,
    ): RedirectResponse {
        $voucher = Voucher::query()
            ->where('code', $request->validated('code'))
            ->first();

        if (! $voucher instanceof Voucher) {
            return $this->redirectWithPreview([
                'eligible' => false,
                'status' => 'not_found',
                'message' => 'That Pay Code could not be found.',
            ]);
        }

        $decision = $eligibility->evaluate($voucher);
        $preview = [
            'eligible' => $decision->eligible,
            'status' => $decision->status,
            'message' => $decision->message,
            'code_hint' => '••••'.mb_substr((string) $voucher->code, -4),
            'amount' => $decision->amountMinor === null || $decision->currency === null
                ? null
                : Number::currency(
                    $decision->amountMinor / 100,
                    in: $decision->currency,
                    locale: 'en_PH',
                ),
            'currency' => $decision->currency,
            'expires_at' => $voucher->expires_at?->toIso8601String(),
            'provider_calls' => false,
        ];

        if ($decision->eligible) {
            $inspectionToken = $inspections->issue(
                $voucher,
                $request->user(),
            );
            $preview['inspection_token'] = $inspectionToken;
            $journal->recordInspected(
                $voucher->getKey(),
                $request->user(),
                $inspectionToken,
            );
        }

        return $this->redirectWithPreview($preview);
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function redirectWithPreview(array $preview): RedirectResponse
    {
        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with('pay_code_funding_preview', $preview);
    }
}
