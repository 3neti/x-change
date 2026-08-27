<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Exceptions\ExternalReferenceConflict;
use LBHurtado\XChange\Http\Requests\PartnerApi\IssuePartnerPayCodeRequest;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\PartnerApiPayCodeReference;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\IdempotencyService;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiMandateGuard;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\PartnerApi\PartnerPayCodeReferenceService;
use LBHurtado\XChange\Services\VoucherConsumerStatusResolver;
use LogicException;

class IssuePartnerPayCodeController extends Controller
{
    public function __invoke(
        IssuePartnerPayCodeRequest $request,
        PartnerApiRequestContext $context,
        PartnerApiMandateGuard $mandates,
        GeneratePayCode $issue,
        IdempotencyService $idempotency,
        PartnerPayCodeReferenceService $references,
        VoucherConsumerStatusResolver $consumerStatuses,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $payload = $request->validated();
        $externalReference = (string) Arr::pull($payload, 'external_reference');
        $headers = (array) Arr::pull($payload, '_partner', []);
        $key = (string) data_get($headers, 'idempotency_key');
        data_set($payload, 'metadata.issuer_id', (string) $context->issuer()->getKey());
        data_set($payload, 'metadata.custom.external_reference', $externalReference);
        data_set($payload, '_meta.idempotency_key', $key);
        data_set($payload, '_meta.correlation_id', data_get($headers, 'correlation_id'));
        $transportPayload = $payload;
        data_forget($transportPayload, 'metadata.custom.external_reference');

        $outcome = DB::transaction(function () use (
            $context,
            $mandates,
            $payload,
            $transportPayload,
            $externalReference,
            $key,
            $idempotency,
            $issue,
            $headers,
            $references,
            $consumerStatuses,
        ): array {
            $client = PartnerApiClient::query()->lockForUpdate()->findOrFail($context->client()->getKey());
            $context->setClient($client);
            $mandates->assertAllows($payload, $context);
            $namespace = sprintf('partner-api:%s:pay-codes:issue', $client->reference);
            $termsHash = $references->termsHash($payload);
            $businessReference = PartnerApiPayCodeReference::query()
                ->whereBelongsTo($client, 'client')
                ->where('external_reference', $externalReference)
                ->lockForUpdate()
                ->first();

            if (
                $businessReference instanceof PartnerApiPayCodeReference
                && ! hash_equals($businessReference->terms_hash, $termsHash)
            ) {
                throw new ExternalReferenceConflict(
                    'The external reference is already bound to different Pay Code terms.',
                );
            }

            $recalled = $idempotency->recallOrValidate($key, $transportPayload, $namespace);

            if (is_array($recalled)) {
                return ['data' => $recalled, 'replayed' => true];
            }

            if ($businessReference instanceof PartnerApiPayCodeReference) {
                $voucher = $businessReference->voucher()->firstOrFail();
                $data = $this->replayedIssuanceData($client, $voucher);
                $data['external_reference'] = $references->externalReference($voucher);
                $data['consumer_status'] = $consumerStatuses->resolve($voucher);
                $idempotency->remember($key, $transportPayload, $data, $namespace);

                return ['data' => $data, 'replayed' => true];
            }

            $currency = strtoupper((string) data_get($payload, 'cash.currency'));
            $principalMinor = $mandates->principalMinor($payload);
            $mandates->assertDailyPrincipalAvailable($principalMinor, $context);
            $result = $issue->handle($payload);
            $data = $result->toArray();
            $voucher = Voucher::query()
                ->whereKey(data_get($data, 'voucher_id'))
                ->where('owner_type', $context->issuer()->getMorphClass())
                ->where('owner_id', (string) $context->issuer()->getKey())
                ->firstOrFail();
            $persistedReference = $references->externalReference($voucher);

            if ($persistedReference !== $externalReference) {
                throw new LogicException('Issued Pay Code did not preserve its external reference.');
            }

            $data['external_reference'] = $persistedReference;
            $data['consumer_status'] = $consumerStatuses->resolve($voucher);

            PartnerApiPayCodeReference::query()->create([
                'partner_api_client_id' => $client->getKey(),
                'external_reference' => $externalReference,
                'voucher_id' => $voucher->getKey(),
                'terms_hash' => $termsHash,
            ]);

            PartnerApiOperation::query()->create([
                'partner_api_client_id' => $client->getKey(),
                'operation' => 'pay_code_issued',
                'idempotency_key' => $key,
                'correlation_id' => data_get($headers, 'correlation_id'),
                'subject_reference' => data_get($data, 'code'),
                'principal_minor' => $principalMinor,
                'currency' => $currency,
                'response_snapshot' => $data,
                'occurred_at' => now(),
            ]);
            $idempotency->remember($key, $transportPayload, $data, $namespace);

            return ['data' => $data, 'replayed' => false];
        }, attempts: 5);

        $correlationId = data_get($headers, 'correlation_id');
        $response = $responses->success(
            $outcome['data'],
            [
                'idempotency' => ['key' => $key, 'replayed' => $outcome['replayed']],
                'correlation_id' => $correlationId,
            ],
            $outcome['replayed'] ? 200 : 201,
        );

        if (is_string($correlationId) && $correlationId !== '') {
            $response->headers->set(
                (string) config('x-change.api.correlation.header', 'X-Correlation-ID'),
                $correlationId,
            );
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function replayedIssuanceData(PartnerApiClient $client, Voucher $voucher): array
    {
        $snapshot = PartnerApiOperation::query()
            ->whereBelongsTo($client, 'client')
            ->where('operation', 'pay_code_issued')
            ->where('subject_reference', (string) $voucher->code)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->value('response_snapshot');

        if (! is_array($snapshot)) {
            throw new LogicException('Partner API issuance evidence has no replayable response snapshot.');
        }

        return $snapshot;
    }
}
