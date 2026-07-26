<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Services\Treasury\TreasurySensitiveMetadataRedaction;
use RuntimeException;
use Throwable;

final class RedactTreasurySensitiveMetadataCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:treasury:redact-sensitive-metadata
        {--authorization-reference= : Stable security-control authorization reference}
        {--commit : Remove configured sensitive metadata}
        {--confirm-security-redaction : Confirm the metadata-only security redaction}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Guardedly remove sensitive values from durable Treasury metadata';

    public function handle(
        TreasurySensitiveMetadataRedaction $redaction,
    ): int {
        try {
            $commit = (bool) $this->option('commit');

            if (
                $commit
                && ! (bool) $this->option(
                    'confirm-security-redaction',
                )
            ) {
                throw new RuntimeException(
                    'Commit requires --confirm-security-redaction.',
                );
            }

            $result = $commit
                ? $redaction->redact(
                    (string) $this->option(
                        'authorization-reference',
                    ),
                )
                : $redaction->inspect();

            $this->renderPayload(
                $result,
                $commit
                    ? 'Treasury sensitive metadata redaction'
                    : 'Treasury sensitive metadata redaction preview',
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.treasury-sensitive-metadata-redaction.v1',
                'success' => false,
                'status' => 'rejected',
                'message' => $exception->getMessage(),
                'committed' => false,
                'request_hashes_changed' => false,
                'money_changed' => false,
                'provider_calls' => false,
            ]);

            return self::FAILURE;
        }
    }
}
