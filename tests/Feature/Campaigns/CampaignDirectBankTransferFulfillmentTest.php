<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetAuthorizationExecutionService;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

function fundDirectTransferCampaignOwner(mixed $owner, int $amountMinor): void
{
    enableNetbankTreasuryForTests();
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'campaign-browser-runner:inventory:register',
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventory->recognize(new TreasuryInventoryRecognitionData(
        operationReference: 'campaign-browser-runner:inventory:recognize:'.str()->uuid(),
        inventoryReference: 'inventory:netbank:vca-cash',
        settlementResourceReference: 'resource:netbank:corporate-vca',
        amountMinor: $amountMinor,
        currency: 'PHP',
        status: 'requested',
        idempotencyKey: 'campaign-browser-runner:inventory:recognize-key:'.str()->uuid(),
        externalReference: 'campaign-browser-runner:funding:'.str()->uuid(),
    ));
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision(
        $owner,
        ['netbank-primary'],
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$owner->wallet->uuid,
        provider: 'netbank',
        amountMinor: $amountMinor,
        currency: 'PHP',
        evidenceReference: 'netbank:campaign-browser-runner:'.spl_object_id($owner),
    );
}

it('keeps browser direct-transfer execution behind the NetBank environment gate', function () {
    $owner = actingAsTestUser();
    $officer = actingAsTestUser();
    $officer->forceFill(['mobile' => '09173011987'])->save();
    $repository = app(CampaignWorksheetRepository::class);

    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-direct-bank-disabled-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Direct Bank Disabled Test',
        fulfillmentMode: 'direct_bank_transfer',
        rows: [
            new CampaignWorksheetRowData(null, 1, ['mobile' => '09178889999', 'bank_account' => '113001000019', 'bank_code' => 'NBKPHMM'], 12_500),
            new CampaignWorksheetRowData(null, 2, ['mobile' => '09179998888'], 7_500),
        ],
    ));
    $repository->freeze((string) $worksheet->reference, $owner->getMorphClass(), (string) $owner->getKey());

    $this->actingAs($owner);
    $authorization = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $owner);

    $this->actingAs($officer);
    app(CampaignWorksheetAuthorizationExecutionService::class)->execute(
        Voucher::query()->where('code', $authorization->approval_pay_code)->sole(),
        ['mobile' => '09173011987'],
    );

    $this->actingAs($owner)
        ->post(route('x-change.cockpit.campaigns.fulfillments.bank-transfers.store', $worksheet->reference))
        ->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHas('campaign_notice', 'NetBank live transfer dispatch is not enabled for this environment.');

    $authorization->refresh();

    expect($authorization->fulfillments()->where('status', 'planned')->count())->toBe(2)
        ->and($authorization->fulfillments()->whereNotNull('provider_transfer_reference')->count())->toBe(0)
        ->and($authorization->fulfillments()->whereNotNull('pay_code')->count())->toBe(0);
});

it('runs an approved direct-transfer worksheet from Cockpit through the voucher execution engine', function (): void {
    config()->set('x-change.provider_runtime.lifecycle.allow_live_provider_scenarios', true);
    config()->set('x-change.campaigns.netbank_dispatch.enabled', true);
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'TXN-COCKPIT-BROWSER-RUNNER-001',
        uuid: 'uuid-cockpit-browser-runner-001',
        provider: 'netbank',
    );
    $owner = actingAsTestUser();
    fundDirectTransferCampaignOwner($owner, 10_000);
    $officer = actingAsTestUser();
    $officer->forceFill(['mobile' => '09173011987'])->save();
    $repository = app(CampaignWorksheetRepository::class);

    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-browser-runner-live-01',
        ownerType: $owner->getMorphClass(),
        ownerId: (string) $owner->getKey(),
        profile: 'payroll',
        name: 'Browser Runner Payroll',
        fulfillmentMode: 'direct_bank_transfer',
        rows: [
            new CampaignWorksheetRowData(
                null,
                1,
                ['name' => 'Payroll Recipient', 'mobile' => '09170000011', 'bank_account' => '09170000011', 'bank_code' => 'GCash'],
                2_500,
            ),
        ],
    ));
    $repository->freeze((string) $worksheet->reference, $owner->getMorphClass(), (string) $owner->getKey());

    $this->actingAs($owner);
    $authorization = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $owner);
    $this->actingAs($officer);
    app(CampaignWorksheetAuthorizationExecutionService::class)->execute(
        Voucher::query()->where('code', $authorization->approval_pay_code)->sole(),
        ['mobile' => '09173011987'],
    );

    $this->actingAs($owner)
        ->post(route('x-change.cockpit.campaigns.fulfillments.bank-transfers.store', $worksheet->reference), [
            'confirm_live_transfer' => 'I APPROVE LIVE BANK TRANSFERS',
        ])
        ->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHas('campaign_notice', 'Live payroll runner: 1 Pay Codes issued, 1 paid, 0 require review, 0 skipped.');

    $fulfillment = $authorization->refresh()->fulfillments()->sole();
    $voucher = Voucher::query()->where('code', $fulfillment->pay_code)->sole();

    expect($fulfillment->status)->toBe('completed')
        ->and($voucher->isRedeemed())->toBeTrue()
        ->and(VoucherClaim::query()->where('voucher_id', $voucher->getKey())->sole()->status)->toBe('succeeded')
        ->and(DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())->sole()->provider_transaction_id)
        ->toBe('TXN-COCKPIT-BROWSER-RUNNER-001')
        ->and(ExecutionJournalEntry::query()->where('event_type', 'campaign.direct_transfer.executed')->exists())->toBeTrue()
        ->and(ExecutionJournalEntry::query()->where('event_type', 'campaign.pay_code.issued')->exists())->toBeTrue()
        ->and(ExecutionJournalEntry::query()->where('event_type', 'campaign.provider_payout.succeeded')->exists())->toBeTrue();
    $provider->assertDisburseCalledTimes(1);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertOk()
        ->assertJsonPath('props.fulfillments.0.monitor_label', 'Paid')
        ->assertJsonPath('props.fulfillments.0.claim_status', 'succeeded');
});
