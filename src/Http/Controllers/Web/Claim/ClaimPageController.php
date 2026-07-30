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
use LBHurtado\XChange\Contracts\ClaimShareCardUrlResolverContract;
use LBHurtado\XChange\Contracts\ClaimShareMetadataResolverContract;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use LBHurtado\XChange\Http\Responses\ClaimEntryResponseFactory;
use LBHurtado\XChange\Support\Claim\ClaimAuthenticationIntent;

class ClaimPageController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        ValidateCompiledClaimVoucher $validator,
        VoucherFlowCapabilityResolverContract $capabilities,
        ClaimWorkflowResolverContract $workflows,
        ClaimAuthenticationIntent $loginIntent,
        ClaimShareMetadataResolverContract $shareMetadata,
        ClaimShareCardUrlResolverContract $shareCardUrls,
        ClaimEntryResponseFactory $responses,
    ): Response|RedirectResponse {
        $code = strtoupper(trim($code));
        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher instanceof Voucher) {
            return $responses->error(
                message: 'Invalid Pay Code.',
                code: $code,
            );
        }

        if (! $capabilities->resolve($voucher)->can_disburse) {
            return $responses->error(
                message: 'This Pay Code accepts payment and cannot be claimed.',
                code: $code,
            );
        }

        $workflow = $workflows->resolve($voucher);

        if (
            $workflow->authentication_mode === ClaimAuthenticationMode::AuthenticatedOfficer
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

        $message = $validator->handle($voucher);

        if ($message !== null) {
            return $responses->error(
                message: $message,
                code: $code,
            );
        }

        return $responses->render(
            initialCode: $code,
            claimExperience: ResolveClaimExperience::run($voucher)->toArray(),
            provisioningRequirement: null,
            shareMetadata: $shareMetadata->resolve(
                $voucher,
                route('x-change.claim.show', ['code' => $voucher->code]),
                $shareCardUrls->resolve($voucher),
            ),
        );
    }
}
