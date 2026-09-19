<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Continuity;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Continuity\PlanInstanceContinuityRecovery;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use Throwable;

final class PlanInstanceContinuityCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:continuity:plan
        {archive : Downloaded encrypted keepsake archive}
        {--private-key-file= : Local private decryption key file}
        {--expected-archive-sha256= : Exact archive checksum returned by export}
        {--destination= : Stable destination instance identifier}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Build a deterministic read-only continuity plan from a verified keepsake';

    protected $help = <<<'HELP'
Build a deterministic recovery proposal for an intended destination instance.

The result classifies portable evidence, reconciliation requirements, unsupported
restore work, and authorization gates. It never imports records, calls providers,
moves money, restores live Pay Codes, or grants restoration authority.
HELP;

    protected function configure(): void
    {
        parent::configure();

        $this->setHelp($this->help);
    }

    public function handle(PlanInstanceContinuityRecovery $planner): int
    {
        try {
            $result = $planner->handle(
                archivePath: (string) $this->argument('archive'),
                keyPath: (string) $this->option('private-key-file'),
                expectedArchiveHash: (string) $this->option('expected-archive-sha256'),
                destinationInstance: (string) $this->option('destination'),
            );
            $this->renderPayload($result, 'Instance continuity plan');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.instance-continuity-plan.v1',
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'apply_supported' => false,
                'read_only' => true,
                'writes_database' => false,
                'provider_calls' => false,
                'moves_money' => false,
                'restores_live_pay_codes' => false,
                'safe_to_reset' => false,
            ]);

            return self::FAILURE;
        }
    }
}
