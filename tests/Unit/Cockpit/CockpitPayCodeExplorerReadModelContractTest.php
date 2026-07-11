<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeExplorerFilterData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeExplorerStatsData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeListReadModelData;

it('carries read-only pay code explorer filter and summary contract fields', function () {
    $readModel = new CockpitPayCodeListReadModelData(
        status: 'available',
        authorized: true,
        query: 'PC-123',
        status_filter: 'active',
        stats: new CockpitPayCodeExplorerStatsData(
            total: 5,
            active: 2,
            awaiting_approval: 1,
            redeemed: 1,
            expired: 1,
            pending: 0,
            failed: 0,
            filtered: 2,
        ),
        filters: [
            new CockpitPayCodeExplorerFilterData(
                key: 'status',
                label: 'Active',
                value: 'active',
                active: true,
            ),
        ],
        records: [],
        redactions: ['payloads' => 'sanitized-list-summary-only'],
    );

    expect($readModel->toArray())->toBe([
        'status' => 'available',
        'authorized' => true,
        'query' => 'PC-123',
        'status_filter' => 'active',
        'stats' => [
            'total' => 5,
            'active' => 2,
            'awaiting_approval' => 1,
            'redeemed' => 1,
            'expired' => 1,
            'pending' => 0,
            'failed' => 0,
            'filtered' => 2,
        ],
        'filters' => [
            [
                'key' => 'status',
                'label' => 'Active',
                'value' => 'active',
                'active' => true,
                'read_only' => true,
            ],
        ],
        'records' => [],
        'redactions' => ['payloads' => 'sanitized-list-summary-only'],
    ]);
});
