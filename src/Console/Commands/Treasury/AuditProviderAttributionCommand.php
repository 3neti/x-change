<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Services\Treasury\ProviderStatementAttributionAudit;
use Throwable;

final class AuditProviderAttributionCommand extends Command
{
    protected $signature = 'x-change:treasury:audit-provider-attribution
        {--statement= : Absolute path to a private normalized provider statement CSV}
        {--connection= : Explicit active Treasury connection to inspect}
        {--json : Emit a sanitized machine-readable result}';

    protected $description = 'Audit a private provider statement against local evidence without writes or provider calls';

    public function handle(ProviderStatementAttributionAudit $audit): int
    {
        try {
            $result = $audit->audit(
                (string) $this->option('statement'),
                (string) $this->option('connection'),
            );
        } catch (TreasuryConfigurationException|\InvalidArgumentException $exception) {
            return $this->failure($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure('The provider attribution audit could not be completed safely.');
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Rows', 'Inflows', 'Outflows', 'Unmatched debits', 'Unmatched credits', 'Mismatches'],
                [[
                    $result['rows'],
                    $result['counts']['matched_inflows'],
                    $result['counts']['matched_outflows'],
                    $result['counts']['unmatched_provider_debits'],
                    $result['counts']['unmatched_provider_credits'],
                    $result['counts']['amount_mismatches'] + $result['counts']['status_mismatches'],
                ]],
            );
            $this->components->warn('Read-only evidence only. This report does not authorize reconciliation or money movement.');
        }

        return self::SUCCESS;
    }

    private function failure(string $message): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'status' => 'rejected',
                'read_only' => true,
                'writes_database' => false,
                'provider_calls' => false,
                'moves_money' => false,
                'message' => $message,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
