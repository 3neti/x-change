<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Treasury\RequestTreasuryInstitutionFundClassification;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StoreTreasuryInstitutionFundClassificationRequest;

final class CockpitTreasuryInstitutionFundClassificationController extends Controller
{
    public function store(
        StoreTreasuryInstitutionFundClassificationRequest $request,
        RequestTreasuryInstitutionFundClassification $requestClassification,
    ): RedirectResponse {
        $input = $request->validated();
        $maker = $request->user();
        abort_unless($maker instanceof Model, 403);
        $requestClassification->handle(
            evidenceOperationReference: (string) $input['evidence_operation_reference'],
            ownershipBasis: (string) $input['ownership_basis'],
            idempotencyReference: (string) $input['idempotency_reference'],
            maker: $maker,
        );

        return back();
    }
}
