<?php

declare(strict_types=1);

it('documents the cockpit authorization and redaction review slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/031-authorization-redaction-review.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Integration Slice 2H — Authorization / Redaction Review')
        ->and($report)->toContain('Exception classes, exception messages, raw payloads, provider payloads, recipient addresses, action target URLs, non-durable run IDs, credentials, and internal routes remain hidden')
        ->and($report)->toContain('This slice did not add:')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Completed Host Integration Slice 2H — Authorization / Redaction Review')
        ->and($cockpitCompass)->toContain('reports/031-authorization-redaction-review.md')
        ->and($settlementCompass)->toContain('x-change Host Integration Slice 2H — Authorization / Redaction Review')
        ->and($settlementCompass)->toContain('Exception classes, exception messages, raw payloads, provider payloads, recipient addresses, action target URLs, non-durable run IDs, credentials, and internal routes remain hidden');
});
