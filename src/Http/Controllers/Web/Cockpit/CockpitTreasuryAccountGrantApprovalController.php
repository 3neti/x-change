<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Treasury\ApproveTreasuryAccountGrant;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ApproveTreasuryAccountGrantRequest;
use LBHurtado\XChange\Models\TreasuryAccountGrant;

final class CockpitTreasuryAccountGrantApprovalController extends Controller
{
    public function store(
        ApproveTreasuryAccountGrantRequest $request,
        TreasuryAccountGrant $treasuryAccountGrant,
        ApproveTreasuryAccountGrant $approve,
    ): RedirectResponse {
        $checker = $request->user();
        abort_unless($checker instanceof Model, 403);
        $approve->handle($treasuryAccountGrant, $checker);

        return back();
    }
}
