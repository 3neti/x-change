<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Support\Facades\Schema;
use LBHurtado\XAffiliation\Models\AffiliationNetwork;

final readonly class AffiliationReadinessInspector
{
    /**
     * @return array{name:string,passed:bool,message:string,meta:array<string,mixed>}
     */
    public function inspect(bool $requireNetwork = true): array
    {
        $enabled = (bool) config('x-change.affiliation.enabled', false);

        if (! $enabled) {
            return [
                'name' => 'affiliation network',
                'passed' => true,
                'message' => 'affiliation networking is explicitly disabled',
                'meta' => ['enabled' => false, 'missing_variables' => []],
            ];
        }

        $instanceId = trim((string) config('x-change.instance.id'));
        $pepper = trim((string) config('x-affiliation.identity_pepper'));
        $missing = [];

        if ($instanceId === '') {
            $missing[] = 'XCHANGE_INSTANCE_ID';
        }

        if ($pepper === '') {
            $missing[] = 'X_AFFILIATION_IDENTITY_PEPPER';
        }

        $networkReady = ! $requireNetwork;

        if ($requireNetwork && $missing === [] && Schema::hasTable('x_affiliation_networks')) {
            $networkReady = AffiliationNetwork::query()
                ->where('scope_type', (string) config(
                    'x-affiliation.default_network_scope_type',
                    'x-change-installation',
                ))
                ->where('scope_reference', $instanceId)
                ->exists();
        }

        $passed = $missing === [] && $networkReady;

        return [
            'name' => 'affiliation network',
            'passed' => $passed,
            'message' => $passed
                ? ($requireNetwork
                    ? 'stable application-instance affiliation network is ready'
                    : 'affiliation identity configuration is ready')
                : ($missing !== []
                    ? 'affiliation identity configuration is incomplete'
                    : 'application-instance affiliation network has not been commissioned'),
            'meta' => [
                'enabled' => true,
                'instance_id_configured' => $instanceId !== '',
                'identity_pepper_configured' => $pepper !== '',
                'network_required' => $requireNetwork,
                'network_ready' => $networkReady,
                'missing_variables' => $missing,
            ],
        ];
    }
}
