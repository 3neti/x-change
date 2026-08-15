<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Treasury\ExecuteTreasuryInstitutionFundClassification;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ExecuteTreasuryInstitutionFundClassificationRequest;
use LBHurtado\XChange\Models\TreasuryInstitutionFundClassification;

final class CockpitTreasuryInstitutionFundClassificationExecutionController extends Controller
{
    public function store(
        ExecuteTreasuryInstitutionFundClassificationRequest $request,
        TreasuryInstitutionFundClassification $treasuryInstitutionFundClassification,
        ExecuteTreasuryInstitutionFundClassification $execute,
    ): RedirectResponse {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $execute->handle($treasuryInstitutionFundClassification, $operator);

        return back();
    }
}
