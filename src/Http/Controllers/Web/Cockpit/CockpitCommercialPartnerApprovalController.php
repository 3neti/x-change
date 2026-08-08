<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialPartner;
use LBHurtado\XChange\Http\Requests\Cockpit\ApproveCommercialPartnerRequest;
use LBHurtado\XChange\Models\CommercialPartnerRevision;

final class CockpitCommercialPartnerApprovalController extends Controller
{
    public function __construct(private readonly ManageCommercialPartner $partners) {}

    public function store(
        ApproveCommercialPartnerRequest $request,
        CommercialPartnerRevision $partnerRevision,
    ): RedirectResponse {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $this->partners->approve($operator, $partnerRevision);

        return back()->with('success', 'Commercial Partner approved and activated.');
    }
}
