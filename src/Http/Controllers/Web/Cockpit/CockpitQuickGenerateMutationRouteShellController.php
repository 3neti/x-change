<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

class CockpitQuickGenerateMutationRouteShellController extends Controller
{
    public function __invoke(GeneratePayCodeRequest $request, GeneratePayCode $generatePayCode): JsonResponse
    {
        $payload = $request->validated();
        $correlationId = $request->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID'));
        $issuerId = $request->user()?->getAuthIdentifier();

        $payload['_meta'] = [
            'correlation_id' => is_string($correlationId) ? $correlationId : null,
            'source' => 'cockpit.quick-generate',
        ];

        if ($issuerId !== null) {
            data_set($payload, 'metadata.issuer_id', (string) $issuerId);
        }

        $result = $generatePayCode->handle($payload);

        return response()->json([
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
                'status' => 'deferred-to-wave-1d',
                'persisted' => false,
                'fingerprinted' => false,
                'replay_checked' => false,
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
            'next_step' => 'Cockpit Mutation Wave 1D — Idempotency and Replay Contract',
        ], 201);
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
            ],
        ];
    }
}
