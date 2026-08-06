<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\XChange\Enums\DeploymentRuntimeTier;

final class ClaimEvidenceStorageReadinessInspector
{
    /**
     * @return array{
     *     runtime_tier: string,
     *     required: bool,
     *     disk: ?string,
     *     driver: ?string,
     *     private: bool,
     *     durable: bool,
     *     ready: bool,
     *     missing_variables: list<string>,
     *     message: string
     * }
     */
    public function inspect(DeploymentRuntimeTier $tier): array
    {
        $disk = trim((string) config('x-change.claim.evidence.disk', 'local'));
        $driver = trim((string) config("filesystems.disks.{$disk}.driver"));
        $private = $disk !== '' && $disk !== 'public' && $driver !== '';
        $durable = $private && $disk !== 'local' && $driver !== 'local';
        $missing = [];

        if (! $private || ($tier->requiresDurableInfrastructure() && ! $durable)) {
            $missing[] = 'XCHANGE_CLAIM_EVIDENCE_DISK';
        }

        if ($tier->requiresDurableInfrastructure() && $driver === 's3') {
            foreach ([
                'AWS_ACCESS_KEY_ID' => 'key',
                'AWS_SECRET_ACCESS_KEY' => 'secret',
                'AWS_BUCKET' => 'bucket',
            ] as $environmentKey => $configurationKey) {
                if (blank(config("filesystems.disks.{$disk}.{$configurationKey}"))) {
                    $missing[] = $environmentKey;
                }
            }
        }

        $missing = array_values(array_unique($missing));
        sort($missing);
        $ready = $missing === [];

        return [
            'runtime_tier' => $tier->value,
            'required' => $tier->requiresDurableInfrastructure(),
            'disk' => $disk === '' ? null : $disk,
            'driver' => $driver === '' ? null : $driver,
            'private' => $private,
            'durable' => $durable,
            'ready' => $ready,
            'missing_variables' => $missing,
            'message' => $this->message($tier, $disk, $ready, $durable),
        ];
    }

    private function message(
        DeploymentRuntimeTier $tier,
        string $disk,
        bool $ready,
        bool $durable,
    ): string {
        if (! $ready) {
            return $tier->requiresDurableInfrastructure()
                ? "runtime tier [{$tier->value}] requires configured durable private claim-evidence storage"
                : 'runtime tier [local] requires a configured private claim-evidence disk';
        }

        if ($durable) {
            return "claim evidence uses the durable private [{$disk}] disk for runtime tier [{$tier->value}]";
        }

        return "claim evidence uses the private [{$disk}] disk permitted for runtime tier [local]";
    }
}
