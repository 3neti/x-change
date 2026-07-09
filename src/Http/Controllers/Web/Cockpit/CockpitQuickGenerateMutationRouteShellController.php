<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\IdempotencyService;
use Throwable;

class CockpitQuickGenerateMutationRouteShellController extends Controller
{
    public function __invoke(
        GeneratePayCodeRequest $request,
        GeneratePayCode $generatePayCode,
        IdempotencyService $idempotency,
        CockpitOperatorIssuanceActivityRecorderContract $activityRecorder,
    ): JsonResponse {
        $payload = $request->validated();
        $payload = $this->normalizePayloadForIssuance($payload);
        $key = $idempotency->extractKey($request);
        $correlationId = $request->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID'));
        $issuerId = $request->user()?->getAuthIdentifier();

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

        $result = $generatePayCode->handle($payload);
        $response = $this->responsePayload($request, $result, $key, false);
        $this->recordOperatorIssuanceActivity($request, $response, $key, $activityRecorder);

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
    protected function responsePayload(GeneratePayCodeRequest $request, GeneratePayCodeResultData $result, ?string $key, bool $replayed): array
    {
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
            ],
            'handoff' => [
                'status' => 'executed',
                'target' => 'GeneratePayCode',
                'controller' => 'GeneratePayCodeController',
                'executed' => true,
                'controller_invoked' => false,
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
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function recordOperatorIssuanceActivity(
        GeneratePayCodeRequest $request,
        array $response,
        ?string $key,
        CockpitOperatorIssuanceActivityRecorderContract $activityRecorder,
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
            $activityRecorder->record(new CockpitOperatorIssuanceActivityItemData(
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
                metadata: [
                    'source' => 'x-change.cockpit',
                    'presentation_only' => true,
                    'recorder' => 'cockpit.operator-issuance-activity.v1',
                ],
            ));
        } catch (Throwable) {
            //
        }
    }
}
