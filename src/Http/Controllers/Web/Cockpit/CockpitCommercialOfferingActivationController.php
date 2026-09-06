<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Requests\Cockpit\ActivateCommercialOfferingRequest;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Services\Commercial\ActivateCommercialOfferingRevision;

final class CockpitCommercialOfferingActivationController extends Controller
{
    public function __construct(
        private readonly ActivateCommercialOfferingRevision $activate,
    ) {}

    public function store(
        ActivateCommercialOfferingRequest $request,
        CommercialOffering $offering,
    ): RedirectResponse {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);

        $this->activate->execute(
            offering: $offering,
            actor: $operator,
            activationReference: (string) $request->validated('activation_reference'),
        );

        return back()->with('success', 'Commercial Offering activated. New Pay Codes now use this version.');
    }
}
