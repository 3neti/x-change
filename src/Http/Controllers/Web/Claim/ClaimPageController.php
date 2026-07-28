<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Routing\Controller;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
use LBHurtado\XChange\Actions\Claim\ValidateCompiledClaimVoucher;
use LBHurtado\XChange\Contracts\ClaimShareMetadataResolverContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Http\Responses\ClaimEntryResponseFactory;

class ClaimPageController extends Controller
{
    public function __invoke(
        string $code,
        ValidateCompiledClaimVoucher $validator,
        VoucherFlowCapabilityResolverContract $capabilities,
        ClaimShareMetadataResolverContract $shareMetadata,
        ClaimEntryResponseFactory $responses,
    ): Response {
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
            ),
        );
    }
}
