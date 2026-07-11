<?php

declare(strict_types=1);

it('documents cockpit wave 15b pass block decision criteria', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/181-wave-15b-pass-block-decision-criteria.md');

    expect($report)->toContain('Go Criteria')
        ->and($report)->toContain('No-Go Criteria')
        ->and($report)->toContain('Proceed to runtime decision')
        ->and($report)->toContain('Do not proceed')
        ->and($report)->toContain('Existing GeneratePayCode handoff')
        ->and($report)->toContain('journal/action/feedback handoffs remain gated')
        ->and($report)->toContain('campaign mutation remains gated');
});
