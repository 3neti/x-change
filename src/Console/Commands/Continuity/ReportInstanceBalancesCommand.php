<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Continuity;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Continuity\BuildInstanceBalanceReport;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Services\Continuity\InstanceBalanceReportTextRenderer;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;
use Throwable;

final class ReportInstanceBalancesCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:continuity:balance-report
        {--as-of= : Explicit ISO-8601 report cut-off; defaults to the current time}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Report persisted instance balances without provider calls or state changes';

    protected $help = <<<'HELP'
Build a versioned closing-balance report from persisted Treasury, Account,
Pay Code, and provider-snapshot facts. The command is evidence-only: it never
calls a provider, writes application state, moves money, transfers balances,
restores Accounts, or authorizes a financial action.

Use --as-of with an explicit ISO-8601 timestamp to reproduce the same report
against unchanged persisted state. JSON and human output are rendered from the
same read model and their SHA-256 checksums are included in the result.
HELP;

    protected function configure(): void
    {
        parent::configure();

        $this->setHelp($this->help);
    }

    public function handle(
        BuildInstanceBalanceReport $builder,
        InstanceBalanceReportTextRenderer $text,
        CanonicalKeepsakeJson $json,
    ): int {
        try {
            $asOf = filled($this->option('as-of'))
                ? CarbonImmutable::parse((string) $this->option('as-of'))->utc()
                : CarbonImmutable::now('UTC');
            $result = $builder->handle($asOf);
            $report = $result['report'];
            $jsonArtifact = $json->encode($report);
            $textArtifact = $text->render($report);
            $payload = [
                'schema' => 'x-change.instance-balance-report-envelope.v1',
                'status' => $report['status'],
                'report' => $report,
                'artifact_checksums' => [
                    'canonical_report_json_sha256' => hash('sha256', $jsonArtifact),
                    'human_report_text_sha256' => hash('sha256', $textArtifact),
                ],
                'report_sha256' => $result['report_sha256'],
            ];

            if ($this->shouldOutputJson()) {
                $this->line($json->encode($payload, (bool) $this->option('pretty')));
            } else {
                $this->line(rtrim($textArtifact));
                $this->newLine();
                $this->line('Canonical report JSON SHA-256: '.$payload['artifact_checksums']['canonical_report_json_sha256']);
                $this->line('Human report text SHA-256: '.$payload['artifact_checksums']['human_report_text_sha256']);
                $this->line('Report SHA-256: '.$payload['report_sha256']);
            }

            return $report['status'] === 'complete' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $payload = [
                'schema' => 'x-change.instance-balance-report-envelope.v1',
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'read_only' => true,
                'provider_calls' => false,
                'moves_money' => false,
                'transfers_balances' => false,
                'authorizes_financial_action' => false,
            ];

            if ($this->shouldOutputJson()) {
                $this->line($json->encode($payload, (bool) $this->option('pretty')));
            } else {
                $this->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }
}
