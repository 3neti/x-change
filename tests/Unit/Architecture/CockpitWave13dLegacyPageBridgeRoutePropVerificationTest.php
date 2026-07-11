<?php

declare(strict_types=1);

it('documents and protects cockpit wave 13d legacy page bridge route prop verification', function () {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/173-wave-13d-legacy-page-bridge-route-prop-verification.md');
    $create = file_get_contents($packageRoot.'/resources/js/pages/x-change/pay-codes/Create.vue');
    $index = file_get_contents($packageRoot.'/resources/js/pages/x-change/pay-codes/Index.vue');
    $balances = file_get_contents($packageRoot.'/resources/js/pages/x-change/balances/Index.vue');
    $callout = file_get_contents($packageRoot.'/resources/js/components/x-change/CockpitBridgeCallout.vue');

    expect($report)->toContain('/x/pay-codes/create')
        ->and($report)->toContain('/x/pay-codes')
        ->and($report)->toContain('/x/balances')
        ->and($report)->toContain('legacy page remains the functional owner')
        ->and($create)->toContain('CockpitBridgeCallout')
        ->and($create)->toContain('props.cockpit_bridge')
        ->and($index)->toContain('CockpitBridgeCallout')
        ->and($index)->toContain('props.cockpit_bridge')
        ->and($balances)->toContain('CockpitBridgeCallout')
        ->and($balances)->toContain('cockpit_bridge')
        ->and($callout)->toContain('legacy page remains the functional owner')
        ->and($callout)->toContain('Open Cockpit');
});
