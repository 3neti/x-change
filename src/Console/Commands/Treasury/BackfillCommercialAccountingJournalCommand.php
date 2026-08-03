<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingJournalBackfill;
use Throwable;

final class BackfillCommercialAccountingJournalCommand extends Command
{
    protected $signature = 'x-change:treasury:backfill-commercial-accounting-journal
        {--sale=* : Limit processing to exact commercial sale references}
        {--commit : Append only safely reconstructible journal events}
        {--authorization-reference= : Stable operator control reference required with --commit}
        {--json : Emit a machine-readable result}';

    protected $description = 'Preview or guardedly append reconstructible commercial accounting journal events';

    public function handle(CommercialAccountingJournalBackfill $backfill): int
    {
        $commit = (bool) $this->option('commit');

        try {
            $result = $commit
                ? $backfill->backfill(
                    array_values((array) $this->option('sale')),
                    (string) $this->option('authorization-reference'),
                )
                : $backfill->inspect(array_values((array) $this->option('sale')));
        } catch (Throwable $exception) {
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode([
                    'schema' => 'x-change.commercial-accounting-journal-backfill.v1',
                    'mode' => $commit ? 'commit' : 'preview',
                    'status' => 'rejected',
                    'message' => $exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
            } else {
                $this->components->error($exception->getMessage());
            }

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Sale', 'Status', 'Journal', 'Can Backfill', 'Review'],
                array_map(static fn (array $sale): array => [
                    $sale['commercial_sale_reference'],
                    $sale['status'],
                    $sale['journal_complete'] ? 'complete' : 'incomplete',
                    $sale['can_backfill'] ? 'yes' : 'no',
                    $sale['review_required'] ? 'required' : 'none',
                ], $result['sales']),
            );
        }

        return $result['unknown_sale_references'] === []
            ? self::SUCCESS
            : self::FAILURE;
    }
}
