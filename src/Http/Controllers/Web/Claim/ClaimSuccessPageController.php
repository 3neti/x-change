<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Claim\VoucherRiderFallbackPolicy;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;
use LBHurtado\XChange\Support\Claim\CompiledClaimSuccessPayload;
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
        VoucherRiderFallbackPolicy $riderFallbacks,
    ): Response|JsonResponse {
        $voucher = Voucher::query()
            ->where('code', $code)
            ->firstOrFail();

        $claimExperience = ResolveClaimExperience::run($voucher)->toArray();
        $subject = $subjects->fromVoucher($voucher);
        $state = $outcomes->forVoucher($voucher);
        $instructions = $voucher->instructions?->toArray() ?? [];

        $experience = $riderFallbacks->shouldResolve($instructions)
            ? $riders->resolve($subject, [
                'state' => $state->value,
                'rider' => data_get($instructions, 'rider', []),
                'meta' => [
                    'source' => 'x-change',
                    'route' => 'claim.success',
                ],
            ])
            : null;

        $props = [
            'voucher' => [
                'code' => (string) $voucher->code,
                'amount' => data_get($voucher, 'cash.amount'),
                'currency' => data_get($voucher, 'cash.currency'),
            ],
            'claimOutcome' => $state->value,
            'rider' => $experience?->toArray(),
            'redirectEndpoint' => route('x-change.claim.redirect', [
                'code' => $voucher->code,
            ]),
            'claim_experience' => $claimExperience,
            'redirect' => ClaimExperiencePayload::redirect($claimExperience),
            'compiled_claim_result' => app(CompiledClaimSuccessPayload::class)->pull(),
            'destination' => $this->destinationSnapshot($voucher),
        ];

        if (request()->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('x-change/claim/Success', $props);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function destinationSnapshot(Voucher $voucher): ?array
    {
        $latestClaim = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->first();

        $destination = is_array($latestClaim?->meta) ? data_get($latestClaim->meta, 'destination') : null;

        return is_array($destination) && $destination !== [] ? $destination : null;
    }
}
