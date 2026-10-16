<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Commercial\PublicIssuanceEscrowPrincipalProvisioningService;
use Throwable;

final class ProvisionPublicIssuanceEscrowPrincipalCommand extends Command
{
    protected $signature = 'x-change:public-issuance-escrow:provision
        {--commit : Create or adopt the configured escrow principal}
        {--confirm : Confirm this non-login holder owns public issuance escrow}
        {--json : Emit a machine-readable result}';

    protected $description = 'Guardedly provision the non-login public issuance escrow principal';

    public function handle(PublicIssuanceEscrowPrincipalProvisioningService $provisioning): int
    {
        $commit = (bool) $this->option('commit');

        if ($commit && ! (bool) $this->option('confirm')) {
            return $this->reject('Committing public issuance escrow provisioning requires [--confirm].');
        }

        try {
            $result = $commit ? $provisioning->provision() : $provisioning->inspect();
        } catch (Throwable $exception) {
            return $this->reject($exception->getMessage());
        }

        $payload = [
            'schema' => 'x-change.public-issuance-escrow-provisioning.v1',
            'success' => true,
            ...$result->toArray(),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info($result->committed ? 'Public issuance escrow principal ready' : 'Public issuance escrow preview');
            $this->table(['Status', 'Reference', 'Platform account ready'], [[
                $result->status,
                $result->reference,
                $result->accountReady ? 'yes' : 'no',
            ]]);
        }

        return self::SUCCESS;
    }

    private function reject(string $message): int
    {
        $payload = [
            'schema' => 'x-change.public-issuance-escrow-provisioning.v1',
            'success' => false,
            'status' => 'rejected',
            'message' => $message,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
