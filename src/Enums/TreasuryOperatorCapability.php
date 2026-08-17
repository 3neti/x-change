<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum TreasuryOperatorCapability: string
{
    case ViewAccountGrants = 'treasury.account_grants.view';
    case RequestAccountGrants = 'treasury.account_grants.request';
    case ApproveAccountGrants = 'treasury.account_grants.approve';
    case ExecuteAccountGrants = 'treasury.account_grants.execute';
    case ViewInstitutionFunds = 'treasury.institution_funds.view';
    case RequestInstitutionFunds = 'treasury.institution_funds.request';
    case ApproveInstitutionFunds = 'treasury.institution_funds.approve';
    case ExecuteInstitutionFunds = 'treasury.institution_funds.execute';
    case ViewReconciliation = 'treasury.reconciliation.view';
    case RequestReconciliation = 'treasury.reconciliation.request';
    case ApproveReconciliation = 'treasury.reconciliation.approve';
    case ExecuteReconciliation = 'treasury.reconciliation.execute';
    case ViewFundingBindings = 'treasury.funding_bindings.view';
    case RequestFundingBindings = 'treasury.funding_bindings.request';
    case ApproveFundingBindings = 'treasury.funding_bindings.approve';
    case ExecuteFundingBindings = 'treasury.funding_bindings.execute';
}
