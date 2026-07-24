<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Services\Treasury\HistoricalStandingFundingPositionBackfillService;
use Throwable;

final class BackfillStandingFundingPositionsCommand extends Command
{
    protected $signature = 'x-change:treasury:backfill-standing-funding-positions
        {owner : Account owner primary key}
        {--connection= : Explicit Treasury connection receiving the attribution}
        {--commit : Execute the guarded backfill}
        {--json : Emit a machine-readable result}';

    protected $description = 'Backfill verified historical Standing Funding Receipts into Treasury Positions';

    public function handle(
        HistoricalStandingFundingPositionBackfillService $backfill,
    ): int {
        $connection = trim((string) $this->option('connection'));

        if ($connection === '') {
            $this->components->error('An explicit --connection is required.');

            return self::FAILURE;
        }

        try {
            $owner = $this->owner((string) $this->argument('owner'));
            $result = (bool) $this->option('commit')
                ? $backfill->backfill($owner, $connection)
                : $backfill->inspect($owner, $connection);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $payload = [
            ...$result->toArray(),
            'owner_type' => $owner::class,
            'owner_id' => (string) $owner->getKey(),
            'committed' => (bool) $this->option('commit'),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Owner', 'Connection', 'Provider', 'Amount', 'Candidates', 'Backfilled', 'Status'],
                [[
                    $payload['owner_id'],
                    $payload['connection_reference'],
                    $payload['provider'],
                    $payload['amount_minor'],
                    $payload['candidate_count'],
                    $payload['backfilled_count'],
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
