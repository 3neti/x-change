<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Merchant\Contracts\MerchantProfileRepositoryContract;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\UpdateCockpitFundingQrMerchantProfileRequest;

class CockpitFundingQrMerchantProfileController extends Controller
{
    public function __invoke(
        UpdateCockpitFundingQrMerchantProfileRequest $request,
        MerchantProfileRepositoryContract $profiles,
    ): RedirectResponse {
        $profiles->updateForUser($request->user(), $request->validated());

        return back()->with(
            'funding_account_notice',
            'Merchant identity updated. New Pay Codes and Funding QR codes will use it.',
        );
    }
}
