<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Claim\DefaultClaimWorkflowResolver;
use LBHurtado\XChange\Services\Claim\OnboardingSuccessActionResolver;
use LBHurtado\XChange\Services\Claim\VoucherRiderFallbackPolicy;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use LBHurtado\XChange\Services\XRay\VoucherXRayProjectionBuilder;
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
        VoucherXRayProjectionBuilder $xray,
        OnboardingSuccessActionResolver $onboardingActions,
        DefaultClaimWorkflowResolver $workflowResolver,
        VoucherFlowCapabilityResolverContract $capabilities,
        VoucherCollectionProgressService $collectionProgress,
    ): Response|JsonResponse {
        $voucher = Voucher::query()
            ->where('code', $code)
            ->firstOrFail();

        $claimExperience = ResolveClaimExperience::run($voucher)->toArray();
        $subject = $subjects->fromVoucher($voucher);
        $state = $outcomes->forVoucher($voucher);
        $instructions = $voucher->instructions?->toArray() ?? [];
        $successPresentation = $this->successPresentation($voucher, $xray);

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
            'claimWorkflowKey' => $workflowResolver->resolve($voucher)->key,
            'rider' => $experience?->toArray(),
            'redirectEndpoint' => route('x-change.claim.redirect', [
                'code' => $voucher->code,
            ]),
            'claim_experience' => $claimExperience,
            'redirect' => ClaimExperiencePayload::redirect($claimExperience),
            'compiled_claim_result' => app(CompiledClaimSuccessPayload::class)->pull(),
            'destination' => $this->destinationSnapshot($voucher),
            'success_presentation' => $successPresentation,
            'success_action' => $this->successAction(
                voucher: $voucher,
                successPresentation: $successPresentation,
                onboardingActions: $onboardingActions,
                capabilities: $capabilities,
                collectionProgress: $collectionProgress,
            ),
        ];

        if (request()->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('x-change/claim/Success', $props);
    }

    /**
     * @param  array<string, mixed>|null  $successPresentation
     * @return array<string, mixed>|null
     */
    private function successAction(
        Voucher $voucher,
        ?array $successPresentation,
        OnboardingSuccessActionResolver $onboardingActions,
        VoucherFlowCapabilityResolverContract $capabilities,
        VoucherCollectionProgressService $collectionProgress,
    ): ?array {
        if ($successPresentation !== null) {
            $onboardingAction = $onboardingActions->resolve($successPresentation);

            if ($onboardingAction !== null) {
                return $onboardingAction;
            }
        }

        if (! $capabilities->resolve($voucher)->can_collect) {
            return null;
        }

        if ($collectionProgress->compute($voucher)->is_fully_collected) {
            return null;
        }

        return [
            'key' => 'x-change.claim-success.continue-to-payment',
            'label' => 'Continue to payment',
            'intent' => 'pay_code_payment',
            'description' => 'Open the payment page for this Pay Code.',
            'enabled' => true,
            'target' => [
                'type' => 'url',
                'url' => route('x-change.pay.show', ['code' => $voucher->code]),
                'method' => 'GET',
                'redirectable' => true,
                'external' => false,
            ],
            'source' => 'x-ray',
        ];
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

    /**
     * @return array<string, mixed>|null
     */
    private function successPresentation(Voucher $voucher, VoucherXRayProjectionBuilder $xray): ?array
    {
        $projection = $xray->build($voucher);
        $success = data_get($projection, 'presentation.success');
        $intent = data_get($projection, 'presentation.intent');

        if (! is_array($success)) {
            $instructions = $voucher->instructions?->toArray();

            if (is_array($instructions) && $instructions !== []) {
                $projection = $xray->build($this->voucherSnapshot($voucher, $instructions), $voucher);
                $success = data_get($projection, 'presentation.success');
                $intent = data_get($projection, 'presentation.intent');
            }
        }

        if (! is_array($success)) {
            $metadata = $voucher->getAttribute('metadata');
            $instructions = data_get($metadata, 'instructions');

            if (is_array($instructions)) {
                $projection = $xray->build($this->voucherSnapshot($voucher, $instructions), $voucher);

                $success = data_get($projection, 'presentation.success');
                $intent = data_get($projection, 'presentation.intent');
            }
        }

        if (! is_array($success)) {
            return null;
        }

        $appName = trim((string) config('app.name', 'X-Change')) ?: 'X-Change';
        $titleTemplate = is_string($success['title_template'] ?? null)
            ? $success['title_template']
            : 'Welcome to {app_name}';

        return array_filter([
            ...$success,
            'intent' => is_string($intent) && $intent !== '' ? $intent : null,
            'app_name' => $appName,
            'title' => str_replace('{app_name}', $appName, $titleTemplate),
            'source' => 'x-ray',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $instructions
     */
    private function voucherSnapshot(Voucher $voucher, array $instructions): object
    {
        return (object) [
            'code' => (string) $voucher->code,
            'amount' => data_get($instructions, 'cash.amount', data_get($voucher, 'cash.amount')),
            'currency' => data_get($instructions, 'cash.currency', data_get($voucher, 'cash.currency', 'PHP')),
            'status' => data_get($voucher, 'status', 'issued'),
            'claimed' => (bool) data_get($voucher, 'claimed', false),
            'fully_claimed' => (bool) data_get($voucher, 'fully_claimed', false),
            'instructions' => $instructions,
            'metadata' => $voucher->getAttribute('metadata'),
        ];
    }
}
