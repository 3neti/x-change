<?php

declare(strict_types=1);

use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XCampaign\Models\CampaignWorksheetRow;
use LBHurtado\XChange\Services\Campaigns\NetbankCampaignBankTransferDispatcher;
use LBHurtado\XChange\Tests\Fakes\FakePayoutProvider;

it('keeps NetBank campaign dispatch disabled until its explicit runtime gate is enabled', function () {
    config(['x-change.campaigns.netbank_dispatch.enabled' => false]);
    $payouts = new FakePayoutProvider;

    $result = (new NetbankCampaignBankTransferDispatcher($payouts))->dispatch(campaignBankTransferFulfillment(12_500));

    expect($result->status)->toBe('blocked')
        ->and($result->reason)->toBe('NetBank campaign dispatch is disabled.');
    $payouts->assertNoDisbursementAttempted();
});

it('selects InstaPay below fifty thousand pesos and PesoNet at the conservative threshold', function () {
    config(['x-change.campaigns.netbank_dispatch.enabled' => true]);
    $payouts = new FakePayoutProvider;
    $dispatcher = new NetbankCampaignBankTransferDispatcher($payouts);

    $dispatcher->dispatch(campaignBankTransferFulfillment(4_999_999, 'campaign-instapay'));
    $dispatcher->dispatch(campaignBankTransferFulfillment(5_000_000, 'campaign-pesonet'));

    expect($payouts->requests)->toHaveCount(2)
        ->and($payouts->requests[0]->settlement_rail)->toBe('INSTAPAY')
        ->and($payouts->requests[1]->settlement_rail)->toBe('PESONET')
        ->and($payouts->requests[0]->reference)->toBe('campaign:campaign-instapay')
        ->and($payouts->requests[1]->reference)->toBe('campaign:campaign-pesonet');
});

/**
 * @param  positive-int  $amountMinor
 */
function campaignBankTransferFulfillment(int $amountMinor, string $reference = 'campaign-transfer'): CampaignWorksheetFulfillment
{
    $row = new CampaignWorksheetRow;
    $row->forceFill([
        'amount_minor' => $amountMinor,
        'currency' => 'PHP',
        'beneficiary_ciphertext' => [
            'mobile' => '09173011987',
            'bank_account' => '113001000019',
            'bank_code' => 'NBKPHMM',
        ],
    ]);

    $fulfillment = new CampaignWorksheetFulfillment;
    $fulfillment->forceFill(['reference' => $reference]);
    $fulfillment->setRelation('row', $row);

    return $fulfillment;
}
