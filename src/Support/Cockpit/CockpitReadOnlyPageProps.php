<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Cockpit;

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitDistributionWorkspaceItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitDistributionWorkspaceReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivitySearchFilterData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityRuntimeProfileInspector;

class CockpitReadOnlyPageProps
{
    public function __construct(
        private readonly CockpitReadModelProviderContract $readModels,
        private readonly CockpitOperatorIssuanceActivityRuntimeProfileInspector $operatorActivityRuntimeProfile,
        private readonly CockpitHeaderReadModelProviderContract $headerReadModels,
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
    public function toVoucherDetailArray(
        string $code,
        ?string $campaignPlanningKey = null,
        ?string $campaignExecutionId = null,
        ?string $campaignId = null,
        ?string $campaignAudienceId = null,
        ?string $campaignRecipientId = null,
        ?string $campaignSource = null,
    ): array {
        return [
            ...$this->toArray($code),
            'campaign_navigation_context' => $this->campaignNavigationContext(
                campaignPlanningKey: $campaignPlanningKey,
                campaignExecutionId: $campaignExecutionId,
                campaignSource: $campaignSource,
                destination: 'pay_code_detail',
                campaignId: $campaignId,
                campaignAudienceId: $campaignAudienceId,
                campaignRecipientId: $campaignRecipientId,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayCodeExplorerArray(
        ?string $campaignPlanningKey = null,
        ?string $campaignExecutionId = null,
        ?string $campaignId = null,
        ?string $campaignAudienceId = null,
        ?string $campaignRecipientId = null,
        ?string $campaignSource = null,
        ?string $activityCode = null,
        ?string $activitySource = null,
        ?string $search = null,
        ?string $status = null,
    ): array {
        return [
            ...$this->toArray(),
            'pay_codes_read_model' => $this->readModels->forPayCodeList(new CockpitReadModelQueryData(
                code: $this->normalizeCode($activityCode),
                payCodeSearch: $this->optionalString($search),
                payCodeStatus: $this->optionalString($status),
                include: ['voucher'],
            ))->toArray(),
            'campaign_navigation_context' => $this->campaignNavigationContext(
                campaignPlanningKey: $campaignPlanningKey,
                campaignExecutionId: $campaignExecutionId,
                campaignSource: $campaignSource,
                destination: 'pay_code_explorer',
                campaignId: $campaignId,
                campaignAudienceId: $campaignAudienceId,
                campaignRecipientId: $campaignRecipientId,
            ),
            'activity_navigation_context' => $this->activityNavigationContext(
                activityCode: $activityCode,
                activitySource: $activitySource,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDashboardArray(
        ?string $campaignPlanningKey = null,
        ?string $campaignExecutionId = null,
        ?string $campaignId = null,
        ?string $campaignAudienceId = null,
        ?string $campaignRecipientId = null,
        ?string $campaignSource = null,
        ?string $campaignTemplateKey = null,
        int|float|string|null $campaignAmount = null,
        ?string $campaignCurrency = null,
        ?string $campaignRecipientReference = null,
        ?string $campaignPurpose = null,
        ?string $operatorId = null,
        mixed $operator = null,
        ?CockpitOperatorIssuanceActivitySearchFilterData $operatorActivityFilters = null,
    ): array {
        return [
            ...$this->toArray(),
            'cockpit_header_read_model' => $this->headerReadModels->forOperator($operator)->toArray(),
            'dashboard_read_model' => $this->readModels->forDashboard(new CockpitReadModelQueryData(
                operatorId: $operatorId,
                include: ['voucher'],
            ))->toArray(),
            'campaign_read_model' => $this->readModels->forCampaignAdoption(new CockpitReadModelQueryData(
                code: $campaignPlanningKey,
                operatorId: $operatorId,
                include: ['campaigns', 'audiences', 'imports', 'attachments', 'api_descriptors'],
                correlationId: $campaignExecutionId,
                campaignPlanningKey: $this->optionalString($campaignPlanningKey),
                campaignExecutionId: $this->optionalString($campaignExecutionId),
                campaignId: $this->optionalString($campaignId),
                campaignAudienceId: $this->optionalString($campaignAudienceId),
                campaignRecipientId: $this->optionalString($campaignRecipientId),
                campaignSource: $this->optionalString($campaignSource),
                campaignTemplateKey: $this->optionalString($campaignTemplateKey),
                campaignAmount: $campaignAmount,
                campaignCurrency: $this->optionalString($campaignCurrency),
                campaignRecipientReference: $this->optionalString($campaignRecipientReference),
                campaignPurpose: $this->optionalString($campaignPurpose),
            ))->toArray(),
            'operator_issuance_activity_read_model' => $this->readModels->forOperatorIssuanceActivity(new CockpitReadModelQueryData(
                operatorId: $operatorId,
                include: ['operator_issuance_activity', 'presentations'],
                operatorActivityFilters: $operatorActivityFilters,
            ))->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuickGenerateArray(
        ?string $campaignPlanningKey = null,
        ?string $campaignExecutionId = null,
        ?string $campaignId = null,
        ?string $campaignAudienceId = null,
        ?string $campaignRecipientId = null,
        ?string $campaignSource = null,
        ?string $campaignTemplateKey = null,
        int|float|string|null $campaignAmount = null,
        ?string $campaignCurrency = null,
        ?string $campaignRecipientReference = null,
        ?string $campaignPurpose = null,
    ): array {
        return [
            ...$this->toArray(),
            'quick_generate_read_model' => $this->readModels->forQuickGenerate(new CockpitReadModelQueryData(
                include: ['templates', 'pricing'],
                campaignPlanningKey: $this->optionalString($campaignPlanningKey),
                campaignExecutionId: $this->optionalString($campaignExecutionId),
                campaignId: $this->optionalString($campaignId),
                campaignAudienceId: $this->optionalString($campaignAudienceId),
                campaignRecipientId: $this->optionalString($campaignRecipientId),
                campaignSource: $this->optionalString($campaignSource),
                campaignTemplateKey: $this->optionalString($campaignTemplateKey),
                campaignAmount: $campaignAmount,
                campaignCurrency: $this->optionalString($campaignCurrency),
                campaignRecipientReference: $this->optionalString($campaignRecipientReference),
                campaignPurpose: $this->optionalString($campaignPurpose),
            ))->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDistributionWorkspaceArray(
        string $code,
        ?string $campaignPlanningKey = null,
        ?string $campaignExecutionId = null,
        ?string $campaignId = null,
        ?string $campaignAudienceId = null,
        ?string $campaignRecipientId = null,
        ?string $campaignSource = null,
    ): array {
        $props = $this->toArray($code);

        return [
            ...$props,
            'distribution_workspace_read_model' => $this->distributionWorkspaceReadModel(
                code: $this->normalizeCode($code),
                readModel: $props['read_model'] ?? [],
            )->toArray(),
            'campaign_navigation_context' => $this->campaignNavigationContext(
                campaignPlanningKey: $campaignPlanningKey,
                campaignExecutionId: $campaignExecutionId,
                campaignSource: $campaignSource,
                destination: 'distribution_workspace',
                campaignId: $campaignId,
                campaignAudienceId: $campaignAudienceId,
                campaignRecipientId: $campaignRecipientId,
            ),
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
        string $destination,
        ?string $campaignId = null,
        ?string $campaignAudienceId = null,
        ?string $campaignRecipientId = null,
    ): ?array {
        $source = $campaignSource ?? 'campaign_cockpit';

        if (
            $campaignPlanningKey === null
            || $campaignExecutionId === null
            || ! in_array($source, ['campaign_cockpit', 'x_campaign_adapter'], true)
        ) {
            return null;
        }

        return [
            'schema' => 'x-change.cockpit.campaign-navigation.v1',
            'status' => 'available',
            'authorized' => true,
            'source' => $source,
            'planning_key' => $campaignPlanningKey,
            'execution_id' => $campaignExecutionId,
            'campaign_id' => $campaignId,
            'audience_id' => $campaignAudienceId,
            'recipient_id' => $campaignRecipientId,
            'destination' => $destination,
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

    private function normalizeCode(?string $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>  $readModel
     */
    private function distributionWorkspaceReadModel(?string $code, array $readModel): CockpitDistributionWorkspaceReadModelData
    {
        $voucher = is_array($readModel['voucher'] ?? null) ? $readModel['voucher'] : [];
        $summary = is_array($voucher['summary'] ?? null) ? $voucher['summary'] : [];
        $distributionLinks = is_array($voucher['distribution_links'] ?? null)
            ? $voucher['distribution_links']
            : $this->distributionLinks($code);
        $feedback = is_array($readModel['feedback'] ?? null) ? $readModel['feedback'] : [];
        $feedbackStatus = $this->stringValue($feedback['status'] ?? null, 'not_wired');

        return new CockpitDistributionWorkspaceReadModelData(
            status: $code === null ? 'not_wired' : 'available',
            authorized: $code !== null,
            code: $code,
            summary: [
                'code' => $code,
                'display_status' => $this->stringValue($summary['display_status'] ?? null, $this->stringValue($summary['status'] ?? null, 'not_wired')),
                'amount' => $summary['amount'] ?? null,
                'currency' => $this->stringValue($summary['currency'] ?? null, null),
                'claimed' => $summary['claimed'] ?? null,
                'fully_claimed' => $summary['fully_claimed'] ?? null,
            ],
            distribution_links: $distributionLinks,
            share_assets: [
                new CockpitDistributionWorkspaceItemData(
                    key: 'copy-text',
                    label: 'Copy text',
                    status: 'preview',
                    description: 'Operator-safe Pay Code copy text can be displayed without secret claim material.',
                    available: $code !== null,
                    source: 'voucher-summary',
                    metadata: ['copies_secret_claim_material' => false],
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'qr',
                    label: 'QR asset',
                    status: 'deferred',
                    description: 'QR generation remains disabled until an approved Pay Code representation service is wired.',
                    source: 'distribution-policy',
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'short-link',
                    label: 'Short link',
                    status: 'deferred',
                    description: 'Short-link creation remains disabled until routing, expiration, and redaction policy are approved.',
                    source: 'distribution-policy',
                ),
            ],
            channels: [
                new CockpitDistributionWorkspaceItemData(
                    key: 'sms',
                    label: 'SMS',
                    status: $feedbackStatus,
                    description: 'SMS delivery state must come from x-feedback; this workspace does not send messages.',
                    source: 'feedback-read-model',
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'email',
                    label: 'Email',
                    status: $feedbackStatus,
                    description: 'Email delivery state must come from x-feedback; this workspace does not send messages.',
                    source: 'feedback-read-model',
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'in-app',
                    label: 'In-app',
                    status: $feedbackStatus,
                    description: 'In-app notification state must come from x-feedback; this workspace does not create notifications.',
                    source: 'feedback-read-model',
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'manual',
                    label: 'Manual branch release',
                    status: 'planned',
                    description: 'Manual release requires authorized host workflows before use.',
                    source: 'distribution-policy',
                ),
            ],
            print_templates: [
                new CockpitDistributionWorkspaceItemData(
                    key: 'receipt-card',
                    label: 'Receipt card',
                    status: 'planned',
                    description: 'Receipt card output remains preview-only; no print artifact is generated.',
                    source: 'distribution-policy',
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'branch-sheet',
                    label: 'Branch release sheet',
                    status: 'planned',
                    description: 'Bulk branch sheets remain disabled until explicit artifact generation is approved.',
                    source: 'distribution-policy',
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'counter-slip',
                    label: 'Counter slip',
                    status: 'planned',
                    description: 'Counter slip output remains preview-only; printer integration is not wired.',
                    source: 'distribution-policy',
                ),
            ],
            analytics: [
                new CockpitDistributionWorkspaceItemData(
                    key: 'delivery-state',
                    label: 'Delivery state',
                    status: $feedbackStatus,
                    description: 'Delivery truth is communication state from x-feedback, not lifecycle truth.',
                    source: 'feedback-read-model',
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'campaign-state',
                    label: 'Campaign state',
                    status: 'not_wired',
                    description: 'Campaign distribution state requires x-campaign read models.',
                    source: 'campaign-read-model',
                ),
            ],
            actions: [
                new CockpitDistributionWorkspaceItemData(
                    key: 'send-now',
                    label: 'Send now',
                    status: 'blocked',
                    description: 'Distribution dispatch is not authorized from Cockpit.',
                    source: 'mutation-boundary',
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'generate-print',
                    label: 'Generate print assets',
                    status: 'blocked',
                    description: 'Print artifact generation is not authorized from Cockpit.',
                    source: 'mutation-boundary',
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'create-qr',
                    label: 'Create QR',
                    status: 'blocked',
                    description: 'QR generation is not authorized from Cockpit.',
                    source: 'mutation-boundary',
                ),
                new CockpitDistributionWorkspaceItemData(
                    key: 'create-campaign',
                    label: 'Create campaign',
                    status: 'blocked',
                    description: 'Campaign creation is not authorized from Cockpit.',
                    source: 'mutation-boundary',
                ),
            ],
            redactions: [
                'payloads' => 'distribution-read-model-summary-only',
                'raw_payloads_exposed' => false,
                'provider_payloads_exposed' => false,
                'wallet_data_exposed' => false,
                'secret_claim_material_exposed' => false,
                'dispatch_enabled' => false,
                'artifact_generation_enabled' => false,
                'campaign_mutation_enabled' => false,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function distributionLinks(?string $code): array
    {
        if ($code === null || trim($code) === '' || ! Route::has('x-change.claim.experience')) {
            return [
                'schema' => 'x-change.cockpit.distribution-links.v1',
                'status' => 'unavailable',
                'available' => false,
                'read_only' => true,
                'delivery_enabled' => false,
                'redactions' => [
                    'payloads' => 'distribution-links-unavailable',
                ],
            ];
        }

        return [
            'schema' => 'x-change.cockpit.distribution-links.v1',
            'status' => 'available',
            'available' => true,
            'read_only' => true,
            'redeem_url' => route('x-change.claim.experience', ['code' => $code]),
            'redeem_path' => route('x-change.claim.experience', ['code' => $code], false),
            'source' => 'x-change.claim.experience',
            'delivery_enabled' => false,
            'redactions' => [
                'payloads' => 'distribution-links-only',
                'secret_claim_material_exposed' => false,
                'provider_payloads_exposed' => false,
                'wallet_data_exposed' => false,
                'delivery_payloads_exposed' => false,
            ],
        ];
    }

    private function stringValue(mixed $value, ?string $fallback = ''): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $fallback;
    }

    private function optionalString(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activityNavigationContext(?string $activityCode, ?string $activitySource): ?array
    {
        $code = $this->normalizeCode($activityCode);

        if ($code === null) {
            return null;
        }

        return [
            'schema' => 'x-change.cockpit.activity-navigation.v1',
            'status' => 'available',
            'authorized' => true,
            'source' => $this->optionalString($activitySource) ?? 'operator_issuance_activity',
            'code' => $code,
            'destination' => 'pay_code_explorer',
            'read_only' => true,
            'mutation' => [
                'enabled' => false,
                'status' => 'blocked',
                'reason' => 'activity-navigation-read-only',
            ],
            'redactions' => [
                'payloads' => 'activity-navigation-context-only',
                'routes_registered' => true,
                'controllers_registered' => true,
                'mutates_vouchers' => false,
                'executes_drivers' => false,
                'writes_journal' => false,
                'sends_feedback' => false,
                'calls_providers' => false,
                'moves_money' => false,
            ],
        ];
    }
}
