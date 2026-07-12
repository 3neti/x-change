<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use LBHurtado\XChange\Contracts\CockpitRedactorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitActionReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitCampaignReadModelData;
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

    public function campaignAdoption(CockpitReadModelQueryData $query): CockpitCampaignReadModelData
    {
        $service = $this->resolveOptionalService('campaign.cockpit');

        if ($service === null || ! method_exists($service, 'summary')) {
            return $this->campaignUnavailable('package-not-installed');
        }

        $planningKey = $this->nonEmptyString($query->code);
        $operatorId = $this->nonEmptyString($query->operatorId);

        if ($planningKey === null || $operatorId === null) {
            return $this->campaignUnavailable('missing-campaign-context');
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
