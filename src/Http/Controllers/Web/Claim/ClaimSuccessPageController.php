<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Support\Rider\XChangeRiderOutcomeResolver;
use LBHurtado\XChange\Support\Rider\XChangeRiderSubjectFactory;
use LBHurtado\XRider\Contracts\RiderExperienceResolverContract;

class ClaimSuccessPageController
{
    public function __invoke(
        string $code,
        RiderExperienceResolverContract $riders,
        XChangeRiderSubjectFactory $subjects,
        XChangeRiderOutcomeResolver $outcomes,
    ): Response {
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
                'route' => 'claim.success',
            ],
        ]);

        return Inertia::render('x-change/claim/Success', [
            'voucher' => [
                'code' => (string) $voucher->code,
                'amount' => data_get($voucher, 'cash.amount'),
                'currency' => data_get($voucher, 'cash.currency'),
            ],
            'claimOutcome' => $state->value,
            'rider' => $experience->toArray(),
            'redirectEndpoint' => route('x-change.claim.redirect', [
                'code' => $voucher->code,
            ]),
        ]);
    }
}
