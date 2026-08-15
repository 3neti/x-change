<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Treasury\ExecuteTreasuryAccountGrant;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ExecuteTreasuryAccountGrantRequest;
use LBHurtado\XChange\Models\TreasuryAccountGrant;

final class CockpitTreasuryAccountGrantExecutionController extends Controller
{
    public function store(
        ExecuteTreasuryAccountGrantRequest $request,
        TreasuryAccountGrant $treasuryAccountGrant,
        ExecuteTreasuryAccountGrant $execute,
    ): RedirectResponse {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $execute->handle($treasuryAccountGrant, $operator);

        return back();
    }
}
