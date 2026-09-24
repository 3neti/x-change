<?php

declare(strict_types=1);

it('uses explicit PostgreSQL-safe names for settlement lifecycle constraints', function () {
    $migrationPaths = [
        __DIR__.'/../../../database/migrations/2026_09_23_000006_create_x_change_provisional_coverages_table.php',
        __DIR__.'/../../../database/migrations/2026_09_23_000007_create_x_change_completion_pay_code_issuances_table.php',
        __DIR__.'/../../../database/migrations/2026_09_24_000000_add_settlement_collection_sources_to_campaign_payments.php',
    ];

    $constraintNames = [
        'xchg_provisional_recognition_unique',
        'xchg_provisional_recognition_foreign',
        'xchg_provisional_envelope_unique',
        'xchg_provisional_envelope_foreign',
        'xchg_completion_coverage_unique',
        'xchg_completion_coverage_foreign',
        'xchg_completion_envelope_unique',
        'xchg_completion_envelope_foreign',
        'xchg_completion_voucher_unique',
        'xchg_completion_voucher_foreign',
        'xchg_campaign_source_collection_unique',
        'xchg_campaign_source_collection_foreign',
        'xchg_campaign_source_attempt_unique',
        'xchg_campaign_source_attempt_foreign',
    ];

    $migrationSource = collect($migrationPaths)
        ->map(fn (string $path): string|false => file_get_contents($path))
        ->implode("\n");

    foreach ($constraintNames as $constraintName) {
        expect(strlen($constraintName))->toBeLessThanOrEqual(63)
            ->and($migrationSource)->toContain("'{$constraintName}'");
    }
});
