<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\ReconcilePartnerCommissionPayoutBatch;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;

final class CockpitPartnerCommissionPayoutReconciliationController extends Controller
{
    public function __construct(
        private readonly CommercialOperatorAuthorityContract $authority,
        private readonly ReconcilePartnerCommissionPayoutBatch $batches,
    ) {}

    public function store(Request $request, PartnerCommissionPayoutBatch $commissionPayoutBatch): RedirectResponse
    {
        $operator = $request->user();
        abort_unless(
            (bool) config('x-change.commercial.operations.live_provider_calls_enabled', false)
            && $operator instanceof Model
            && $this->authority->allows($operator, CommercialOperatorCapability::ExecuteCommissionPayouts),
            403,
        );
        $this->batches->execute($operator, $commissionPayoutBatch);

        return back()->with('success', 'Provider status checked and the payout state reconciled.');
    }
}
