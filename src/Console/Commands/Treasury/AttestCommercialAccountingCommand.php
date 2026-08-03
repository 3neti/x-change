<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingAttestation;
use Throwable;

final class AttestCommercialAccountingCommand extends Command
{
    protected $signature = 'x-change:treasury:attest-commercial-accounting
        {--connection=* : Limit attestation to explicit active connections}
        {--json : Emit a machine-readable result}';

    protected $description = 'Attest the commercial waterfall, Treasury positions, Inventory, and journal evidence';

    public function handle(CommercialAccountingAttestation $attestation): int
    {
        try {
            $result = $attestation->inspect(array_values(
                (array) $this->option('connection'),
            ));
        } catch (Throwable $exception) {
            report($exception);

            if ((bool) $this->option('json')) {
                $this->line((string) json_encode([
                    'schema' => 'x-change.commercial-accounting-attestation.v1',
                    'ready' => false,
                    'message' => 'Commercial accounting attestation could not be completed safely.',
                ], JSON_THROW_ON_ERROR));
            } else {
                $this->components->error(
                    'Commercial accounting attestation could not be completed safely.',
                );
            }

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_THROW_ON_ERROR));
        } elseif ($result['ready']) {
            $this->components->info('Commercial accounting is balanced and fully evidenced.');
        } else {
            $this->components->error(sprintf(
                'Commercial accounting requires review (%d issue(s)).',
                $result['issue_count'],
            ));
            $this->table(
                ['Issue', 'Reference', 'Expected', 'Actual'],
                array_map(static fn (array $issue): array => [
                    $issue['code'],
                    $issue['commercial_sale_reference']
                        ?? $issue['position_reference']
                        ?? $issue['connection_reference']
                        ?? $issue['commercial_allocation_id']
                        ?? '—',
                    $issue['expected_minor'] ?? $issue['expected_events'] ?? '—',
                    $issue['actual_minor'] ?? $issue['actual_events'] ?? '—',
                ], $result['issues']),
            );
        }

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
