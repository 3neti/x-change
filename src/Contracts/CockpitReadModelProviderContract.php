<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitCampaignReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeListReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelBundleData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;

interface CockpitReadModelProviderContract
{
    public function forVoucher(CockpitReadModelQueryData $query): CockpitReadModelBundleData;

    public function forPayCodeList(CockpitReadModelQueryData $query): CockpitPayCodeListReadModelData;

    public function forDashboard(CockpitReadModelQueryData $query): CockpitDashboardReadModelData;

    public function forQuickGenerate(CockpitReadModelQueryData $query): CockpitQuickGenerateReadModelData;

    public function forCampaignAdoption(CockpitReadModelQueryData $query): CockpitCampaignReadModelData;
}
