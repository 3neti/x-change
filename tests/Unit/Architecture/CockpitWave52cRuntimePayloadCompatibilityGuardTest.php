<?php

declare(strict_types=1);

it('documents the campaign quick generate runtime payload compatibility guard', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/325-wave-52c-runtime-payload-compatibility-guard.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts');

    expect($report)
        ->toContain('Cockpit Wave 52C')
        ->toContain('cash.validation.mobile')
        ->toContain('inputs.fields[] = mobile')
        ->toContain('feedback.mobile')
        ->toContain('No new issuance runtime')
        ->and($component)
        ->toContain('const validationSummary = computed')
        ->toContain('const selectedInputFields = computed')
        ->toContain('cash.validation = validation')
        ->toContain('fields: selectedInputFields.value')
        ->and($frontendTest)
        ->toContain('validation: {')
        ->toContain("inputs: { fields: ['mobile'] }");
});
