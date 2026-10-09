<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Settlement\CampaignAutomaticDemonstrationResponderRegistry;
use LBHurtado\XChange\Services\Settlement\CampaignCoverageDriverRegistry;
use LBHurtado\XChange\Services\Settlement\MedicardDemoBenefitCampaignCoverageDriver;
use LBHurtado\XChange\Services\Settlement\MedicardDemoBenefitPolicyCompletionDriver;
use LBHurtado\XChange\Services\Settlement\MedicardDemoBenefitResponder;

it('registers the exact Medicard coverage and automatic demonstration drivers', function (): void {
    $coverage = app(CampaignCoverageDriverRegistry::class)->for(
        MedicardDemoBenefitCampaignCoverageDriver::DRIVER_ID,
        MedicardDemoBenefitCampaignCoverageDriver::DRIVER_VERSION,
    );
    $responder = app(CampaignAutomaticDemonstrationResponderRegistry::class)->for(
        MedicardDemoBenefitPolicyCompletionDriver::DRIVER_ID,
        MedicardDemoBenefitPolicyCompletionDriver::DRIVER_VERSION,
    );

    expect($coverage)->toBeInstanceOf(MedicardDemoBenefitCampaignCoverageDriver::class)
        ->and($responder)->toBeInstanceOf(MedicardDemoBenefitResponder::class);
});
