<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Exceptions\IdempotencyConflict;
use LBHurtado\XChange\Exceptions\VoucherCannotCollect;
use LBHurtado\XChange\Http\Requests\PartnerApi\CreatePartnerPayCodePaymentAttemptRequest;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\IdempotencyService;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\Payment\PayCodePaymentLinkResolver;
use LBHurtado\XChange\Services\Payment\PaymentAttemptPresenter;
use LBHurtado\XChange\Services\VoucherCapabilityGuard;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use RuntimeException;

class CreatePartnerPayCodePaymentAttemptController extends Controller
{
    public function __invoke(
        string $code,
        CreatePartnerPayCodePaymentAttemptRequest $request,
        PartnerApiRequestContext $context,
        VoucherCapabilityGuard $capabilityGuard,
        VoucherFlowCapabilityResolverContract $capabilities,
        VoucherCollectionProgressService $progress,
        CreatePaymentAttempt $createAttempt,
        IssuePaymentInstructions $issueInstructions,
        PaymentAttemptPresenter $presenter,
        PayCodePaymentLinkResolver $paymentLinks,
        IdempotencyService $idempotency,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $payload = $request->validated();
        $headers = (array) Arr::pull($payload, '_partner', []);
        $key = (string) data_get($headers, 'idempotency_key');
        $correlationId = data_get($headers, 'correlation_id');
        $provider = (string) data_get($payload, 'provider');
        $client = $context->client();
        $voucher = $this->ownedVoucher($code, $context);
        $fingerprintPayload = [
            'code' => strtoupper((string) $voucher->code),
            'provider' => $provider,
        ];
        $requestHash = hash('sha256', json_encode($fingerprintPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $namespace = sprintf('partner-api:%s:pay-codes:pay', $client->reference);
        $recalled = $this->recall($client, $key, $requestHash, $fingerprintPayload, $namespace, $idempotency);

        if ($recalled !== null) {
            return $this->response($responses, $recalled, $key, $correlationId, true, 200);
        }

        $capabilityGuard->ensureCanCollect($voucher);

        if ($progress->compute($voucher)->remaining_to_collect_minor <= 0) {
            throw new VoucherCannotCollect(
                voucher: $voucher,
                capabilities: $capabilities->resolve($voucher),
                message: 'This Pay Code is already fully paid.',
            );
        }

        $attempt = $createAttempt->handle(
            voucher: $voucher,
            provider: $provider,
            browserKey: sprintf('partner-api:%s:%s', $client->reference, strtoupper((string) $voucher->code)),
            idempotencyKey: $key,
        );

        try {
            $attempt = $issueInstructions->handle($attempt);
        } catch (RuntimeException) {
            $response = $responses->error(
                'Payment instructions are temporarily unavailable.',
                'PAYMENT_INSTRUCTIONS_UNAVAILABLE',
                [],
                503,
            );

            if (is_string($correlationId) && $correlationId !== '') {
                $response->headers->set(
                    (string) config('x-change.api.correlation.header', 'X-Correlation-ID'),
                    $correlationId,
                );
            }

            return $response;
        }

        $data = [
            'schema' => 'x-change.partner-payment-attempt.v1',
            'code' => strtoupper((string) $voucher->code),
            'pay_url' => $paymentLinks->forVoucher($voucher)['pay'],
            'attempt' => $presenter->present($attempt),
        ];

        $outcome = DB::transaction(function () use (
            $client,
            $key,
            $requestHash,
            $fingerprintPayload,
            $namespace,
            $idempotency,
            $data,
            $correlationId,
            $attempt,
            $voucher,
        ): array {
            $lockedClient = PartnerApiClient::query()->lockForUpdate()->findOrFail($client->getKey());
            $existing = $this->existingOperation($lockedClient, $key);

            if ($existing instanceof PartnerApiOperation) {
                $this->assertRequestHash($existing, $requestHash);

                return ['data' => (array) $existing->response_snapshot, 'replayed' => true];
            }

            PartnerApiOperation::query()->create([
                'partner_api_client_id' => $lockedClient->getKey(),
                'operation' => 'payment_attempt_created',
                'idempotency_key' => $key,
                'correlation_id' => $correlationId,
                'subject_reference' => strtoupper((string) $voucher->code),
                'principal_minor' => 0,
                'currency' => $attempt->currency,
                'request_hash' => $requestHash,
                'response_snapshot' => $data,
                'occurred_at' => now(),
            ]);
            $idempotency->remember($key, $fingerprintPayload, $data, $namespace);

            return ['data' => $data, 'replayed' => false];
        }, attempts: 5);

        return $this->response(
            $responses,
            $outcome['data'],
            $key,
            $correlationId,
            $outcome['replayed'],
            $outcome['replayed'] ? 200 : 201,
        );
    }

    private function ownedVoucher(string $code, PartnerApiRequestContext $context): Voucher
    {
        $issuer = $context->issuer();

        return Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->where('owner_type', $issuer->getMorphClass())
            ->where('owner_id', (string) $issuer->getKey())
            ->firstOrFail();
    }

    /** @param array<string, mixed> $payload */
    private function recall(
        PartnerApiClient $client,
        string $key,
        string $requestHash,
        array $payload,
        string $namespace,
        IdempotencyService $idempotency,
    ): ?array {
        return DB::transaction(function () use ($client, $key, $requestHash, $payload, $namespace, $idempotency): ?array {
            $lockedClient = PartnerApiClient::query()->lockForUpdate()->findOrFail($client->getKey());
            $existing = $this->existingOperation($lockedClient, $key);

            if ($existing instanceof PartnerApiOperation) {
                $this->assertRequestHash($existing, $requestHash);

                return (array) $existing->response_snapshot;
            }

            $recalled = $idempotency->recallOrValidate($key, $payload, $namespace);

            return is_array($recalled) ? $recalled : null;
        }, attempts: 5);
    }

    private function existingOperation(PartnerApiClient $client, string $key): ?PartnerApiOperation
    {
        return PartnerApiOperation::query()
            ->where('partner_api_client_id', $client->getKey())
            ->where('operation', 'payment_attempt_created')
            ->where('idempotency_key', $key)
            ->first();
    }

    private function assertRequestHash(PartnerApiOperation $operation, string $requestHash): void
    {
        if (! is_string($operation->request_hash) || ! hash_equals($operation->request_hash, $requestHash)) {
            throw new IdempotencyConflict('The idempotency key was reused with different request data.');
        }
    }

    private function response(
        ApiResponseFactory $responses,
        array $data,
        string $key,
        mixed $correlationId,
        bool $replayed,
        int $status,
    ): JsonResponse {
        $response = $responses->success($data, [
            'idempotency' => ['key' => $key, 'replayed' => $replayed],
            'correlation_id' => $correlationId,
        ], $status);

        if (is_string($correlationId) && $correlationId !== '') {
            $response->headers->set(
                (string) config('x-change.api.correlation.header', 'X-Correlation-ID'),
                $correlationId,
            );
        }

        return $response;
    }
}
