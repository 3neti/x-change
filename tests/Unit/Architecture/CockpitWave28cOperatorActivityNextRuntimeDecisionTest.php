<?php

declare(strict_types=1);

it('documents cockpit wave 28c operator activity next runtime decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/202-wave-28c-operator-activity-next-runtime-decision.md');

    expect($report)->toContain('Close the Operator Activity filter hardening sequence for now.')
        ->and($report)->toContain('Do not continue adding filter-specific UX in the next wave.')
        ->and($report)->toContain('Cockpit Wave 29 — Pay Code Explorer Runtime Parity / Activity Navigation Bridge')
        ->and($report)->toContain('inspect the current Pay Code Explorer Cockpit page against `/x/pay-codes`')
        ->and($report)->toContain('preserve Cockpit read-only boundaries')
        ->and($report)->toContain('Pay Code Explorer changes')
        ->and($report)->toContain('raw payload display')
        ->and($report)->toContain('Cockpit Wave 28D — Operator Activity Filter Acceptance Closure');
});
