<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Services\Treasury\LegacyAccountBalanceMigrationService;
use Throwable;

final class MigrateLegacyAccountBalanceCommand extends Command
{
    protected $signature = 'x-change:treasury:migrate-legacy-account
        {owner : Account owner primary key}
        {--connection= : Explicit Treasury connection receiving the attribution}
        {--commit : Execute the migration}
        {--json : Emit a machine-readable result}';

    protected $description = 'Move one legacy Account balance into provider-reconciled Client Funds';

    public function handle(
        LegacyAccountBalanceMigrationService $migration,
    ): int {
        if (! (bool) $this->option('commit')) {
            $this->components->error(
                'Legacy Account migration is guarded. Re-run with --commit after opening reconciliation.',
            );

            return self::FAILURE;
        }

        $connection = trim((string) $this->option('connection'));

        if ($connection === '') {
            $this->components->error('An explicit --connection is required.');

            return self::FAILURE;
        }

        try {
            $owner = $this->owner((string) $this->argument('owner'));
            $result = $migration->migrate($owner, $connection);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $payload = [
            'status' => $result->status,
            'owner_type' => $owner::class,
            'owner_id' => (string) $owner->getKey(),
            'connection' => $result->connectionReference,
            'provider' => $result->provider,
            'currency' => $result->currency,
            'amount_minor' => $result->amountMinor,
            'allocation_operation_reference' => $result->allocationOperationReference,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Owner', 'Connection', 'Provider', 'Currency', 'Amount', 'Status'],
                [[
                    $payload['owner_id'],
                    $payload['connection'],
                    $payload['provider'],
                    $payload['currency'],
                    $payload['amount_minor'],
                    $payload['status'],
                ]],
            );
        }

        return self::SUCCESS;
    }

    private function owner(string $key): Model
    {
        $model = config(
            'x-change.onboarding.issuer_model',
            config('auth.providers.users.model'),
        );

        if (
            ! is_string($model)
            || ! is_a($model, Model::class, true)
            || trim($key) === ''
        ) {
            throw new \RuntimeException('Configured Account owner model is invalid.');
        }

        return $model::query()->findOrFail($key);
    }
}
