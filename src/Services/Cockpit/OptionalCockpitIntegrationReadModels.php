<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use LBHurtado\XChange\Contracts\CockpitRedactorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitActionReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitCampaignReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardActivityData;
use LBHurtado\XChange\Data\Cockpit\CockpitFeedbackReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitJournalReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use Throwable;

class OptionalCockpitIntegrationReadModels
{
    public function __construct(
        private readonly Container $container,
        private readonly CockpitRedactorContract $redactor,
    ) {}

    public function journal(CockpitReadModelQueryData $query): CockpitJournalReadModelData
    {
        $service = $this->resolveOptionalService('journal.reader');

        if ($service === null || ! method_exists($service, 'read')) {
            return $this->journalUnavailable('package-not-installed');
        }

        try {
            $result = $service->read($this->journalQuery($query));
            $payload = $this->redact($this->arrayValue($result));

            return new CockpitJournalReadModelData(
                status: 'available',
                entries: $this->listValue($payload['entries'] ?? []),
                redactions: [
                    'payloads' => 'journal-evidence-summary-only',
                    'source' => 'x-journal',
                    'evidence_only' => true,
                    'lifecycle_truth' => false,
                    'writes_journal_entries' => false,
                    'pagination' => $payload['metadata']['pagination'] ?? null,
                ],
                authorized: true,
            );
        } catch (Throwable $exception) {
            return $this->journalUnavailable('read-model-unavailable', $exception);
        }
    }

    public function actions(CockpitReadModelQueryData $query): CockpitActionReadModelData
    {
        $service = $this->resolveOptionalService('action.composer');

        if ($service === null || ! method_exists($service, 'compose')) {
            return $this->actionsUnavailable('package-not-installed');
        }

        try {
            $result = $service->compose(
                'cockpit.voucher.view',
                $this->actionSubject($query),
                $this->actionContext($query),
                $query->correlationId,
                null,
                true,
            );
            $payload = $this->redact($this->arrayValue($result));

            return new CockpitActionReadModelData(
                status: 'available',
                actions: $this->listValue($payload['actions'] ?? []),
                diagnostics: $this->listValue($payload['meta']['safe_diagnostics'] ?? []),
                redactions: [
                    'payloads' => 'safe-action-host-summary-only',
                    'source' => 'x-action',
                    'presentation_only' => true,
                    'durable_run' => false,
                    'executes_action' => false,
                    'authorizes_action' => false,
                    'records_lifecycle' => false,
                    'raw_diagnostics_exposed' => false,
                ],
                authorized: true,
            );
        } catch (Throwable $exception) {
            return $this->actionsUnavailable('read-model-unavailable', $exception);
        }
    }

    public function feedback(CockpitReadModelQueryData $query): CockpitFeedbackReadModelData
    {
        $service = $this->resolveOptionalService('feedback.console');

        if ($service === null || ! method_exists($service, 'history')) {
            return $this->feedbackUnavailable('package-not-installed');
        }

        try {
            $result = $service->history($this->feedbackFilters($query));
            $payload = $this->redact($this->arrayValue($result));

            return new CockpitFeedbackReadModelData(
                status: 'available',
                deliveries: $this->listValue($payload['records'] ?? []),
                redactions: [
                    'payloads' => 'communication-delivery-summary-only',
                    'source' => 'x-feedback',
                    'communication_state_only' => true,
                    'audit_truth' => false,
                    'sends_feedback' => false,
                    'retries_delivery' => false,
                    'calls_providers' => false,
                ],
                authorized: true,
            );
        } catch (Throwable $exception) {
            return $this->feedbackUnavailable('read-model-unavailable', $exception);
        }
    }

    /**
     * @return array<int, CockpitDashboardActivityData>
     */
    public function executionActivities(CockpitReadModelQueryData $query): array
    {
        $service = $this->resolveOptionalService('journal.reader');

        if ($service === null || ! method_exists($service, 'read')) {
            return [];
        }

        try {
            $result = $service->read($this->executionJournalQuery($query));
            $payload = $this->redact($this->arrayValue($result));
            $entries = $this->listValue($payload['entries'] ?? []);

            return collect($entries)
                ->map(fn (array $entry): ?CockpitDashboardActivityData => $this->executionActivity($entry, $entries))
                ->filter()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function campaignAdoption(CockpitReadModelQueryData $query): CockpitCampaignReadModelData
    {
        $service = $this->resolveOptionalService('campaign.cockpit');

        if ($service === null || ! method_exists($service, 'summary')) {
            return $this->campaignUnavailable('package-not-installed');
        }

        $planningKey = $this->nonEmptyString($query->code);
        $operatorId = $this->nonEmptyString($query->operatorId);

        if ($planningKey === null) {
            return $this->campaignPackageAvailable($query, $service);
        }

        if ($operatorId === null) {
            return $this->campaignUnavailable('missing-operator-context');
        }

        $executionId = $this->nonEmptyString($query->correlationId) ?? $planningKey;

        try {
            $result = $service->summary(
                planningKey: $planningKey,
                executionId: $executionId,
                operatorId: $operatorId,
                channel: 'cockpit',
                correlationId: $query->correlationId,
                metadata: [
                    'source' => 'x-change.cockpit',
                    'read_only' => true,
                    'integration' => 'campaign.cockpit',
                ],
            );
            $payload = $this->redact($this->arrayValue($result));
            $effects = $this->arrayValue($payload['effects'] ?? []);

            return new CockpitCampaignReadModelData(
                status: 'available',
                authorized: true,
                source: 'x-campaign',
                surfaces: $this->campaignSurfaces('available', true, 'x-campaign-read-model-available'),
                facts: [
                    'planning_key' => $this->payloadValue($payload, 'planning_key', 'planningKey') ?? $planningKey,
                    'execution_id' => $this->payloadValue($payload, 'execution_id', 'executionId') ?? $executionId,
                    'operator_id' => $this->payloadValue($payload, 'operator_id', 'operatorId') ?? $operatorId,
                    'cards' => $this->arrayValue($payload['cards'] ?? []),
                    'panels' => $this->arrayValue($payload['panels'] ?? []),
                    'actions' => $this->arrayValue($payload['actions'] ?? []),
                    'blockers' => array_values(array_filter(
                        (array) ($payload['blockers'] ?? []),
                        fn (mixed $blocker): bool => is_string($blocker) && trim($blocker) !== '',
                    )),
                    'metadata' => $this->arrayValue($payload['metadata'] ?? []),
                ],
                mutation: $this->campaignMutation(),
                redactions: [
                    'payloads' => 'campaign-cockpit-summary-only',
                    'source' => 'x-campaign',
                    'read_only' => true,
                    'routes_registered' => false,
                    'controllers_registered' => false,
                    'mutates_campaigns' => false,
                    'issues_pay_codes' => false,
                    'sends_feedback' => false,
                    'writes_journal' => false,
                    'moves_money' => false,
                    'effects' => $effects,
                ],
            );
        } catch (Throwable $exception) {
            return $this->campaignUnavailable('read-model-unavailable', $exception);
        }
    }

    private function resolveOptionalService(string $key): ?object
    {
        $serviceId = $this->serviceId($key);

        if (! is_string($serviceId) || trim($serviceId) === '') {
            return null;
        }

        if (! $this->container->bound($serviceId) && ! class_exists($serviceId) && ! interface_exists($serviceId)) {
            return null;
        }

        try {
            $service = $this->container->make($serviceId);
        } catch (Throwable) {
            return null;
        }

        return is_object($service) ? $service : null;
    }

    private function serviceId(string $key): ?string
    {
        $defaults = [
            'journal.reader' => $this->fqcn('XJournal', 'Services\\CockpitJournalReader'),
            'action.composer' => $this->fqcn('XAction', 'Contracts\\ActionHostComposerContract'),
            'feedback.console' => $this->fqcn('XFeedback', 'Contracts\\FeedbackDeliveryConsoleContract'),
            'campaign.cockpit' => $this->fqcn('XCampaign', 'Contracts\\CampaignCockpitWorkspace'),
        ];

        $configured = config('x-change.cockpit.integrations.'.$key);

        return is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : $defaults[$key] ?? null;
    }

    private function journalQuery(CockpitReadModelQueryData $query): mixed
    {
        $actorClass = $this->fqcn('XJournal', 'Data\\JournalAccessActorData');
        $retrievalClass = $this->fqcn('XJournal', 'Data\\JournalRetrievalQueryData');
        $profileClass = $this->fqcn('XJournal', 'Data\\JournalVisibilityProfileData');
        $queryClass = $this->fqcn('XJournal', 'Data\\CockpitJournalQueryData');

        if (
            class_exists($actorClass)
            && class_exists($retrievalClass)
            && class_exists($profileClass)
            && class_exists($queryClass)
        ) {
            return new $queryClass(
                actor: $actorClass::fromArray([
                    'id' => $query->operatorId,
                    'type' => 'operator',
                    'permissions' => ['x-journal.view'],
                    'metadata' => ['source' => 'x-change.cockpit'],
                ]),
                query: new $retrievalClass(
                    subjectType: 'voucher',
                    subjectId: $query->code,
                    correlationId: $query->correlationId,
                    limit: 5,
                    order: 'desc',
                ),
                visibilityProfile: $profileClass::fromArray(['name' => 'redacted']),
                context: [
                    'code' => $query->code,
                    'source' => 'x-change.cockpit',
                ],
                metadata: [
                    'source' => 'x-change.cockpit',
                    'integration' => 'cockpit.journal',
                ],
            );
        }

        return [
            'actor' => [
                'id' => $query->operatorId,
                'type' => 'operator',
                'permissions' => ['x-journal.view'],
            ],
            'query' => [
                'subject_type' => 'voucher',
                'subject_id' => $query->code,
                'correlation_id' => $query->correlationId,
                'limit' => 5,
                'order' => 'desc',
            ],
            'visibility_profile' => ['name' => 'redacted'],
            'context' => ['code' => $query->code],
            'metadata' => ['source' => 'x-change.cockpit'],
        ];
    }

    private function executionJournalQuery(CockpitReadModelQueryData $query): mixed
    {
        $actorClass = $this->fqcn('XJournal', 'Data\\JournalAccessActorData');
        $retrievalClass = $this->fqcn('XJournal', 'Data\\JournalRetrievalQueryData');
        $profileClass = $this->fqcn('XJournal', 'Data\\JournalVisibilityProfileData');
        $queryClass = $this->fqcn('XJournal', 'Data\\CockpitJournalQueryData');

        if (
            class_exists($actorClass)
            && class_exists($retrievalClass)
            && class_exists($profileClass)
            && class_exists($queryClass)
        ) {
            return new $queryClass(
                actor: $actorClass::fromArray([
                    'id' => $query->operatorId,
                    'type' => 'operator',
                    'permissions' => ['x-journal.view'],
                    'metadata' => ['source' => 'x-change.cockpit.execution-activity'],
                ]),
                query: new $retrievalClass(
                    correlationId: $query->correlationId,
                    limit: 10,
                    order: 'desc',
                ),
                visibilityProfile: new $profileClass(
                    name: 'redacted',
                    redactPayloadKeys: [
                        'raw',
                        'raw_payload',
                        'provider_payload',
                        'wallet',
                        'funding_source',
                        'account_number',
                        'otp',
                        'secret',
                    ],
                ),
                context: [
                    'source' => 'x-change.cockpit',
                    'surface' => 'dashboard.execution_activity',
                ],
                metadata: [
                    'source' => 'x-change.cockpit',
                    'integration' => 'cockpit.execution_activity',
                    'read_only' => true,
                ],
            );
        }

        return [
            'actor' => [
                'id' => $query->operatorId,
                'type' => 'operator',
                'permissions' => ['x-journal.view'],
            ],
            'query' => [
                'correlation_id' => $query->correlationId,
                'limit' => 10,
                'order' => 'desc',
            ],
            'visibility_profile' => ['name' => 'redacted'],
            'context' => ['source' => 'x-change.cockpit', 'surface' => 'dashboard.execution_activity'],
            'metadata' => ['source' => 'x-change.cockpit', 'integration' => 'cockpit.execution_activity', 'read_only' => true],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function executionActivity(array $entry, array $entries): ?CockpitDashboardActivityData
    {
        $payload = $this->arrayValue($entry['payload'] ?? []);
        $references = $this->arrayValue($entry['references'] ?? []);
        $metadata = $this->arrayValue($entry['metadata'] ?? []);
        $eventType = $this->nonEmptyString($entry['event_type'] ?? null);

        if ($eventType !== 'execution.result.recorded') {
            return null;
        }

        $executionId = $this->nonEmptyString($payload['execution_id'] ?? null)
            ?? $this->nonEmptyString($references['execution_id'] ?? null);
        $voucherCode = $this->nonEmptyString($payload['voucher_code'] ?? null)
            ?? $this->nonEmptyString($references['voucher_code'] ?? null);
        $status = $this->nonEmptyString($payload['status'] ?? null) ?? 'recorded';
        $driver = $this->nonEmptyString($payload['driver'] ?? null)
            ?? $this->nonEmptyString($metadata['driver'] ?? null)
            ?? 'execution';
        $timestamp = $this->nonEmptyString($entry['occurred_at'] ?? null);

        if ($executionId === null || $voucherCode === null || $timestamp === null) {
            return null;
        }

        $handoffProfile = $this->executionHandoffProfile(
            entry: $entry,
            summaryEntry: $this->matchingHandoffSummaryEntry(
                entries: $entries,
                executionId: $executionId,
                voucherCode: $voucherCode,
            ),
        );
        $projectionStatus = $this->executionProjectionStatus($handoffProfile);

        return new CockpitDashboardActivityData(
            id: 'execution-'.$executionId,
            label: 'Execution recorded for '.$voucherCode,
            description: $driver.' '.$status.' · '.$executionId,
            timestamp: $timestamp,
            source: 'execution',
            projection_badge: $projectionStatus['badge'],
            projection_status: $projectionStatus['status'],
            projection_detail: $projectionStatus['detail'],
            projection_targets: $this->stringList($handoffProfile['active_targets'] ?? []),
            metadata: [
                'execution_handoff_profile' => $handoffProfile,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $handoffProfile
     * @return array{badge: string, status: string, detail: string}
     */
    private function executionProjectionStatus(array $handoffProfile): array
    {
        $projectionSource = $this->nonEmptyString(data_get($handoffProfile, 'projection.source'));
        $actionEvidence = $this->nonEmptyString(data_get($handoffProfile, 'durable_evidence.action.status'));
        $feedbackEvidence = $this->nonEmptyString(data_get($handoffProfile, 'durable_evidence.feedback.status'));

        if (
            $projectionSource === 'x-journal.execution.handoff.summary.recorded'
            && $actionEvidence === 'projected'
            && $feedbackEvidence === 'projected'
        ) {
            return [
                'badge' => 'Durable summary evidence',
                'status' => 'durable_summary_evidence_available',
                'detail' => 'Action and feedback statuses are projected from x-journal execution.handoff.summary.recorded.',
            ];
        }

        if ($projectionSource === 'x-journal.execution.result.recorded') {
            return [
                'badge' => 'Journal evidence',
                'status' => 'runtime_handoff_profile_only',
                'detail' => 'Execution is journaled; action and feedback statuses require durable post-pipeline evidence.',
            ];
        }

        return [
            'badge' => 'Execution evidence',
            'status' => 'projection_pending',
            'detail' => 'Execution evidence is available without durable handoff summary projection.',
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function executionHandoffProfile(array $entry, ?array $summaryEntry = null): array
    {
        if ($summaryEntry !== null) {
            return $this->durableSummaryExecutionHandoffProfile($summaryEntry);
        }

        $targets = [
            'journal' => 'recorded',
            'action' => $this->configuredExecutionHandoffStatus('action', 'x-action', 'enabled_not_projected'),
            'feedback' => $this->configuredExecutionHandoffStatus('feedback', 'x-feedback', 'enabled_not_projected'),
            'cockpit_activity' => $this->configuredExecutionHandoffStatus('cockpit_activity', 'database', 'enabled_not_projected'),
        ];

        return [
            'schema' => 'x-change.cockpit.execution-handoff-profile.v1',
            'targets' => $targets,
            'active_targets' => collect($targets)
                ->reject(fn (string $status): bool => $status === 'not_wired')
                ->keys()
                ->values()
                ->all(),
            'performed_side_effect_targets' => ['journal'],
            'failed_targets' => collect($targets)
                ->filter(fn (string $status): bool => str_starts_with($status, 'failed'))
                ->keys()
                ->values()
                ->all(),
            'non_blocking' => true,
            'projection' => [
                'source' => 'x-journal.execution.result.recorded',
                'action_feedback_evidence' => 'runtime-config-only',
                'read_only' => true,
                'executes_actions' => false,
                'sends_feedback' => false,
                'moves_money' => false,
            ],
            'durable_evidence' => $this->durableHandoffEvidenceDecision($targets),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<string, mixed>|null
     */
    private function matchingHandoffSummaryEntry(array $entries, string $executionId, string $voucherCode): ?array
    {
        $eventType = $this->nonEmptyString(config('x-change.execution_result_handoffs.durable_evidence_event_type'))
            ?? 'execution.handoff.summary.recorded';

        return collect($entries)
            ->first(function (array $entry) use ($eventType, $executionId, $voucherCode): bool {
                $payload = $this->arrayValue($entry['payload'] ?? []);
                $references = $this->arrayValue($entry['references'] ?? []);

                return ($this->nonEmptyString($entry['event_type'] ?? null) === $eventType)
                    && (($this->nonEmptyString($payload['execution_id'] ?? null) ?? $this->nonEmptyString($references['execution_id'] ?? null)) === $executionId)
                    && (($this->nonEmptyString($payload['voucher_code'] ?? null) ?? $this->nonEmptyString($references['voucher_code'] ?? null)) === $voucherCode);
            });
    }

    /**
     * @param  array<string, mixed>  $summaryEntry
     * @return array<string, mixed>
     */
    private function durableSummaryExecutionHandoffProfile(array $summaryEntry): array
    {
        $payload = $this->arrayValue($summaryEntry['payload'] ?? []);
        $profile = $this->arrayValue($payload['profile'] ?? []);
        $targets = $this->stringMap($profile['targets'] ?? []);
        $targets['handoff_summary_journal'] = 'recorded';
        $activeTargets = $this->stringList($profile['active_targets'] ?? []);
        $performedSideEffectTargets = $this->stringList($profile['performed_side_effect_targets'] ?? []);

        if (! in_array('handoff_summary_journal', $activeTargets, true)) {
            $activeTargets[] = 'handoff_summary_journal';
        }

        if (! in_array('handoff_summary_journal', $performedSideEffectTargets, true)) {
            $performedSideEffectTargets[] = 'handoff_summary_journal';
        }

        return [
            'schema' => 'x-change.cockpit.execution-handoff-profile.v1',
            'targets' => $targets,
            'active_targets' => $activeTargets,
            'performed_side_effect_targets' => $performedSideEffectTargets,
            'failed_targets' => $this->stringList($profile['failed_targets'] ?? []),
            'non_blocking' => (bool) ($profile['non_blocking'] ?? true),
            'projection' => [
                'source' => 'x-journal.execution.handoff.summary.recorded',
                'action_feedback_evidence' => 'durable-summary-journal-event',
                'read_only' => true,
                'executes_actions' => false,
                'sends_feedback' => false,
                'moves_money' => false,
            ],
            'durable_evidence' => $this->durableSummaryHandoffEvidence($summaryEntry, $targets),
        ];
    }

    /**
     * @param  array<string, mixed>  $summaryEntry
     * @param  array<string, string>  $targets
     * @return array<string, array<string, mixed>>
     */
    private function durableSummaryHandoffEvidence(array $summaryEntry, array $targets): array
    {
        $referenceNumber = $this->nullableString($summaryEntry['reference_number'] ?? null);
        $eventType = $this->nonEmptyString($summaryEntry['event_type'] ?? null) ?? 'execution.handoff.summary.recorded';

        $evidence = $this->durableHandoffEvidenceDecision($targets + [
            'journal' => 'recorded',
            'action' => 'not_wired',
            'feedback' => 'not_wired',
            'cockpit_activity' => 'not_wired',
        ]);

        foreach (['action', 'feedback', 'cockpit_activity'] as $target) {
            if (($targets[$target] ?? 'not_wired') === 'not_wired') {
                continue;
            }

            $evidence[$target] = [
                'status' => 'projected',
                'source' => 'x-journal.execution.handoff.summary.recorded',
                'durable' => true,
                'event_type' => $eventType,
                'reference_number' => $referenceNumber,
                'reason' => 'Projected from a persisted post-pipeline execution handoff summary journal entry.',
            ];
        }

        $evidence['handoff_summary_journal'] = [
            'status' => 'projected',
            'source' => 'x-journal.execution.handoff.summary.recorded',
            'durable' => true,
            'event_type' => $eventType,
            'reference_number' => $referenceNumber,
            'reason' => 'The post-pipeline handoff summary itself is persisted in x-journal.',
        ];

        return $evidence;
    }

    private function configuredExecutionHandoffStatus(string $key, string $enabledValue, string $enabledStatus): string
    {
        return config("x-change.execution_result_handoffs.{$key}") === $enabledValue
            ? $enabledStatus
            : 'not_wired';
    }

    /**
     * @param  array<string, string>  $targets
     * @return array<string, array<string, mixed>>
     */
    private function durableHandoffEvidenceDecision(array $targets): array
    {
        $sourceSelection = $this->durableHandoffEvidenceSourceSelection();

        return [
            'journal' => [
                'status' => 'projected',
                'source' => 'x-journal.execution.result.recorded',
                'durable' => true,
                'reason' => 'The Cockpit activity row is projected from a persisted execution journal entry.',
            ],
            'action' => [
                'status' => $targets['action'] === 'not_wired' ? 'not_wired' : 'deferred',
                'source' => null,
                'durable' => false,
                'reason' => $targets['action'] === 'not_wired'
                    ? 'x-action execution-result handoff is not configured.'
                    : 'x-action handoff evidence is not persisted in the execution.result.recorded journal entry.',
                'required_source' => 'future x-action read model, journal event, or durable handoff evidence record',
                'selected_source' => $sourceSelection,
            ],
            'feedback' => [
                'status' => $targets['feedback'] === 'not_wired' ? 'not_wired' : 'deferred',
                'source' => null,
                'durable' => false,
                'reason' => $targets['feedback'] === 'not_wired'
                    ? 'x-feedback execution-result handoff is not configured.'
                    : 'x-feedback handoff evidence is not persisted in the execution.result.recorded journal entry.',
                'required_source' => 'future x-feedback read model, journal event, or durable handoff evidence record',
                'selected_source' => $sourceSelection,
            ],
            'cockpit_activity' => [
                'status' => $targets['cockpit_activity'] === 'not_wired' ? 'not_wired' : 'deferred',
                'source' => null,
                'durable' => false,
                'reason' => $targets['cockpit_activity'] === 'not_wired'
                    ? 'Execution-result Cockpit activity handoff is not configured.'
                    : 'Execution-result Cockpit activity handoff evidence is not projected from durable activity storage yet.',
                'required_source' => 'future durable Cockpit activity record',
                'selected_source' => $sourceSelection,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function durableHandoffEvidenceSourceSelection(): array
    {
        $source = $this->nonEmptyString(config('x-change.execution_result_handoffs.durable_evidence_source'))
            ?? 'post_pipeline_summary_journal_event';
        $eventType = $this->nonEmptyString(config('x-change.execution_result_handoffs.durable_evidence_event_type'))
            ?? 'execution.handoff.summary.recorded';

        return [
            'source' => $source,
            'status' => 'selected_not_implemented',
            'event_type' => $eventType,
            'reason' => 'Selected source for future durable action/feedback handoff evidence projection.',
            'writes_now' => false,
            'read_only' => true,
        ];
    }

    private function actionSubject(CockpitReadModelQueryData $query): mixed
    {
        $class = $this->fqcn('XAction', 'Data\\ActionSubjectData');

        if (class_exists($class)) {
            return new $class(
                type: 'voucher',
                id: $query->code,
                attributes: ['code' => $query->code],
                meta: ['source' => 'x-change.cockpit'],
            );
        }

        return [
            'type' => 'voucher',
            'id' => $query->code,
            'attributes' => ['code' => $query->code],
            'meta' => ['source' => 'x-change.cockpit'],
        ];
    }

    private function actionContext(CockpitReadModelQueryData $query): mixed
    {
        $class = $this->fqcn('XAction', 'Data\\ActionContextData');

        if (class_exists($class)) {
            return new $class(
                actor_type: 'operator',
                actor_id: $query->operatorId,
                surface: 'cockpit',
                capabilities: ['cockpit.view'],
                meta: [
                    'source' => 'x-change.cockpit',
                    'read_only' => true,
                    'code' => $query->code,
                ],
            );
        }

        return [
            'actor_type' => 'operator',
            'actor_id' => $query->operatorId,
            'surface' => 'cockpit',
            'capabilities' => ['cockpit.view'],
            'meta' => ['source' => 'x-change.cockpit', 'read_only' => true],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function feedbackFilters(CockpitReadModelQueryData $query): array
    {
        return array_filter([
            'correlation_id' => $query->correlationId ?: $query->code,
        ], fn (?string $value): bool => $value !== null && trim($value) !== '');
    }

    private function journalUnavailable(string $reason, ?Throwable $exception = null): CockpitJournalReadModelData
    {
        return new CockpitJournalReadModelData(
            status: 'unavailable',
            redactions: $this->unavailableRedactions('x-journal', $reason, $exception),
            authorized: false,
        );
    }

    private function actionsUnavailable(string $reason, ?Throwable $exception = null): CockpitActionReadModelData
    {
        return new CockpitActionReadModelData(
            status: 'unavailable',
            redactions: $this->unavailableRedactions('x-action', $reason, $exception),
            authorized: false,
        );
    }

    private function feedbackUnavailable(string $reason, ?Throwable $exception = null): CockpitFeedbackReadModelData
    {
        return new CockpitFeedbackReadModelData(
            status: 'unavailable',
            redactions: $this->unavailableRedactions('x-feedback', $reason, $exception),
            authorized: false,
        );
    }

    private function campaignUnavailable(string $reason, ?Throwable $exception = null): CockpitCampaignReadModelData
    {
        return new CockpitCampaignReadModelData(
            status: 'unavailable',
            authorized: false,
            source: 'x-campaign',
            surfaces: $this->campaignSurfaces('unavailable', false, $reason),
            mutation: $this->campaignMutation(),
            redactions: $this->unavailableRedactions('x-campaign', $reason, $exception),
        );
    }

    private function campaignPackageAvailable(CockpitReadModelQueryData $query, object $service): CockpitCampaignReadModelData
    {
        return new CockpitCampaignReadModelData(
            status: 'available',
            authorized: true,
            source: 'x-campaign',
            surfaces: $this->campaignSurfaces('available', true, 'x-campaign-package-available'),
            facts: [
                'context_status' => 'no-campaign-selected',
                'selected' => false,
                'operator_id' => $this->nonEmptyString($query->operatorId),
                'cards' => [],
                'panels' => [],
                'actions' => [],
                'blockers' => ['no-campaign-selected'],
                'metadata' => [
                    'source' => 'x-change.cockpit',
                    'read_only' => true,
                    'integration' => 'campaign.cockpit',
                    'package_available' => true,
                ],
            ],
            mutation: $this->campaignMutation(),
            redactions: [
                'payloads' => 'campaign-cockpit-package-presence-only',
                'source' => 'x-campaign',
                'read_only' => true,
                'routes_registered' => false,
                'controllers_registered' => false,
                'mutates_campaigns' => false,
                'issues_pay_codes' => false,
                'sends_feedback' => false,
                'writes_journal' => false,
                'moves_money' => false,
                'reason' => 'no-campaign-selected',
                'effects' => $this->campaignEffects($service),
            ],
        );
    }

    /**
     * @return array<string, bool>
     */
    private function campaignEffects(object $service): array
    {
        if (! method_exists($service, 'effects')) {
            return [
                'persists' => false,
                'uses_database' => false,
                'queues_jobs' => false,
                'issues_pay_codes' => false,
                'sends_feedback' => false,
                'writes_journal' => false,
                'moves_money' => false,
            ];
        }

        try {
            return collect($service->effects())
                ->filter(fn (mixed $value): bool => is_bool($value))
                ->map(fn (bool $value): bool => $value)
                ->all();
        } catch (Throwable) {
            return [
                'persists' => false,
                'uses_database' => false,
                'queues_jobs' => false,
                'issues_pay_codes' => false,
                'sends_feedback' => false,
                'writes_journal' => false,
                'moves_money' => false,
            ];
        }
    }

    /**
     * @return array<int, array{key: string, status: string, enabled: bool, read_only: bool, reason: string}>
     */
    private function campaignSurfaces(string $status, bool $enabled, string $reason): array
    {
        return array_map(
            fn (string $key): array => [
                'key' => $key,
                'status' => $status,
                'enabled' => $enabled,
                'read_only' => true,
                'reason' => $reason,
            ],
            [
                'campaign_dashboard',
                'campaign_explorer',
                'audience_import_workspace',
                'attachment_operator_workspace',
                'campaign_api_descriptors',
            ],
        );
    }

    /**
     * @return array{enabled: bool, status: string, reason: string}
     */
    private function campaignMutation(): array
    {
        return [
            'enabled' => false,
            'status' => 'blocked',
            'reason' => 'campaign-mutations-not-authorized',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableRedactions(string $source, string $reason, ?Throwable $exception): array
    {
        return [
            'payloads' => 'not-loaded',
            'source' => $source,
            'reason' => $reason,
            'exception' => $exception === null ? null : class_basename($exception),
            'exception_message_exposed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if ($value instanceof JsonSerializable) {
            $serialized = $value->jsonSerialize();

            return is_array($serialized) ? $serialized : [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return [];
    }

    private function payloadValue(array $payload, string $snakeKey, string $camelKey): mixed
    {
        return $payload[$snakeKey] ?? $payload[$camelKey] ?? null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }

    private function nonEmptyString(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listValue(mixed $value): array
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (is_object($value) && method_exists($value, 'all')) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): ?array => $this->arrayValue($item) ?: null,
            $value,
        )));
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item, mixed $key): bool => is_string($key) && is_scalar($item))
            ->map(fn (mixed $item): string => (string) $item)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_scalar($item))
            ->map(fn (mixed $item): string => (string) $item)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        return $this->redactor->redact($payload, [
            'api_key',
            'apikey',
            'client_secret',
            'password',
            'provider_response',
            'signature',
        ]);
    }

    private function fqcn(string $package, string $class): string
    {
        return 'LBHurtado\\'.$package.'\\'.$class;
    }
}
