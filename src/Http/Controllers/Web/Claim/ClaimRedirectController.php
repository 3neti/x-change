<?php

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Claim\VoucherRiderFallbackPolicy;
use LBHurtado\XChange\Support\Claim\ClaimRiderHandoff;
use LBHurtado\XChange\Support\Rider\XChangeRiderOutcomeResolver;
use LBHurtado\XChange\Support\Rider\XChangeRiderSubjectFactory;
use LBHurtado\XRider\Contracts\RiderAnalyticsRecorderContract;
use LBHurtado\XRider\Contracts\RiderExperienceResolverContract;
use LBHurtado\XRider\Contracts\SuccessRedirectResolverContract;
use LBHurtado\XRider\Data\RiderAnalyticsEventData;
use LBHurtado\XRider\Enums\RiderOutcomeState;

class ClaimRedirectController
{
    public function __invoke(
        string $code,
        RiderExperienceResolverContract $riders,
        SuccessRedirectResolverContract $redirects,
        RiderAnalyticsRecorderContract $analytics,
        XChangeRiderSubjectFactory $subjects,
        XChangeRiderOutcomeResolver $outcomes,
        VoucherRiderFallbackPolicy $riderFallbacks,
        ClaimRiderHandoff $handoff,
    ): RedirectResponse {
        $voucher = Voucher::query()
            ->where('code', $code)
            ->firstOrFail();

        $claim = $handoff->claim($voucher);
        abort_unless($claim !== null, 404);

        $subject = $subjects->fromVoucher($voucher);
        $outcome = $outcomes->forVoucher($voucher);
        $riderState = $outcome === RiderOutcomeState::RejectedFailure
            ? RiderOutcomeState::AcceptedPending
            : $outcome;
        $instructions = $voucher->instructions?->toArray() ?? [];

        abort_unless($riderFallbacks->shouldResolve($instructions), 404);

        try {
            $experience = $riders->resolve($subject, [
                'state' => $riderState->value,
                'rider' => data_get($instructions, 'rider', []),
                'meta' => [
                    'source' => 'x-change',
                    'route' => 'claim.redirect',
                ],
            ]);

            $url = $redirects->resolve($experience);
        } catch (\Throwable) {
            $url = null;
        }

        $destination = data_get($instructions, 'rider.url');
        $parts = is_string($url) ? parse_url($url) : false;

        if (! is_string($url) || ! is_string($destination)
            || ! hash_equals($destination, $url)
            || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user']) || isset($parts['pass'])
            || ! $handoff->recordRedirect($voucher, $claim)) {
            return redirect()->route('x-change.claim.success', ['code' => $voucher->code])
                ->with('claim_rider_handoff_failed', true);
        }

        try {
            $analytics->record(new RiderAnalyticsEventData(
                event: 'rider.redirect.started',
                reference: $subject->reference(),
                sourceType: $subject->type,
                sourceId: $subject->id,
                context: [
                    'claim_outcome' => $outcome->value,
                ],
                meta: [
                    'voucher_code' => (string) $voucher->code,
                    'redirect_url' => $url,
                ],
            ));
        } catch (\Throwable $exception) {
            Log::warning('Claim rider analytics could not be recorded', [
                'voucher_id' => $voucher->getKey(),
                'claim_id' => $claim->getKey(),
                'exception' => $exception::class,
            ]);
        }

        return redirect()->away($url);
    }
}
