<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Settlement\AuiPersonalAccidentPolicyCompletionDriver;
use LBHurtado\XChange\Services\Settlement\CampaignDemonstrationProductPresentation;
use LBHurtado\XChange\Services\Settlement\MedicardDemoBenefitPolicyCompletionDriver;

it('keeps AUI copy stable and isolates Medicard demonstration language', function (): void {
    $presentations = new CampaignDemonstrationProductPresentation;
    $aui = $presentations->forDriver(
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
    );
    $medicard = $presentations->forDriver(
        MedicardDemoBenefitPolicyCompletionDriver::DRIVER_ID,
        MedicardDemoBenefitPolicyCompletionDriver::DRIVER_VERSION,
    );

    expect($aui['result_code'])->toBe('policy_issued_demo')
        ->and($aui['first_sms_body'])->toContain('AUI demonstration only')
        ->and($medicard['result_code'])->toBe('benefit_ready_demo')
        ->and($medicard['title'])->toBe('Demo benefit summary')
        ->and($medicard['first_sms_body'])->toContain('PHP 50.00', 'DEMONSTRATION ONLY')
        ->and($medicard['summary_sms_body'])->toContain('not membership', 'treatment authorization')
        ->and($presentations->forDriver('unsupported', '1.0.0'))->toBeNull();
});
