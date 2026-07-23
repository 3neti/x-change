<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Funding;

use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\EmiCore\Actions\Funding\StoreProviderWebhookReceipt;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\XChange\Exceptions\FundingProviderUnavailable;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;

class ReceiveFundingWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        FundingProviderAdapterRegistry $providers,
        StoreProviderWebhookReceipt $storeReceipt,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $rawBody = $request->getContent();
        $maximumBodyBytes = (int) config('x-change.funding.webhook_max_body_bytes', 262_144);

        if ($maximumBodyBytes <= 0 || strlen($rawBody) > $maximumBodyBytes) {
            return $responses->error(
                'Funding webhook payload is too large.',
                'FUNDING_WEBHOOK_PAYLOAD_TOO_LARGE',
                [],
                413,
            );
        }

        $provider = strtolower(trim($provider));
        try {
            $adapter = $providers->for($provider);
        } catch (FundingProviderUnavailable $exception) {
            return $responses->errorFromThrowable(
                $exception,
                'FUNDING_PROVIDER_UNAVAILABLE',
                [],
                503,
            );
        }
        $providerRequest = new ProviderWebhookRequestData(
            provider: $provider,
            rawBody: $rawBody,
            contentType: $request->header('Content-Type'),
            headers: $request->headers->all(),
            sourceIp: $request->ip(),
            receivedAt: DateTimeImmutable::createFromInterface(now()),
            signature: $this->signature($request),
        );
        $authentication = $adapter->authenticateWebhook($providerRequest);
        $event = $authentication->authenticated
            ? $adapter->normalizeWebhook(ProviderWebhookReceiptData::fromRequest(
                $providerRequest,
                $authentication,
            ))
            : null;
        $receipt = $storeReceipt->handle($providerRequest, $authentication, $event);

        if (! $authentication->authenticated || ! $receipt->signature_verified) {
            return $responses->error(
                'Funding webhook authentication failed.',
                'FUNDING_WEBHOOK_AUTHENTICATION_FAILED',
                [],
                401,
            );
        }

        return $responses->success([
            'acknowledgement' => 'accepted',
            'provider' => $provider,
        ], [
            'balance_changed' => false,
            'verification_queued' => false,
        ], 202);
    }

    private function signature(Request $request): ?string
    {
        $header = config('x-change.funding.providers.'.strtolower((string) $request->route('provider')).'.signature_header');

        if (! is_string($header) || trim($header) === '') {
            return null;
        }

        $signature = $request->header($header);

        return is_string($signature) && trim($signature) !== ''
            ? trim($signature)
            : null;
    }
}
