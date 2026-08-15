<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Treasury\ApproveTreasuryInstitutionFundClassification;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ApproveTreasuryInstitutionFundClassificationRequest;
use LBHurtado\XChange\Models\TreasuryInstitutionFundClassification;

final class CockpitTreasuryInstitutionFundClassificationApprovalController extends Controller
{
    public function store(
        ApproveTreasuryInstitutionFundClassificationRequest $request,
        TreasuryInstitutionFundClassification $classification,
        ApproveTreasuryInstitutionFundClassification $approve,
    ): RedirectResponse {
        $checker = $request->user();
        abort_unless($checker instanceof Model, 403);
        $approve->handle($classification, $checker);

        return back();
    }
}
