<?php

declare(strict_types=1);

it('keeps funding and header refreshes non-overlapping in the cockpit shell', function () {
    $packageRoot = dirname(__DIR__, 3);
    $fundingPage = file_get_contents($packageRoot.'/resources/js/cockpit/pages/Funding.vue');
    $layout = file_get_contents($packageRoot.'/resources/js/cockpit/layouts/CockpitLayout.vue');

    expect($fundingPage)
        ->toContain('fundingProjectionRefreshInFlight')
        ->toContain('onBefore: () => {')
        ->toContain('return false;')
        ->toContain('onFinish: () => {')
        ->and($fundingPage)
        ->not->toContain("const { start: startFundingPoll, stop: stopFundingPoll } = usePoll(\n    Math.max(1000, props.funding_poll_interval ?? 5000),\n    {\n        only: [\n            'cockpit_header_read_model',")
        ->and($layout)
        ->toContain('balanceRefreshInFlight')
        ->toContain("only: ['cockpit_header_read_model']")
        ->toContain('onFinish: () => {');
});
