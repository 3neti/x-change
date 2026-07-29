<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetAuthorizationExecutionService;

it('separates direct-bank beneficiaries from Pay Code fallbacks while NetBank dispatch is disabled', function () {
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
        ->assertSessionHas('campaign_notice', 'NetBank dispatch: 0 dispatched, 1 blocked, 0 failed.');

    $authorization->refresh();

    expect($authorization->fulfillments()->where('status', 'provider_dispatch_blocked')->count())->toBe(1)
        ->and($authorization->fulfillments()->where('status', 'fallback_planned')->count())->toBe(1)
        ->and($authorization->fulfillments()->whereNotNull('provider_transfer_reference')->count())->toBe(0)
        ->and($authorization->fulfillments()->whereNotNull('pay_code')->count())->toBe(0);

    $this->post(route('x-change.cockpit.campaigns.fulfillments.bank-transfers.store', $worksheet->reference))
        ->assertRedirect(route('x-change.cockpit.campaigns.show', $worksheet->reference))
        ->assertSessionHas('campaign_notice', 'NetBank dispatch: 0 dispatched, 0 blocked, 0 failed.');

    expect($authorization->fulfillments()->where('status', 'provider_dispatch_blocked')->count())->toBe(1)
        ->and($authorization->fulfillments()->where('status', 'fallback_planned')->count())->toBe(1);
});
