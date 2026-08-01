<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Services\Configuration\CommissioningManifestReadinessInspector;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;
use LBHurtado\XChange\Services\Configuration\DeploymentConfigurationInspector;
use LBHurtado\XChange\Services\Configuration\PreInstallReadinessInspector;
use LBHurtado\XChange\Services\Configuration\RuntimeOperationsChecklist;
use LBHurtado\XChange\Services\Configuration\SystemPrincipalAccountReadinessInspector;
use Throwable;

final readonly class CockpitSystemReadinessInspector
{
    public function __construct(
        private PreInstallReadinessInspector $readiness,
        private DeploymentConfigurationInspector $deployment,
        private CommissioningStateResolver $commissioning,
        private CommissioningManifestReadinessInspector $manifest,
        private SystemPrincipalAccountReadinessInspector $systemPrincipal,
        private RuntimeOperationsChecklist $runtimeOperations,
        private CockpitOperatorIssuanceActivityRuntimeProfileInspector $operatorActivity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $readiness = $this->readiness->inspect();
        $commissioning = $this->commissioning->resolve();
        $deployment = $this->deploymentFacts();
        $checks = collect($readiness['checks'])
            ->keyBy('name');
        $systemPrincipal = $this->systemPrincipal->inspect();
        $manifest = $this->manifest->inspect($commissioning);
        $allChecks = collect($readiness['checks'])
            ->push($systemPrincipal)
            ->push($manifest);
        $readyCount = $allChecks->where('passed', true)->count();
        $totalCount = $allChecks->count();

        return [
            'schema' => 'x-change.cockpit.system-readiness.v1',
            'status' => $commissioning->isOperational() && $readiness['ready']
                ? 'operational'
                : 'attention_required',
            'checked_at' => now()->toIso8601String(),
            'summary' => [
                'ready' => $readyCount,
                'total' => $totalCount,
                'attention' => $totalCount - $readyCount,
            ],
            'context' => [
                'environment' => (string) config('app.env', 'unknown'),
                'profile' => $readiness['profile'],
                'active_connections' => $deployment['active_connections'],
                'active_providers' => $deployment['active_providers'],
            ],
            'sections' => [
                $this->section(
                    'deployment',
                    'Deployment',
                    'Identity, application security, and installation state.',
                    [
                        $checks->get('deployment configuration'),
                        $checks->get('system principal identity'),
                        $checks->get('production application security'),
                        $systemPrincipal,
                        $manifest,
                    ],
                ),
                $this->section(
                    'runtime',
                    'Runtime Services',
                    'Durable work, scheduling locks, and live updates.',
                    [
                        $checks->get('durable queue runtime'),
                        $checks->get('shared scheduler lock cache'),
                        $checks->get('funding broadcast runtime'),
                    ],
                ),
                $this->section(
                    'delivery',
                    'Delivery And Access',
                    'Recipient verification and configured communication channels.',
                    [
                        $checks->get('production onboarding OTP'),
                        $checks->get('campaign email delivery'),
                        $checks->get('SMS delivery'),
                    ],
                ),
            ],
            'providers' => [
                'status' => $deployment['ready'] ? 'ready' : 'attention',
                'active' => $deployment['active_providers'],
                'connections' => $deployment['active_connections'],
                'installed_but_disabled' => $deployment['installed_but_disabled_providers'],
                'capabilities' => $deployment['capability_readiness'],
            ],
            'runtime_processes' => $this->runtimeOperations->describe(),
            'technical' => [
                'operator_activity' => $this->operatorActivity->inspect()->toArray(),
                'legacy_published_config' => $deployment['legacy_published_config'],
            ],
            'redactions' => [
                'secrets_exposed' => false,
                'credentials_exposed' => false,
                'account_numbers_exposed' => false,
                'provider_payloads_exposed' => false,
                'raw_responses_exposed' => false,
                'performs_live_provider_checks' => false,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>|null>  $checks
     * @return array<string, mixed>
     */
    private function section(
        string $key,
        string $label,
        string $description,
        array $checks,
    ): array {
        $publicChecks = collect($checks)
            ->filter(static fn (mixed $check): bool => is_array($check))
            ->map(fn (array $check): array => $this->publicCheck($check))
            ->values()
            ->all();

        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'status' => collect($publicChecks)->every(
                static fn (array $check): bool => $check['passed'],
            ) ? 'ready' : 'attention',
            'checks' => $publicChecks,
        ];
    }

    /**
     * @param  array<string, mixed>  $check
     * @return array{name: string, passed: bool, message: string}
     */
    private function publicCheck(array $check): array
    {
        $name = (string) ($check['name'] ?? 'readiness check');

        return [
            'name' => match ($name) {
                'deployment configuration' => 'Deployment Profile',
                'system principal identity' => 'System Identity',
                'production application security' => 'Application Security',
                'system principal account' => 'System Account',
                'installation manifest' => 'Installation Record',
                'durable queue runtime' => 'Background Workers',
                'shared scheduler lock cache' => 'Scheduler Locks',
                'funding broadcast runtime' => 'Live Funding Updates',
                'production onboarding OTP' => 'Onboarding Verification',
                'campaign email delivery' => 'Email Delivery',
                'SMS delivery' => 'SMS Delivery',
                default => $name,
            },
            'passed' => (bool) ($check['passed'] ?? false),
            'message' => (string) ($check['message'] ?? 'readiness could not be determined'),
        ];
    }

    /**
     * @return array{
     *     active_connections: list<string>,
     *     active_providers: list<string>,
     *     installed_but_disabled_providers: list<string>,
     *     capability_readiness: array<string, array{ready: bool, missing: list<string>}>,
     *     legacy_published_config: bool,
     *     ready: bool
     * }
     */
    private function deploymentFacts(): array
    {
        try {
            $deployment = $this->deployment->inspect();

            return [
                'active_connections' => $deployment['active_connections'],
                'active_providers' => $deployment['active_providers'],
                'installed_but_disabled_providers' => $deployment['installed_but_disabled_providers'],
                'capability_readiness' => $deployment['capability_readiness'],
                'legacy_published_config' => $deployment['legacy_published_config'],
                'ready' => $deployment['ready'],
            ];
        } catch (Throwable) {
            return [
                'active_connections' => [],
                'active_providers' => [],
                'installed_but_disabled_providers' => [],
                'capability_readiness' => [],
                'legacy_published_config' => false,
                'ready' => false,
            ];
        }
    }
}
