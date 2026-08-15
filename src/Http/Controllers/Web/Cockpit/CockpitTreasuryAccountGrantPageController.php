<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Services\Cockpit\TreasuryAccountGrantReadModel;
use LBHurtado\XChange\Services\Cockpit\TreasuryInstitutionFundClassificationReadModel;
use LBHurtado\XChange\Services\Cockpit\TreasuryReconciliationRunReadModel;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final class CockpitTreasuryAccountGrantPageController extends Controller
{
    public function __construct(
        private readonly TreasuryOperatorAuthority $authority,
        private readonly TreasuryAccountGrantReadModel $readModel,
        private readonly TreasuryInstitutionFundClassificationReadModel $institutionFunds,
        private readonly TreasuryReconciliationRunReadModel $reconciliation,
    ) {}

    public function __invoke(Request $request): Response
    {
        $operator = $request->user();
        abort_unless($operator instanceof Model
            && ($this->authority->allows($operator, TreasuryOperatorCapability::ViewAccountGrants)
                || $this->authority->allows($operator, TreasuryOperatorCapability::ViewInstitutionFunds)
                || $this->authority->allows($operator, TreasuryOperatorCapability::ViewReconciliation)), 404);

        return Inertia::render('x-change/cockpit/TreasuryOperations', [
            'treasury_account_grants' => $this->readModel->build($operator),
            'treasury_institution_funds' => $this->institutionFunds->build($operator),
            'treasury_reconciliation' => $this->reconciliation->build($operator),
            'treasury_account_grant_store_url' => route('x-change.cockpit.treasury.account-grants.store'),
            'treasury_institution_fund_store_url' => route(
                'x-change.cockpit.treasury.institution-funds.store',
            ),
            'treasury_reconciliation_store_url' => route(
                'x-change.cockpit.treasury.reconciliation.store',
            ),
        ]);
    }
}
