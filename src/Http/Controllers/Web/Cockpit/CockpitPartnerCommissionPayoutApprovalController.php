<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\ApprovePartnerCommissionPayoutBatch;
use LBHurtado\XChange\Http\Requests\Cockpit\ApprovePartnerCommissionPayoutBatchRequest;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;

final class CockpitPartnerCommissionPayoutApprovalController extends Controller
{
    public function __construct(private readonly ApprovePartnerCommissionPayoutBatch $batches) {}

    public function store(
        ApprovePartnerCommissionPayoutBatchRequest $request,
        PartnerCommissionPayoutBatch $commissionPayoutBatch,
    ): RedirectResponse {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $this->batches->execute(
            $operator,
            $commissionPayoutBatch,
            (string) $request->validated('authorization_reference'),
        );

        return back()->with('success', 'Partner commission payout independently approved.');
    }
}
