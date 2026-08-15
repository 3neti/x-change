<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Treasury\RequestTreasuryReconciliationRun;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StoreTreasuryReconciliationRunRequest;

final class CockpitTreasuryReconciliationRunController extends Controller
{
    public function store(
        StoreTreasuryReconciliationRunRequest $request,
        RequestTreasuryReconciliationRun $requestRun,
    ): RedirectResponse {
        $input = $request->validated();
        $maker = $request->user();
        abort_unless($maker instanceof Model, 403);
        $requestRun->handle(
            connectionReference: (string) $input['connection_reference'],
            purpose: (string) $input['purpose'],
            idempotencyReference: (string) $input['idempotency_reference'],
            maker: $maker,
        );

        return back();
    }
}
