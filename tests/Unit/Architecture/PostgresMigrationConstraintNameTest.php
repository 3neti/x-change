<?php

declare(strict_types=1);

it('uses distinct PostgreSQL-safe constraint names for transfer matches', function () {
    $migration = file_get_contents(
        dirname(__DIR__, 3).'/database/migrations/2026_07_27_103900_create_x_change_funding_request_transfer_matches_table.php',
    );

    $constraintNames = [
        'xfrtm_funding_request_unique',
        'xfrtm_funding_request_foreign',
        'xfrtm_observation_unique',
        'xfrtm_observation_foreign',
    ];

    expect($migration)->not->toBeFalse()
        ->and(max(array_map(strlen(...), $constraintNames)))->toBeLessThanOrEqual(63)
        ->and(array_unique($constraintNames))->toHaveCount(count($constraintNames));

    foreach ($constraintNames as $constraintName) {
        expect($migration)->toContain("'{$constraintName}'");
    }
});
