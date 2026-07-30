<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Treasury\SystemPrincipalProvisioningService;
use Throwable;

final class ProvisionSystemPrincipalCommand extends Command
{
    protected $signature = 'x-change:system-principal:provision
        {--name= : Display name for a newly created system principal}
        {--email= : Email; must match XCHANGE_SYSTEM_USER_ID}
        {--authorization-reference= : Stable deployment or control authorization}
        {--commit : Create or adopt the configured system principal}
        {--confirm-system-principal : Confirm this non-interactive Account is the system principal}
        {--json : Emit a machine-readable result}';

    protected $description = 'Guardedly provision the non-interactive x-change system principal';

    public function handle(SystemPrincipalProvisioningService $provisioning): int
    {
        $commit = (bool) $this->option('commit');

        if ($commit && ! (bool) $this->option('confirm-system-principal')) {
            return $this->reject(
                'Committing system-principal provisioning requires '
                .'[--confirm-system-principal].',
            );
        }

        if (
            $commit
            && trim((string) $this->option('authorization-reference')) === ''
        ) {
            return $this->reject(
                'Committing system-principal provisioning requires '
                .'[--authorization-reference].',
            );
        }

        try {
            $result = $commit
                ? $provisioning->provision(
                    authorizationReference: (string) $this->option(
                        'authorization-reference',
                    ),
                    name: $this->nullableOption('name'),
                    email: $this->nullableOption('email'),
                )
                : $provisioning->inspect(
                    name: $this->nullableOption('name'),
                    email: $this->nullableOption('email'),
                );
        } catch (Throwable $exception) {
            return $this->reject($exception->getMessage());
        }

        $payload = [
            'schema' => 'x-change.system-principal-provisioning.v1',
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
                    ? 'System principal ready'
                    : 'System principal preview',
            );
            $this->table(
                ['Status', 'Identifier', 'Account ready'],
                [[
                    $result->status,
                    "{$result->identifierColumn}:{$result->identifier}",
                    $result->accountReady ? 'yes' : 'no',
                ]],
            );
        }

        return self::SUCCESS;
    }

    private function nullableOption(string $name): ?string
    {
        $value = trim((string) $this->option($name));

        return $value === '' ? null : $value;
    }

    private function reject(string $message): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'schema' => 'x-change.system-principal-provisioning.v1',
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
