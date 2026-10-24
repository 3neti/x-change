<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Configuration\ApplicationJourneyData;
use LBHurtado\XChange\Data\Configuration\ApplicationProfileData;
use Symfony\Component\Yaml\Yaml;

it('keeps the salary payout definition aligned with the payout app profile', function (): void {
    $packageRoot = dirname(__DIR__, 4);
    $definition = Yaml::parseFile($packageRoot.'/config/app-journeys/salary-payout.yaml');
    $profile = ApplicationProfileData::fromArray(
        Yaml::parseFile($packageRoot.'/config/app-profiles/payout.yaml'),
    );
    $journey = ApplicationJourneyData::fromArray(array_intersect_key($definition, array_flip([
        'key', 'label', 'kind', 'instruction_contract', 'approval',
    ])));
    $declared = collect($profile->journeys)->first(
        static fn (ApplicationJourneyData $candidate): bool => $candidate->key === $journey->key,
    );

    expect($definition['schema'])->toBe('x-change.app-journey.v1')
        ->and($declared)->toEqual($journey)
        ->and($definition['workflow'])->toBe('campaign_worksheet')
        ->and($definition['intake'])->toBe('payroll_rows')
        ->and($definition['approval'])->toBe('direct')
        ->and($definition['instruction_compiler'])->toBe('campaign_voucher')
        ->and($definition['fulfillment'])->toBe([
            'pay_code_distribution',
            'direct_bank_transfer_with_pay_code_recovery',
        ])
        ->and($definition['result'])->toBe('batch_status_and_reconciliation');
});
