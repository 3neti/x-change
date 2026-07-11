<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeListRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeRowActionData;

it('carries read-only pay code explorer row action contract fields', function () {
    $record = new CockpitPayCodeListRecordData(
        code: 'PC-ROW-001',
        template: 'Money Changer',
        amount: 'PHP 25.00',
        currency: 'PHP',
        status: 'active',
        display_status: 'active',
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
