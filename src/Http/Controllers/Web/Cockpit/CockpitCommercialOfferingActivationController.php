<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Http\Requests\Cockpit\ActivateCommercialOfferingRequest;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Services\Commercial\ActivateCommercialOffering;

final class CockpitCommercialOfferingActivationController extends Controller
{
    public function __construct(
        private readonly ActivateCommercialOffering $activate,
    ) {}

    public function store(
        ActivateCommercialOfferingRequest $request,
        CommercialOffering $offering,
    ): RedirectResponse {
        $this->activate->execute(
            $offering,
            CommercialActivationAuthority::IndependentApproval,
            (string) $request->validated('activation_reference'),
        );

        return back()->with('success', 'Commercial Offering activated. New Pay Codes now use this version.');
    }
}
