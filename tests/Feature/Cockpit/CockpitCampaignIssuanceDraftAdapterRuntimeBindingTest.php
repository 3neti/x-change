<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitCampaignIssuanceDraftAdapterContract;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitCampaignIssuanceDraftAdapter;

it('binds the cockpit campaign issuance draft adapter contract', function () {
    $adapter = app(CockpitCampaignIssuanceDraftAdapterContract::class);

    expect($adapter)->toBeInstanceOf(DefaultCockpitCampaignIssuanceDraftAdapter::class)
        ->and($adapter->fromCampaignContext([
            'amount' => 25,
            'campaign_id' => 'campaign-001',
        ])->hasCampaignContext())->toBeTrue();
});
