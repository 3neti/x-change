<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeCapabilityData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeInstructionBadgeData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeListRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodePartyData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeRowActionData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeTerminalControlData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeTimingData;

it('carries read-only pay code explorer row action contract fields', function () {
    $record = new CockpitPayCodeListRecordData(
        code: 'PC-ROW-001',
        template: 'Money Changer',
        capability: new CockpitPayCodeCapabilityData(
            key: 'disburseable',
            label: 'Disburseable',
            voucher_type_label: 'Redeemable',
        ),
        instruction_badges: [
            new CockpitPayCodeInstructionBadgeData(
                key: 'mobile',
                label: 'Mobile',
            ),
        ],
        amount: 'PHP 25.00',
        amount_presentation: [
            'flow_type' => 'disbursable',
            'label' => 'Disbursable',
            'amount_minor' => 2500,
            'target_amount_minor' => null,
            'amount' => 'PHP 25.00',
            'target_amount' => null,
        ],
        currency: 'PHP',
        status: 'active',
        display_status: 'active',
        purpose: 'Family allowance',
        party: new CockpitPayCodePartyData(
            state: 'targeted',
            label: 'Recipient',
            primary: 'Mobile ending 1987',
            masked: true,
        ),
        timing: new CockpitPayCodeTimingData(
            created_at: '2026-07-11T20:00:00+08:00',
        ),
        terminal_control: new CockpitPayCodeTerminalControlData,
        owner: 'Treasury',
        last_activity: '2026-07-11T20:00:00+08:00',
        actions: [
            new CockpitPayCodeRowActionData(
                key: 'detail',
                label: 'View details',
                enabled: true,
                read_only: true,
                href: '/x/cockpit/pay-codes/PC-ROW-001',
                reason: 'Read-only Cockpit voucher detail route.',
            ),
            new CockpitPayCodeRowActionData(
                key: 'notify',
                label: 'Notify recipient',
                enabled: false,
                read_only: true,
                href: null,
                reason: 'Feedback delivery remains separately gated.',
            ),
        ],
    );

    expect($record->toArray())->toMatchArray([
        'code' => 'PC-ROW-001',
        'actions' => [
            [
                'key' => 'detail',
                'label' => 'View details',
                'enabled' => true,
                'read_only' => true,
                'href' => '/x/cockpit/pay-codes/PC-ROW-001',
                'reason' => 'Read-only Cockpit voucher detail route.',
            ],
            [
                'key' => 'notify',
                'label' => 'Notify recipient',
                'enabled' => false,
                'read_only' => true,
                'href' => null,
                'reason' => 'Feedback delivery remains separately gated.',
            ],
        ],
    ]);
});
