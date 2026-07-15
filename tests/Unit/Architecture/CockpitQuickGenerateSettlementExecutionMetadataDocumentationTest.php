<?php

declare(strict_types=1);

it('documents the quick generate settlement execution metadata boundary', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $documentation = file_get_contents($packageRoot.'/docs/ui-cockpit/quick-generate-settlement-execution-metadata.md');

    expect($documentation)
        ->toContain('Settlement, execution, and metadata')
        ->toContain('Current Card Mapping')
        ->toContain('Settlement fields')
        ->toContain('Execution instruction')
        ->toContain('Metadata fields')
        ->toContain('Current Execution OS Capability')
        ->toContain('Current Gaps')
        ->toContain('voucher_type')
        ->toContain('target_amount')
        ->toContain('rules')
        ->toContain('cash.settlement_rail')
        ->toContain('ExecutionInstructionData')
        ->toContain('ExecutionContextData')
        ->toContain('ExecutionResultData')
        ->toContain('ExecutionEngine')
        ->toContain('ExecutionDriverRegistry')
        ->toContain('default`, `settlement_envelope`, and `stored_value')
        ->toContain('Voucher owns execution semantics')
        ->toContain('Cockpit does not execute drivers')
        ->toContain('x-journal is not required by the execution engine')
        ->toContain('Metadata is not authority')
        ->toContain('Typed executable slice instructions do not yet exist in voucher');
});
