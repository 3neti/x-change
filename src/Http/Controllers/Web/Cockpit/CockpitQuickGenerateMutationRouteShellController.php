<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftCompilerContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftValidatorContract;
use LBHurtado\XChange\Contracts\CockpitQuickGenerateDraftFactoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftValidationResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityHandoffPipeline;
use LBHurtado\XChange\Services\IdempotencyService;
use Throwable;

class CockpitQuickGenerateMutationRouteShellController extends Controller
{
    public function __invoke(
        GeneratePayCodeRequest $request,
        GeneratePayCode $generatePayCode,
        IdempotencyService $idempotency,
        CockpitOperatorIssuanceActivityHandoffPipeline $operatorIssuanceActivityHandoffPipeline,
        CockpitQuickGenerateDraftFactoryContract $quickGenerateDraftFactory,
        CockpitIssuanceDraftValidatorContract $draftValidator,
        CockpitIssuanceDraftCompilerContract $draftCompiler,
        EstimatePayCodeCost $estimatePayCodeCost,
        BuildBalanceOverview $balanceOverview,
    ): JsonResponse {
        $payload = $request->validated();
        $payload = $this->normalizePayloadForIssuance($payload);
        $validatedPayload = $payload;
        $key = $idempotency->extractKey($request);
        $correlationId = $request->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID'));
        $issuerId = $request->user()?->getAuthIdentifier();

        $draft = $quickGenerateDraftFactory->fromPayload(
            $payload,
            idempotencyKey: $key,
            correlationId: is_string($correlationId) ? $correlationId : null,
        );
        $this->ensureDraftIsValid($draftValidator->validate($draft));

        $payload = array_replace_recursive($validatedPayload, $draftCompiler->compile($draft));
        $payload['_meta'] = [
            'idempotency_key' => $key,
            'correlation_id' => is_string($correlationId) ? $correlationId : null,
            'source' => 'cockpit.quick-generate',
        ];

        if ($issuerId !== null) {
            data_set($payload, 'metadata.issuer_id', (string) $issuerId);
        }

        if (is_string($key)) {
            $recalled = $idempotency->recallOrValidate($key, $payload);

            if (is_array($recalled)) {
                data_set($recalled, 'idempotency.replayed', true);
                data_set($recalled, 'status', 'replayed');

                return response()->json($recalled, 200);
            }
        }

        $pricingPreflight = $this->pricingPreflight($payload, $estimatePayCodeCost);
        $fundingPreflight = $this->fundingPreflight($request, $balanceOverview);
        $result = $generatePayCode->handle($payload);
        $response = $this->responsePayload($request, $result, $key, false, $pricingPreflight, $fundingPreflight, $payload);
        $this->processOperatorIssuanceActivity($request, $response, $key, $operatorIssuanceActivityHandoffPipeline);

        if (is_string($key)) {
            $idempotency->remember($key, $payload, $response);
        }

        return response()->json($response, 201);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePayloadForIssuance(array $payload): array
    {
        if (data_get($payload, 'cash.validation') === null) {
            data_set($payload, 'cash.validation', []);
        }

        if (data_get($payload, 'count') === null) {
            data_set($payload, 'count', 1);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function responsePayload(
        GeneratePayCodeRequest $request,
        GeneratePayCodeResultData $result,
        ?string $key,
        bool $replayed,
        array $pricingPreflight = [],
        array $fundingPreflight = [],
        array $payload = [],
    ): array {
        $campaignAttribution = $this->campaignAttribution($payload, $result);

        return [
            'schema' => 'x-change.cockpit.quick-generate-existing-issuance-handoff.v1',
            'status' => 'issued',
            'authorized' => $request->user() !== null,
            'mutation_enabled' => true,
            'runtime_enabled' => true,
            'route' => 'x-change.cockpit.quick-generate.store',
            'request_adapter' => 'GeneratePayCodeRequest-compatible-adapter',
            'issuance_owner' => 'GeneratePayCode',
            'validation' => [
                'status' => 'executed',
                'request' => 'GeneratePayCodeRequest',
                'executed' => true,
                'draft_validator' => 'CockpitIssuanceDraftValidatorContract',
            ],
            'draft' => [
                'status' => 'compiled',
                'factory' => 'CockpitQuickGenerateDraftFactoryContract',
                'compiler' => 'CockpitIssuanceDraftCompilerContract',
                'source' => 'cockpit.quick-generate',
            ],
            'handoff' => [
                'status' => 'executed',
                'target' => 'GeneratePayCode',
                'controller' => 'GeneratePayCodeController',
                'executed' => true,
                'controller_invoked' => false,
            ],
            'preflight' => [
                'pricing' => $pricingPreflight,
                'funding' => $fundingPreflight,
            ],
            'activity' => [
                'schema' => 'x-change.cockpit.operator-issuance-activity.v1',
                'status' => 'recording-attempted-after-issuance',
                'source' => 'cockpit.quick-generate',
                'presentation_only' => true,
                'metadata_alignment' => 'response-and-activity-share-operator-safe-runtime-facts',
            ],
            'idempotency' => [
                'status' => is_string($key) ? 'replay-safe' : 'key-not-provided',
                'key' => $key,
                'persisted' => is_string($key),
                'fingerprinted' => is_string($key),
                'replay_checked' => is_string($key),
                'replayed' => $replayed,
            ],
            'result' => $this->redactedResult($result),
            'campaign_attribution' => $campaignAttribution,
            'post_issuance_navigation' => $this->postIssuanceNavigation($result, $campaignAttribution),
            'redactions' => [
                'payloads' => 'operator-safe-generated-facts-only',
                'request_payload' => 'excluded',
                'validated_payload' => 'excluded',
                'provider_payload' => 'excluded',
                'wallet' => 'excluded',
                'debit' => 'excluded',
                'allocations' => 'excluded',
                'cost' => 'excluded',
                'raw_payload' => 'excluded',
            ],
            'next_step' => 'Draft next Cockpit mutation wave before adding more write behavior.',
        ];
    }

    protected function ensureDraftIsValid(CockpitIssuanceDraftValidationResultData $validation): void
    {
        if ($validation->valid) {
            return;
        }

        throw ValidationException::withMessages([
            'draft' => array_values($validation->errors),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function pricingPreflight(array $payload, EstimatePayCodeCost $estimatePayCodeCost): array
    {
        try {
            $estimate = $estimatePayCodeCost->handle($payload);

            return [
                'status' => 'estimated',
                'currency' => $estimate->currency,
                'base_fee' => $estimate->base_fee,
                'total' => $estimate->total,
                'components' => $estimate->components,
                'blocking' => false,
                'source' => 'EstimatePayCodeCost',
            ];
        } catch (Throwable $throwable) {
            return [
                'status' => 'unavailable',
                'blocking' => false,
                'source' => 'EstimatePayCodeCost',
                'reason' => $throwable::class,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function fundingPreflight(GeneratePayCodeRequest $request, BuildBalanceOverview $balanceOverview): array
    {
        try {
            $overview = $balanceOverview->handle($request->user(), syncIfStale: false);

            return [
                'status' => 'checked',
                'provider' => data_get($overview, 'provider'),
                'topology' => data_get($overview, 'topology'),
                'authority' => data_get($overview, 'authority'),
                'sync_status' => data_get($overview, 'sync_status'),
                'authoritative' => [
                    'key' => data_get($overview, 'authoritative.key'),
                    'authority' => data_get($overview, 'authoritative.authority'),
                    'source' => data_get($overview, 'authoritative.source'),
                    'balance' => data_get($overview, 'authoritative.balance'),
                    'currency' => data_get($overview, 'authoritative.currency'),
                    'is_stale' => data_get($overview, 'authoritative.is_stale'),
                ],
                'blocking' => false,
                'source' => 'BuildBalanceOverview',
            ];
        } catch (Throwable $throwable) {
            return [
                'status' => 'unavailable',
                'blocking' => false,
                'source' => 'BuildBalanceOverview',
                'reason' => $throwable::class,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function redactedResult(GeneratePayCodeResultData $result): array
    {
        return [
            'code' => $result->code,
            'amount' => $result->amount,
            'currency' => $result->currency,
            'links' => [
                'redeem' => $result->links->redeem,
                'redeem_path' => $result->links->redeem_path,
                'cockpit_detail' => Route::has('x-change.cockpit.pay-codes.show')
                    ? route('x-change.cockpit.pay-codes.show', ['code' => $result->code], false)
                    : null,
                'cockpit_distribution' => Route::has('x-change.cockpit.pay-codes.distribution')
                    ? route('x-change.cockpit.pay-codes.distribution', ['code' => $result->code], false)
                    : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaignAttribution(array $payload, GeneratePayCodeResultData $result): array
    {
        $campaign = (array) data_get($payload, 'metadata.campaign', []);
        $planningKey = $this->stringValue(data_get($campaign, 'planning_key'));
        $executionId = $this->stringValue(data_get($campaign, 'execution_id'));
        $campaignId = $this->stringValue(data_get($campaign, 'campaign_id'));
        $audienceId = $this->stringValue(data_get($campaign, 'audience_id'));
        $recipientId = $this->stringValue(data_get($campaign, 'recipient_id'));

        if ($planningKey === null && $executionId === null && $campaignId === null) {
            return [
                'schema' => 'x-change.cockpit.quick-generate-campaign-attribution.v1',
                'status' => 'not_available',
                'available' => false,
                'read_only' => true,
                'mutates_campaign' => false,
                'redactions' => [
                    'payloads' => 'not-loaded',
                ],
            ];
        }

        return [
            'schema' => 'x-change.cockpit.quick-generate-campaign-attribution.v1',
            'status' => 'available',
            'available' => true,
            'read_only' => true,
            'mutates_campaign' => false,
            'planning_key' => $planningKey,
            'execution_id' => $executionId,
            'campaign_id' => $campaignId,
            'audience_id' => $audienceId,
            'recipient_id' => $recipientId,
            'source' => $this->stringValue(data_get($campaign, 'source')) ?? 'campaign_cockpit',
            'generated_code' => $result->code,
            'template_key' => $this->stringValue(data_get($payload, 'metadata.custom.cockpit.template_key')),
            'amount' => $this->stringValue(data_get($payload, 'cash.amount')),
            'currency' => $this->stringValue(data_get($payload, 'cash.currency')),
            'recipient_reference' => $this->stringValue(data_get($payload, 'feedback.mobile')),
            'purpose' => $this->stringValue(data_get($payload, 'rider.message')),
            'redactions' => [
                'payloads' => 'campaign-attribution-only',
                'excluded' => [
                    'campaign_payload',
                    'recipient_payload',
                    'provider_payload',
                    'wallet',
                    'balance',
                    'raw_payload',
                    'campaign_payload',
                    'recipient_payload',
                    'provider_payload',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $campaignAttribution
     * @return array<string, mixed>
     */
    protected function postIssuanceNavigation(GeneratePayCodeResultData $result, array $campaignAttribution = []): array
    {
        $detailHref = Route::has('x-change.cockpit.pay-codes.show')
            ? route('x-change.cockpit.pay-codes.show', ['code' => $result->code], false)
            : null;
        $distributionHref = Route::has('x-change.cockpit.pay-codes.distribution')
            ? route('x-change.cockpit.pay-codes.distribution', ['code' => $result->code], false)
            : null;
        $campaignExplorerHref = $this->campaignExplorerHref($campaignAttribution, $result);
        $campaignDashboardHref = $this->campaignDashboardHref($campaignAttribution);

        return [
            'schema' => 'x-change.cockpit.quick-generate-post-issuance-navigation.v1',
            'status' => 'available',
            'auto_redirect' => false,
            'items' => [
                [
                    'key' => 'detail',
                    'label' => 'Open Cockpit detail',
                    'href' => $detailHref,
                    'status' => $detailHref === null ? 'unavailable' : 'available',
                    'enabled' => $detailHref !== null,
                    'read_only' => true,
                    'reason' => 'Read-only voucher detail route for the generated Pay Code.',
                ],
                [
                    'key' => 'distribution',
                    'label' => 'Open Distribution workspace',
                    'href' => $distributionHref,
                    'status' => $distributionHref === null ? 'unavailable' : 'available',
                    'enabled' => $distributionHref !== null,
                    'read_only' => true,
                    'reason' => 'Read-only distribution/share workspace route for the generated Pay Code.',
                ],
                [
                    'key' => 'campaign_explorer',
                    'label' => 'Return to Campaign Explorer',
                    'href' => $campaignExplorerHref,
                    'status' => $campaignExplorerHref === null ? 'unavailable' : 'available',
                    'enabled' => $campaignExplorerHref !== null,
                    'read_only' => true,
                    'reason' => 'Read-only campaign-aware Explorer context for the generated Pay Code.',
                    'metadata' => $this->campaignNavigationMetadata($campaignAttribution),
                ],
                [
                    'key' => 'campaign_dashboard',
                    'label' => 'Return to Campaign Dashboard',
                    'href' => $campaignDashboardHref,
                    'status' => $campaignDashboardHref === null ? 'unavailable' : 'available',
                    'enabled' => $campaignDashboardHref !== null,
                    'read_only' => true,
                    'reason' => 'Read-only campaign-aware Dashboard context for the originating campaign.',
                    'metadata' => $this->campaignNavigationMetadata($campaignAttribution),
                ],
            ],
            'redactions' => [
                'payloads' => 'post-issuance-navigation-only',
                'excluded' => [
                    'request_payload',
                    'validated_payload',
                    'provider_payload',
                    'wallet',
                    'debit',
                    'allocations',
                    'cost',
                    'idempotency_key',
                    'raw_payload',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $campaignAttribution
     * @return array<string, mixed>
     */
    protected function campaignNavigationMetadata(array $campaignAttribution): array
    {
        return array_filter([
            'planning_key' => $campaignAttribution['planning_key'] ?? null,
            'execution_id' => $campaignAttribution['execution_id'] ?? null,
            'campaign_id' => $campaignAttribution['campaign_id'] ?? null,
            'audience_id' => $campaignAttribution['audience_id'] ?? null,
            'recipient_id' => $campaignAttribution['recipient_id'] ?? null,
            'source' => $campaignAttribution['source'] ?? null,
            'read_only' => true,
            'mutates_campaign' => false,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    protected function campaignExplorerHref(array $campaignAttribution, GeneratePayCodeResultData $result): ?string
    {
        if (($campaignAttribution['available'] ?? false) !== true || ! Route::has('x-change.cockpit.pay-codes.index')) {
            return null;
        }

        return route('x-change.cockpit.pay-codes.index', [
            'campaign_planning_key' => $campaignAttribution['planning_key'] ?? null,
            'campaign_execution_id' => $campaignAttribution['execution_id'] ?? null,
            'campaign_id' => $campaignAttribution['campaign_id'] ?? null,
            'campaign_audience_id' => $campaignAttribution['audience_id'] ?? null,
            'campaign_recipient_id' => $campaignAttribution['recipient_id'] ?? null,
            'campaign_source' => $campaignAttribution['source'] ?? 'campaign_cockpit',
            'activity_code' => $result->code,
            'activity_source' => 'cockpit.quick-generate',
            'search' => $result->code,
        ], false);
    }

    protected function campaignDashboardHref(array $campaignAttribution): ?string
    {
        if (($campaignAttribution['available'] ?? false) !== true || ! Route::has('x-change.cockpit.dashboard')) {
            return null;
        }

        return route('x-change.cockpit.dashboard', [
            'campaign_planning_key' => $campaignAttribution['planning_key'] ?? null,
            'campaign_execution_id' => $campaignAttribution['execution_id'] ?? null,
            'campaign_id' => $campaignAttribution['campaign_id'] ?? null,
            'campaign_audience_id' => $campaignAttribution['audience_id'] ?? null,
            'campaign_recipient_id' => $campaignAttribution['recipient_id'] ?? null,
        ], false);
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function processOperatorIssuanceActivity(
        GeneratePayCodeRequest $request,
        array $response,
        ?string $key,
        CockpitOperatorIssuanceActivityHandoffPipeline $operatorIssuanceActivityHandoffPipeline,
    ): void {
        $code = data_get($response, 'result.code');

        if (! is_string($code) || trim($code) === '') {
            return;
        }

        $correlationHeader = (string) config('x-change.api.correlation.header', 'X-Correlation-ID');
        $correlationId = $request->header($correlationHeader);
        $operatorId = $request->user()?->getAuthIdentifier();
        $detailHref = data_get($response, 'result.links.cockpit_detail');

        try {
            $operatorIssuanceActivityHandoffPipeline->process(new CockpitOperatorIssuanceActivityItemData(
                id: hash('sha256', implode('|', [
                    'cockpit.quick-generate',
                    $code,
                    (string) $key,
                    is_string($correlationId) ? $correlationId : '',
                    $operatorId !== null ? (string) $operatorId : '',
                ])),
                code: $code,
                amount: (string) data_get($response, 'result.amount'),
                currency: (string) data_get($response, 'result.currency'),
                status: (string) data_get($response, 'status', 'issued'),
                issued_at: now()->toIso8601String(),
                route: (string) data_get($response, 'route', 'x-change.cockpit.quick-generate.store'),
                correlation_id: is_string($correlationId) ? $correlationId : null,
                idempotency_key: $key,
                operator_id: $operatorId !== null ? (string) $operatorId : null,
                detail_href: is_string($detailHref) ? $detailHref : null,
                metadata: $this->operatorIssuanceActivityMetadata($response),
            ));
        } catch (Throwable) {
            //
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function operatorIssuanceActivityMetadata(array $response): array
    {
        $metadata = [
            'source' => 'x-change.cockpit',
            'presentation_only' => true,
            'recorder' => 'cockpit.operator-issuance-activity.v1',
            'draft_status' => data_get($response, 'draft.status'),
            'pricing_preflight_status' => data_get($response, 'preflight.pricing.status'),
            'funding_preflight_status' => data_get($response, 'preflight.funding.status'),
            'activity_schema' => data_get($response, 'activity.schema'),
        ];

        $campaignAttribution = $this->operatorSafeCampaignAttributionForActivity($response);

        if ($campaignAttribution !== []) {
            $metadata['campaign_attribution'] = $campaignAttribution;
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function operatorSafeCampaignAttributionForActivity(array $response): array
    {
        if (data_get($response, 'campaign_attribution.available') !== true) {
            return [];
        }

        return array_filter([
            'schema' => data_get($response, 'campaign_attribution.schema'),
            'status' => data_get($response, 'campaign_attribution.status'),
            'read_only' => true,
            'mutates_campaign' => false,
            'planning_key' => data_get($response, 'campaign_attribution.planning_key'),
            'execution_id' => data_get($response, 'campaign_attribution.execution_id'),
            'campaign_id' => data_get($response, 'campaign_attribution.campaign_id'),
            'audience_id' => data_get($response, 'campaign_attribution.audience_id'),
            'recipient_id' => data_get($response, 'campaign_attribution.recipient_id'),
            'source' => data_get($response, 'campaign_attribution.source'),
            'generated_code' => data_get($response, 'campaign_attribution.generated_code'),
            'template_key' => data_get($response, 'campaign_attribution.template_key'),
            'amount' => data_get($response, 'campaign_attribution.amount'),
            'currency' => data_get($response, 'campaign_attribution.currency'),
            'recipient_reference' => data_get($response, 'campaign_attribution.recipient_reference'),
            'purpose' => data_get($response, 'campaign_attribution.purpose'),
            'redactions' => [
                'payloads' => 'campaign-attribution-only',
            ],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
