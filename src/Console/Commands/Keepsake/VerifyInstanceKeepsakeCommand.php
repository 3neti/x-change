<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Keepsake;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Services\Keepsake\VerifyInstanceKeepsakeArchive;
use Throwable;

final class VerifyInstanceKeepsakeCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:instance-keepsake:verify
        {archive : Downloaded encrypted keepsake archive}
        {--private-key-file= : Local private decryption key file}
        {--expected-archive-sha256= : Exact archive checksum returned by the export command}
        {--extract-to= : New private directory for verified extracted contents}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Decrypt and independently verify a downloaded X-Change instance keepsake';

    public function handle(VerifyInstanceKeepsakeArchive $verifier): int
    {
        try {
            $result = $verifier->handle(
                archivePath: (string) $this->argument('archive'),
                keyPath: (string) $this->option('private-key-file'),
                expectedArchiveHash: (string) $this->option('expected-archive-sha256'),
                extractTo: filled($this->option('extract-to')) ? (string) $this->option('extract-to') : null,
            );
            $this->renderPayload($result, 'Instance keepsake verification');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.instance-keepsake-verification.v1',
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'safe_to_reset' => false,
                'migrate_fresh_invoked' => false,
            ]);

            return self::FAILURE;
        }
    }
}
