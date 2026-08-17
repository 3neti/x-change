<?php

declare(strict_types=1);

use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Schema;

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

it('uses distinct PostgreSQL-safe constraint names for component economics heads', function () {
    $migration = file_get_contents(
        dirname(__DIR__, 3).'/database/migrations/2026_08_16_074944_create_x_change_commercial_component_economics_tables.php',
    );

    $constraintNames = [
        'xchg_comm_economics_head_activation_unique',
        'xchg_comm_economics_head_activation_fk',
    ];

    expect($migration)->not->toBeFalse()
        ->and(max(array_map(strlen(...), $constraintNames)))->toBeLessThanOrEqual(63)
        ->and(array_unique($constraintNames))->toHaveCount(count($constraintNames));

    foreach ($constraintNames as $constraintName) {
        expect($migration)->toContain("'{$constraintName}'");
    }
});

it('uses distinct PostgreSQL-safe constraint names for standing funding binding revisions', function () {
    $migrationFiles = [
        dirname(__DIR__, 3).'/database/migrations/2026_08_17_070100_create_x_change_standing_funding_address_binding_heads_table.php',
        dirname(__DIR__, 3).'/database/migrations/2026_08_17_070200_create_x_change_standing_funding_address_binding_migrations_table.php',
    ];
    $constraintNames = [
        'xchg_standing_binding_head_revision_unique',
        'xchg_standing_binding_head_revision_foreign',
        'xchg_standing_binding_migration_revision_unique',
        'xchg_standing_binding_migration_revision_foreign',
    ];
    $migrationSource = implode("\n", array_map(
        static fn (string $migrationFile): string => (string) file_get_contents($migrationFile),
        $migrationFiles,
    ));

    expect(max(array_map(strlen(...), $constraintNames)))->toBeLessThanOrEqual(63)
        ->and(array_unique($constraintNames))->toHaveCount(count($constraintNames));

    foreach ($constraintNames as $constraintName) {
        expect($migrationSource)->toContain("'{$constraintName}'");
    }
});

it('compiles every create migration without PostgreSQL identifier collisions', function () {
    $connection = new class(null, 'x_change_migration_audit') extends PostgresConnection
    {
        public function getServerVersion(): string
        {
            return '17.0';
        }

        public function reconnectIfMissingConnection(): void {}
    };
    $connection->useDefaultSchemaGrammar();

    $originalSchemaBuilder = Schema::getFacadeRoot();
    $migrationDirectory = dirname(__DIR__, 3).'/database/migrations';
    $identifiersByPostgresName = [];

    Schema::swap($connection->getSchemaBuilder());

    try {
        foreach (glob($migrationDirectory.'/*.php') ?: [] as $migrationPath) {
            $source = file_get_contents($migrationPath);

            if ($source === false || ! str_contains($source, 'Schema::create(')) {
                continue;
            }

            $migration = require $migrationPath;
            $queries = $connection->pretend(static fn () => $migration->up());

            foreach ($queries as $query) {
                preg_match_all(
                    '/\\b(?:constraint|index)\\s+"([^"]+)"/i',
                    $query['query'],
                    $matches,
                );

                foreach ($matches[1] as $identifier) {
                    $postgresIdentifier = substr($identifier, 0, 63);
                    $identifiersByPostgresName[$postgresIdentifier][] = basename($migrationPath).': '.$identifier;
                }
            }
        }
    } finally {
        Schema::swap($originalSchemaBuilder);
    }

    $collisions = array_filter(
        $identifiersByPostgresName,
        static fn (array $identifiers): bool => count(array_unique($identifiers)) > 1,
    );

    expect($collisions)->toBe([]);
});
