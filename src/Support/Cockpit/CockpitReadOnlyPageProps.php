<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitDistributionWorkspaceItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitDistributionWorkspaceReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivitySearchFilterData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityRuntimeProfileInspector;
use LBHurtado\XChange\Services\Cockpit\CockpitSystemReadinessInspector;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingAttestation;
use LBHurtado\XChange\Services\MoneyIssuer\MoneyIssuerOptionPresenter;
use Throwable;

class CockpitReadOnlyPageProps
{
    public function __construct(
        private readonly CockpitReadModelProviderContract $readModels,
        private readonly CockpitOperatorIssuanceActivityRuntimeProfileInspector $operatorActivityRuntimeProfile,
        private readonly CockpitSystemReadinessInspector $systemReadiness,
        private readonly CommercialAccountingAttestation $commercialAccounting,
        private readonly MoneyIssuerOptionPresenter $moneyIssuers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(
        ?string $code = null,
        bool $includeReadModel = true,
    ): array {
        $props = [
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
        ];

        if ($includeReadModel) {
            $props['read_model'] = $this->readModels->forVoucher(new CockpitReadModelQueryData(
                code: $code,
                include: ['voucher', 'execution', 'journal', 'actions', 'feedback'],
            ))->toArray();
        }

        return $props;
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
        $props = $this->toArray($code);
        $redemption = data_get($props, 'read_model.voucher.summary.redemption');

        return [
            ...$props,
            'payout_institutions' => data_get($redemption, 'can_correct_destination') === true
                ? $this->moneyIssuers->forRail((string) data_get($redemption, 'settlement_rail', 'INSTAPAY'))
                : [],
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
        ?string $operatorId = null,
        ?string $operatorType = null,
        ?Authenticatable $actor = null,
        bool $canViewTechnicalDetails = false,
    ): array {
        $baseProps = $this->toArray(
            includeReadModel: $canViewTechnicalDetails,
        );
        $baseProps['can']['view_technical_details'] = $canViewTechnicalDetails;

        return [
            ...$baseProps,
            'pay_codes_read_model' => $this->readModels->forPayCodeList(new CockpitReadModelQueryData(
                code: $this->normalizeCode($activityCode),
                payCodeSearch: $this->optionalString($search),
                payCodeStatus: $this->optionalString($status),
                operatorId: $this->optionalString($operatorId),
                operatorType: $this->optionalString($operatorType),
                actor: $actor,
                canViewAllPayCodes: $canViewTechnicalDetails,
                include: ['voucher', 'redeemer'],
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
        ?CockpitOperatorIssuanceActivitySearchFilterData $operatorActivityFilters = null,
    ): array {
        return [
            ...$this->toArray(),
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
    public function toRuntimeProfileArray(
        bool $includeCommercialAccounting = false,
    ): array {
        $commercialAccounting = $includeCommercialAccounting
            ? $this->commercialAccountingAttestation()
            : null;

        return [
            ...$this->toArray(),
            'runtime_profile_read_model' => [
                'schema' => 'x-change.cockpit.runtime-profile-page.v1',
                'status' => 'available',
                'authorized' => true,
                'read_only' => true,
                'profile' => $this->operatorActivityRuntimeProfile->inspect()->toArray(),
                'system_readiness' => $this->systemReadiness->inspect(),
                'commercial_accounting' => $commercialAccounting,
                'copy' => [
                    'eyebrow' => 'Operations',
                    'title' => 'System Readiness',
                    'description' => 'Deployment, providers, delivery, and background-work readiness in one safe view.',
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
                    'commercial_accounting_exposed' => $commercialAccounting !== null,
                    'secrets_exposed' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commercialAccountingAttestation(): array
    {
        try {
            return $this->commercialAccounting->inspect();
        } catch (Throwable) {
            return [
                'schema' => 'x-change.commercial-accounting-attestation.v1',
                'ready' => false,
                'connections' => [],
                'commercial_sales' => 0,
                'provider_cost_settlements' => 0,
                'partner_commission_payouts' => 0,
                'issue_count' => 1,
                'issues' => [[
                    'code' => 'attestation_unavailable',
                ]],
                'inspected_at' => now()->toIso8601String(),
            ];
        }
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
        $feedbackDeliveries = $this->distributionFeedbackChannels(
            is_array($feedback['deliveries'] ?? null) ? $feedback['deliveries'] : [],
            $feedbackStatus,
        );
        $actions = is_array($readModel['actions'] ?? null) ? $readModel['actions'] : [];
        $xActionGuidance = $this->distributionActionGuidance(
            is_array($actions['actions'] ?? null) ? $actions['actions'] : [],
            $this->stringValue($actions['status'] ?? null, 'not_wired') ?? 'not_wired',
        );
        $journal = is_array($readModel['journal'] ?? null) ? $readModel['journal'] : [];
        $journalEvidence = $this->distributionJournalEvidence(
            is_array($journal['entries'] ?? null) ? $journal['entries'] : [],
            $this->stringValue($journal['status'] ?? null, 'not_wired') ?? 'not_wired',
            is_array($journal['redactions'] ?? null) ? $journal['redactions'] : [],
        );

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
                ...$feedbackDeliveries,
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
                ...$journalEvidence,
                new CockpitDistributionWorkspaceItemData(
                    key: 'delivery-state',
                    label: 'Delivery state',
                    status: $feedbackStatus,
                    description: count($feedbackDeliveries) > 0
                        ? sprintf('%d x-feedback delivery summary row(s) are available as communication state only.', count($feedbackDeliveries))
                        : 'Delivery truth is communication state from x-feedback, not lifecycle truth.',
                    source: 'feedback-read-model',
                    metadata: [
                        'delivery_count' => count($feedbackDeliveries),
                        'sends_feedback' => false,
                        'retries_delivery' => false,
                    ],
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
                ...$xActionGuidance,
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
                'action_execution_enabled' => false,
                'action_payloads_exposed' => false,
                'journal_writes_enabled' => false,
                'journal_payloads_exposed' => false,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function distributionLinks(?string $code): array
    {
        if ($code === null || trim($code) === '' || ! Route::has('x-change.claim.show')) {
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
            'redeem_url' => route('x-change.claim.show', ['code' => $code]),
            'redeem_path' => route('x-change.claim.show', ['code' => $code], false),
            'source' => 'x-change.claim.show',
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

    /**
     * @param  array<int, array<string, mixed>>  $deliveries
     * @return array<int, CockpitDistributionWorkspaceItemData>
     */
    private function distributionFeedbackChannels(array $deliveries, string $feedbackStatus): array
    {
        $items = collect($deliveries)
            ->map(function (array $delivery, int $index): CockpitDistributionWorkspaceItemData {
                $channel = $this->stringValue($delivery['channel'] ?? null, 'feedback') ?? 'feedback';
                $status = $this->stringValue($delivery['status'] ?? null, 'available') ?? 'available';
                $attemptCount = $delivery['attempt_count'] ?? null;
                $maxAttempts = $delivery['max_attempts'] ?? null;
                $providerStatus = $this->stringValue($delivery['provider_status'] ?? null, null);

                return new CockpitDistributionWorkspaceItemData(
                    key: $this->stringValue($delivery['delivery_id'] ?? null, sprintf('feedback-%d', $index + 1)) ?? sprintf('feedback-%d', $index + 1),
                    label: $this->channelLabel($channel),
                    status: $status,
                    description: 'x-feedback delivery state is shown for operator inspection only; this workspace does not send, retry, or call providers.',
                    available: true,
                    source: 'x-feedback',
                    metadata: array_filter([
                        'channel' => $channel,
                        'provider_status' => $providerStatus,
                        'attempt_count' => is_numeric($attemptCount) ? (int) $attemptCount : null,
                        'max_attempts' => is_numeric($maxAttempts) ? (int) $maxAttempts : null,
                        'communication_state_only' => true,
                        'sends_feedback' => false,
                        'retries_delivery' => false,
                        'calls_providers' => false,
                    ], fn (mixed $value): bool => $value !== null),
                );
            })
            ->values()
            ->all();

        if ($items !== []) {
            return $items;
        }

        return [
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
        ];
    }

    private function channelLabel(string $channel): string
    {
        return match (strtolower($channel)) {
            'sms' => 'SMS',
            'mail', 'email' => 'Email',
            'in_app', 'in-app' => 'In-app',
            default => str($channel)->replace(['_', '-'], ' ')->headline()->toString(),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<int, CockpitDistributionWorkspaceItemData>
     */
    private function distributionActionGuidance(array $actions, string $actionStatus): array
    {
        if ($actionStatus !== 'available') {
            return [];
        }

        return collect($actions)
            ->map(function (array $action, int $index): CockpitDistributionWorkspaceItemData {
                $target = is_array($action['target'] ?? null) ? $action['target'] : [];
                $meta = is_array($action['meta'] ?? null) ? $action['meta'] : [];
                $runSemantics = is_array($meta['run_semantics'] ?? null) ? $meta['run_semantics'] : [];

                return new CockpitDistributionWorkspaceItemData(
                    key: $this->stringValue($action['key'] ?? null, sprintf('x-action-%d', $index + 1)) ?? sprintf('x-action-%d', $index + 1),
                    label: $this->stringValue($action['label'] ?? null, 'Follow-up action') ?? 'Follow-up action',
                    status: $this->stringValue($action['status'] ?? null, 'available') ?? 'available',
                    description: $this->stringValue($action['description'] ?? null, 'x-action follow-up guidance is shown as a disabled read-only CTA; Cockpit does not execute it from Distribution Workspace.') ?? 'x-action follow-up guidance is shown as a disabled read-only CTA; Cockpit does not execute it from Distribution Workspace.',
                    available: true,
                    source: 'x-action',
                    metadata: array_filter([
                        'intent' => $this->stringValue($action['intent'] ?? null, null),
                        'style' => $this->stringValue($action['style'] ?? null, null),
                        'target_type' => $this->stringValue($target['type'] ?? null, null),
                        'target_route' => $this->stringValue($target['route'] ?? null, null),
                        'redirectable' => $target['redirectable'] ?? null,
                        'presentation_run' => $runSemantics['presentation_run'] ?? true,
                        'durable_run' => $runSemantics['durable'] ?? false,
                        'executes_action' => false,
                        'authorizes_action' => false,
                    ], fn (mixed $value): bool => $value !== null),
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<string, mixed>  $redactions
     * @return array<int, CockpitDistributionWorkspaceItemData>
     */
    private function distributionJournalEvidence(array $entries, string $journalStatus, array $redactions): array
    {
        if ($journalStatus !== 'available') {
            return [];
        }

        $payloadPolicy = $this->stringValue($redactions['payloads'] ?? null, 'journal-evidence-summary-only') ?? 'journal-evidence-summary-only';

        return collect($entries)
            ->map(function (array $entry, int $index) use ($payloadPolicy): CockpitDistributionWorkspaceItemData {
                $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
                $eventType = $this->stringValue($entry['event_type'] ?? null, 'journal.entry') ?? 'journal.entry';
                $summary = $this->stringValue($payload['summary'] ?? null, null)
                    ?? $this->stringValue($entry['summary'] ?? null, null)
                    ?? 'Journal evidence summary available.';
                $occurredAt = $this->stringValue($entry['occurred_at'] ?? null, 'timestamp redacted') ?? 'timestamp redacted';

                return new CockpitDistributionWorkspaceItemData(
                    key: $this->stringValue($entry['reference_number'] ?? null, sprintf('journal-%d', $index + 1)) ?? sprintf('journal-%d', $index + 1),
                    label: 'Journal: '.$eventType,
                    status: 'available',
                    description: $summary.' · '.$occurredAt.' · '.$payloadPolicy,
                    available: true,
                    source: 'x-journal',
                    metadata: [
                        'event_type' => $eventType,
                        'payload_policy' => $payloadPolicy,
                        'evidence_only' => true,
                        'writes_journal' => false,
                    ],
                );
            })
            ->take(5)
            ->values()
            ->all();
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
