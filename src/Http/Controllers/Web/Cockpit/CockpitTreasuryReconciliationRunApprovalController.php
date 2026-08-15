<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Treasury\ApproveTreasuryReconciliationRun;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ApproveTreasuryReconciliationRunRequest;
use LBHurtado\XChange\Models\TreasuryReconciliationRun;

final class CockpitTreasuryReconciliationRunApprovalController extends Controller
{
    public function store(
        ApproveTreasuryReconciliationRunRequest $request,
        TreasuryReconciliationRun $treasuryReconciliationRun,
        ApproveTreasuryReconciliationRun $approve,
    ): RedirectResponse {
        $checker = $request->user();
        abort_unless($checker instanceof Model, 403);
        $approve->handle($treasuryReconciliationRun, $checker);

        return back();
    }
}
