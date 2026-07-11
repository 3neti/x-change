<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;

interface CockpitCampaignIssuanceDraftAdapterContract
{
    /**
     * @param  array<string, mixed>  $campaignContext
     */
    public function fromCampaignContext(array $campaignContext): CockpitIssuanceDraftData;
}
