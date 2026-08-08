<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialPartnerDestination;
use LBHurtado\XChange\Http\Requests\Cockpit\ApproveCommercialPartnerRequest;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;

final class CockpitCommercialPartnerDestinationApprovalController extends Controller
{
    public function __construct(private readonly ManageCommercialPartnerDestination $destinations) {}

    public function store(
        ApproveCommercialPartnerRequest $request,
        CommercialPartnerDestinationRevision $destinationRevision,
    ): RedirectResponse {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $this->destinations->approve($operator, $destinationRevision);

        return back()->with('success', 'Partner destination approved.');
    }
}
