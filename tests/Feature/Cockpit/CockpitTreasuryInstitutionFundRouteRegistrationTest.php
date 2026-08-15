<?php

declare(strict_types=1);

it('registers institution fund approval and execution routes with compilable parameters', function () {
    expect(route(
        'x-change.cockpit.treasury.institution-funds.approvals.store',
        ['classification' => 'IFC-TEST'],
        false,
    ))->toBe('/x/cockpit/treasury-operations/institution-funds/IFC-TEST/approvals')
        ->and(route(
            'x-change.cockpit.treasury.institution-funds.executions.store',
            ['classification' => 'IFC-TEST'],
            false,
        ))->toBe('/x/cockpit/treasury-operations/institution-funds/IFC-TEST/executions');
});
