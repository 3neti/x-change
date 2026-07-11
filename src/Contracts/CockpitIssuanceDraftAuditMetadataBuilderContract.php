<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftAuditMetadataData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;

interface CockpitIssuanceDraftAuditMetadataBuilderContract
{
    public function build(CockpitIssuanceDraftData $draft): CockpitIssuanceDraftAuditMetadataData;
}
