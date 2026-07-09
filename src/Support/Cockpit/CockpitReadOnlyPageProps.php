<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Cockpit;

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;

class CockpitReadOnlyPageProps
{
    public function __construct(private readonly CockpitReadModelProviderContract $readModels) {}

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
