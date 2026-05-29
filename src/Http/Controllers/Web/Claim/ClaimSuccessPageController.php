<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
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
    ): Response|JsonResponse {
        $voucher = Voucher::query()
            ->where('code', $code)
            ->firstOrFail();

        $claimExperience = ResolveClaimExperience::run($voucher)->toArray();
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

        $redirect = [
            'show_countdown' => data_get($claimExperience, 'options.show_redirect_countdown', false),
            'owner' => data_get($claimExperience, 'diagnostics.redirect_owner'),
            'delay_seconds' => collect(data_get($claimExperience, 'phases', []))
                ->firstWhere('key', 'redirect')['delay_seconds'] ?? null,
        ];

        $props = [
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
            'claim_experience' => $claimExperience,
            'redirect' => $redirect,
        ];

        if (request()->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('x-change/claim/Success', $props);
    }
}
