<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
use LBHurtado\XChange\Actions\Claim\ValidateCompiledClaimVoucher;
use LBHurtado\XChange\Contracts\Claim\ClaimSurfaceResolverContract;
use LBHurtado\XChange\Contracts\ClaimShareCardUrlResolverContract;
use LBHurtado\XChange\Contracts\ClaimShareMetadataResolverContract;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceData;
use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use LBHurtado\XChange\Http\Responses\ClaimEntryResponseFactory;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use LBHurtado\XChange\Support\Claim\ClaimAuthenticationIntent;

class ClaimPageController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        ValidateCompiledClaimVoucher $validator,
        VoucherFlowCapabilityResolverContract $capabilities,
        VoucherCollectionProgressService $collectionProgress,
        ClaimWorkflowResolverContract $workflows,
        ClaimAuthenticationIntent $loginIntent,
        ClaimShareMetadataResolverContract $shareMetadata,
        ClaimShareCardUrlResolverContract $shareCardUrls,
        ClaimEntryResponseFactory $responses,
        ClaimSurfaceResolverContract $claimSurfaces,
    ): Response|RedirectResponse {
        $code = strtoupper(trim($code));
        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher instanceof Voucher) {
            return $responses->error(
                message: 'Invalid Pay Code.',
                code: $code,
            );
        }

        $flowCapabilities = $capabilities->resolve($voucher);

        if (! $flowCapabilities->can_disburse) {
            if ($flowCapabilities->can_collect) {
                return $responses->paymentHandoff(
                    code: $code,
                    paymentUrl: route('x-change.pay.show', ['code' => $code]),
                    isFullyCollected: $collectionProgress->compute($voucher)->is_fully_collected,
                );
            }

            return $responses->error(
                message: 'This Pay Code accepts payment and cannot be claimed.',
                code: $code,
            );
        }

        $workflow = $workflows->resolve($voucher);

        if (
            $this->requiresAuthentication($workflow->key, $workflow->authentication_mode)
            && $request->user() === null
        ) {
            $loginIntent->remember($request, $code, $workflow);

            return redirect()->route('x-change.claim.authorization-required', ['code' => $code]);
        }

        if ($workflow->authentication_mode === ClaimAuthenticationMode::AuthenticatedOfficer) {
            $authenticatedMobile = $request->user()?->getAttribute('mobile');

            if (! is_string($authenticatedMobile) || trim($authenticatedMobile) === '') {
                return $responses->error(
                    message: 'Your authenticated officer profile needs a verified mobile number before it can authorize a campaign.',
                    code: $code,
                );
            }
        }

        if (
            $workflow->key === 'stored-value.activation.v1'
            && $request->user()?->getAttribute('mobile_verified_at') === null
        ) {
            return $responses->error(
                message: 'Your Account needs a verified mobile number before it can activate a reusable balance.',
                code: $code,
            );
        }

        // Viewer-aware claim surface: this is the only place that decides
        // what this specific visitor is allowed to see. An issuer opening
        // their own already-claimed Pay Code always gets the issuer
        // console, bypassing the redeemer-oriented voucher validator
        // entirely (a claimed/terminal voucher is exactly what they came to
        // review). Every other viewer still goes through the existing
        // validator; the calm outcome panel (instead of the old hard error
        // page) is only used once we've confirmed via the same operational
        // status resolver that the voucher's state is genuinely terminal --
        // any other validator rejection keeps the pre-existing error page.
        $surface = $claimSurfaces->resolve($voucher, $request->user());

        if ($surface->visibility === 'issuer_console') {
            return $this->renderEntry($responses, $shareMetadata, $shareCardUrls, $voucher, $code, $surface);
        }

        $message = $validator->handle($voucher);

        if ($message !== null) {
            if ($surface->state->terminal) {
                return $this->renderEntry($responses, $shareMetadata, $shareCardUrls, $voucher, $code, $surface);
            }

            return $responses->error(
                message: $message,
                code: $code,
            );
        }

        return $this->renderEntry($responses, $shareMetadata, $shareCardUrls, $voucher, $code, $surface);
    }

    private function requiresAuthentication(
        string $workflowKey,
        ClaimAuthenticationMode $authenticationMode,
    ): bool {
        return $authenticationMode === ClaimAuthenticationMode::AuthenticatedOfficer
            || in_array($workflowKey, ['account-funding.v1', 'stored-value.activation.v1'], true);
    }

    private function renderEntry(
        ClaimEntryResponseFactory $responses,
        ClaimShareMetadataResolverContract $shareMetadata,
        ClaimShareCardUrlResolverContract $shareCardUrls,
        Voucher $voucher,
        string $code,
        ClaimSurfaceData $surface,
    ): Response {
        return $responses->render(
            initialCode: $code,
            claimExperience: ResolveClaimExperience::run($voucher)->toArray(),
            provisioningRequirement: null,
            shareMetadata: $shareMetadata->resolve(
                $voucher,
                route('x-change.claim.show', ['code' => $voucher->code]),
                $shareCardUrls->resolve($voucher),
            ),
            claimSurface: $surface,
        );
    }
}
