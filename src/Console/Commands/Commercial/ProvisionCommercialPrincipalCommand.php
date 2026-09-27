<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Commercial\CommercialPrincipalProvisioningService;
use Throwable;

final class ProvisionCommercialPrincipalCommand extends Command
{
    protected $signature = 'x-change:commercial-principal:provision
        {--commit : Create or adopt the configured commercial principal}
        {--confirm-commercial-principal : Confirm this non-login holder owns the Commercial Revenue Account}
        {--json : Emit a machine-readable result}';

    protected $description = 'Guardedly provision the non-login x-change commercial principal';

    public function handle(CommercialPrincipalProvisioningService $provisioning): int
    {
        $commit = (bool) $this->option('commit');

        if ($commit && ! (bool) $this->option('confirm-commercial-principal')) {
            return $this->reject(
                'Committing commercial-principal provisioning requires '
                .'[--confirm-commercial-principal].',
            );
        }

        try {
            $result = $commit
                ? $provisioning->provision()
                : $provisioning->inspect();
        } catch (Throwable $exception) {
            return $this->reject($exception->getMessage());
        }

        $payload = [
            'schema' => 'x-change.commercial-principal-provisioning.v1',
            'success' => true,
            ...$result->toArray(),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->components->info(
                $result->committed
                    ? 'Commercial principal ready'
                    : 'Commercial principal preview',
            );
            $this->table(
                ['Status', 'Reference', 'Commercial Revenue Account ready'],
                [[
                    $result->status,
                    $result->reference,
                    $result->accountReady ? 'yes' : 'no',
                ]],
            );
        }

        return self::SUCCESS;
    }

    private function reject(string $message): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'schema' => 'x-change.commercial-principal-provisioning.v1',
                'success' => false,
                'status' => 'rejected',
                'message' => $message,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
