<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Cockpit;

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityRuntimeProfileInspector;

class CockpitReadOnlyPageProps
{
    public function __construct(
        private readonly CockpitReadModelProviderContract $readModels,
        private readonly CockpitOperatorIssuanceActivityRuntimeProfileInspector $operatorActivityRuntimeProfile,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $code = null): array
    {
        return [
            'can' => [
                'view_cockpit' => true,
                'mutate_vouchers' => false,
                'execute_drivers' => false,
                'write_journal_entries' => false,
                'send_feedback' => false,
                'call_providers' => false,
                'move_money' => false,
            ],
            'redaction' => [
                'policy' => 'default-cockpit-redaction',
                'payloads' => 'redacted-until-authorized-read-models-exist',
                'sensitive_fields' => [
                    'account_number',
                    'authorization',
                    'bank_account',
                    'email',
                    'mobile',
                    'otp',
                    'provider_payload',
                    'raw_payload',
                    'reference_id',
                    'secret',
                    'token',
                    'webhook',
                ],
            ],
            'context' => [
                'code' => $code,
            ],
            'read_model' => $this->readModels->forVoucher(new CockpitReadModelQueryData(
                code: $code,
                include: ['voucher', 'execution', 'journal', 'actions', 'feedback'],
            ))->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayCodeExplorerArray(
        ?string $campaignPlanningKey = null,
        ?string $campaignExecutionId = null,
        ?string $campaignSource = null,
    ): array {
        return [
            ...$this->toArray(),
            'pay_codes_read_model' => $this->readModels->forPayCodeList(new CockpitReadModelQueryData(
                include: ['voucher'],
            ))->toArray(),
            'campaign_navigation_context' => $this->campaignNavigationContext(
                campaignPlanningKey: $campaignPlanningKey,
                campaignExecutionId: $campaignExecutionId,
                campaignSource: $campaignSource,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDashboardArray(
        ?string $campaignPlanningKey = null,
        ?string $campaignExecutionId = null,
        ?string $operatorId = null,
    ): array {
        return [
            ...$this->toArray(),
            'dashboard_read_model' => $this->readModels->forDashboard(new CockpitReadModelQueryData(
                include: ['voucher'],
            ))->toArray(),
            'campaign_read_model' => $this->readModels->forCampaignAdoption(new CockpitReadModelQueryData(
                code: $campaignPlanningKey,
                operatorId: $operatorId,
                include: ['campaigns', 'audiences', 'imports', 'attachments', 'api_descriptors'],
                correlationId: $campaignExecutionId,
            ))->toArray(),
            'operator_issuance_activity_read_model' => $this->readModels->forOperatorIssuanceActivity(new CockpitReadModelQueryData(
                operatorId: $operatorId,
                include: ['operator_issuance_activity', 'presentations'],
            ))->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuickGenerateArray(): array
    {
        return [
            ...$this->toArray(),
            'quick_generate_read_model' => $this->readModels->forQuickGenerate(new CockpitReadModelQueryData(
                include: ['templates', 'pricing'],
            ))->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toRuntimeProfileArray(): array
    {
        return [
            ...$this->toArray(),
            'runtime_profile_read_model' => [
                'schema' => 'x-change.cockpit.runtime-profile-page.v1',
                'status' => 'available',
                'authorized' => true,
                'read_only' => true,
                'profile' => $this->operatorActivityRuntimeProfile->inspect()->toArray(),
                'copy' => [
                    'eyebrow' => 'Wave 21 · Runtime diagnostics',
                    'title' => 'Operator Activity Runtime Profile',
                    'description' => 'Read-only visibility into Cockpit operator activity runtime configuration. This page does not enable handoffs, write journal entries, compose actions, send feedback, call providers, mutate vouchers, or move money.',
                ],
                'safety' => [
                    'mutates_configuration' => false,
                    'enables_handoffs' => false,
                    'writes_journal' => false,
                    'executes_action' => false,
                    'sends_feedback' => false,
                    'calls_provider' => false,
                    'moves_money' => false,
                    'owns_lifecycle_truth' => false,
                ],
                'redactions' => [
                    'payloads' => 'runtime-configuration-class-names-only',
                    'raw_payloads_exposed' => false,
                    'provider_payloads_exposed' => false,
                    'wallet_data_exposed' => false,
                    'secrets_exposed' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function campaignNavigationContext(
        ?string $campaignPlanningKey,
        ?string $campaignExecutionId,
        ?string $campaignSource,
    ): ?array {
        if ($campaignPlanningKey === null || $campaignExecutionId === null || $campaignSource !== 'campaign_cockpit') {
            return null;
        }

        return [
            'schema' => 'x-change.cockpit.campaign-navigation.v1',
            'status' => 'available',
            'authorized' => true,
            'source' => 'campaign_cockpit',
            'planning_key' => $campaignPlanningKey,
            'execution_id' => $campaignExecutionId,
            'destination' => 'pay_code_explorer',
            'read_only' => true,
            'mutation' => [
                'enabled' => false,
                'status' => 'blocked',
                'reason' => 'campaign-navigation-read-only',
            ],
            'redactions' => [
                'payloads' => 'navigation-context-only',
                'routes_registered' => false,
                'controllers_registered' => false,
                'mutates_campaigns' => false,
                'issues_pay_codes' => false,
                'sends_feedback' => false,
                'writes_journal' => false,
                'moves_money' => false,
            ],
        ];
    }
}
