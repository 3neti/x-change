<?php

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Support\Rider\XChangeRiderOutcomeResolver;
use LBHurtado\XChange\Support\Rider\XChangeRiderSubjectFactory;
use LBHurtado\XRider\Contracts\RiderAnalyticsRecorderContract;
use LBHurtado\XRider\Contracts\RiderExperienceResolverContract;
use LBHurtado\XRider\Contracts\SuccessRedirectResolverContract;
use LBHurtado\XRider\Data\RiderAnalyticsEventData;

class ClaimRedirectController
{
    public function __invoke(
        string $code,
        RiderExperienceResolverContract $riders,
        SuccessRedirectResolverContract $redirects,
        RiderAnalyticsRecorderContract $analytics,
        XChangeRiderSubjectFactory $subjects,
        XChangeRiderOutcomeResolver $outcomes,
    ): RedirectResponse {
        $voucher = Voucher::query()
            ->where('code', $code)
            ->firstOrFail();

        $subject = $subjects->fromVoucher($voucher);
        $state = $outcomes->forVoucher($voucher);

        $experience = $riders->resolve($subject, [
            'state' => $state->value,
            'rider' => data_get($voucher->instructions?->toArray() ?? [], 'rider', []),
            'meta' => [
                'source' => 'x-change',
                'route' => 'claim.redirect',
            ],
        ]);

        $url = $redirects->resolve($experience);

        abort_if(blank($url), 404);

        $analytics->record(new RiderAnalyticsEventData(
            event: 'rider.redirect.started',
            reference: $subject->reference(),
            sourceType: $subject->type,
            sourceId: $subject->id,
            context: [
                'claim_outcome' => $state->value,
            ],
            meta: [
                'voucher_code' => (string) $voucher->code,
                'redirect_url' => $url,
            ],
        ));

        return redirect()->away($url);
    }
}
