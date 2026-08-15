<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Treasury\ExecuteTreasuryReconciliationRun;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ExecuteTreasuryReconciliationRunRequest;
use LBHurtado\XChange\Models\TreasuryReconciliationRun;

final class CockpitTreasuryReconciliationRunExecutionController extends Controller
{
    public function store(
        ExecuteTreasuryReconciliationRunRequest $request,
        TreasuryReconciliationRun $treasuryReconciliationRun,
        ExecuteTreasuryReconciliationRun $execute,
    ): RedirectResponse {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $execute->handle($treasuryReconciliationRun, $operator);

        return back();
    }
}
