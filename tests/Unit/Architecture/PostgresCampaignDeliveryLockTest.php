<?php

declare(strict_types=1);

it('locks delivery parent rows instead of PostgreSQL aggregate queries', function () {
    $source = file_get_contents(
        dirname(__DIR__, 3).'/src/Actions/Campaigns/RecordCampaignDeliveryAttempt.php',
    );
    $statements = preg_split('/;/', (string) $source) ?: [];
    $lockedAggregateStatements = array_filter(
        $statements,
        static fn (string $statement): bool => str_contains($statement, 'lockForUpdate()')
            && str_contains($statement, '->max('),
    );

    expect($source)->not->toBeFalse()
        ->and($source)->toContain('CampaignWorksheetAuthorization::query()')
        ->and($source)->toContain('CampaignDeliveryAttempt::query()')
        ->and($lockedAggregateStatements)->toBe([]);
});
