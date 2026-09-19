<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Keepsake;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Services\Keepsake\InspectInstanceKeepsakeArchive;
use Throwable;

final class InspectInstanceKeepsakeCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:instance-keepsake:inspect
        {archive : Downloaded encrypted keepsake archive}
        {--private-key-file= : Local private decryption key file}
        {--expected-archive-sha256= : Exact archive checksum returned by export}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Verify and summarize an encrypted X-Change instance keepsake without restoring it';

    protected $help = <<<'HELP'
Verify, decrypt in a private temporary workspace, and summarize a keepsake.

The command reports inventory, observed financial totals, privacy scope, and archive
capabilities. It never writes application state, calls a provider, moves money, or
restores Pay Codes. Temporary plaintext is removed before the command returns.
HELP;

    protected function configure(): void
    {
        parent::configure();

        $this->setHelp($this->help);
    }

    public function handle(InspectInstanceKeepsakeArchive $inspector): int
    {
        try {
            $result = $inspector->handle(
                archivePath: (string) $this->argument('archive'),
                keyPath: (string) $this->option('private-key-file'),
                expectedArchiveHash: (string) $this->option('expected-archive-sha256'),
            );
            $this->renderPayload($result, 'Instance keepsake inspection');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.instance-keepsake-inspection.v1',
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'read_only' => true,
                'writes_database' => false,
                'provider_calls' => false,
                'moves_money' => false,
                'safe_to_reset' => false,
            ]);

            return self::FAILURE;
        }
    }
}
