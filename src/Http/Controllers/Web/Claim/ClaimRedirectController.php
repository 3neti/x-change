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

        $analytics->record(new RiderAnalyticsEventData(
            event: 'rider.redirect.started',
            reference: $subject,
            meta: [
                'voucher_code' => (string) $voucher->code,
                'claim_outcome' => $state->value,
                'redirect_url' => $url,
            ],
        ));

        return redirect()->away($url);
    }
}
