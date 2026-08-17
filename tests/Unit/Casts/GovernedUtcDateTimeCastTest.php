<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\CommercialComponentEconomics;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialProviderCostBatch;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;
use LBHurtado\XChange\Models\CommercialTaxProfile;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Models\TreasuryReconciliationRun;

it('normalizes authoritative model instants before Eloquent serializes them', function (Model $model, string $attribute): void {
    $model->setAttribute($attribute, '2026-08-17T21:00:00.123456+08:00');

    expect($model->getAttributes()[$attribute])
        ->toBe('2026-08-17 13:00:00.123456')
        ->and($model->getAttribute($attribute)->timezoneName)->toBe('UTC');
})->with([
    'Commercial Offering' => [new CommercialOffering, 'effective_at'],
    'Component Economics' => [new CommercialComponentEconomics, 'effective_at'],
    'Recipient Designation' => [new CommercialRecipientDesignation, 'effective_from'],
    'Tax Profile' => [new CommercialTaxProfile, 'effective_from'],
    'Provider Cost' => [new CommercialProviderCostBatch, 'observed_at'],
    'Partner Commission' => [new PartnerCommissionPayoutBatch, 'period_started_at'],
    'Treasury Reconciliation' => [new TreasuryReconciliationRun, 'observed_at'],
    'Account Funding Receipt' => [new AccountFundingReceipt, 'observed_at'],
    'Provider Cost Settlement' => [new CommercialProviderCostSettlement, 'observed_at'],
]);

it('preserves nullable authoritative model instants', function (): void {
    $offering = new CommercialOffering;
    $offering->setAttribute('effective_at', null);

    expect($offering->getAttribute('effective_at'))->toBeNull();
});
