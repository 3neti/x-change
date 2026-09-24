<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyResponseData;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyTransportRequestData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;

final readonly class DispatchAuiDemonstrationPolicyViaPipedream
{
    public function __construct(
        private PolicyCompletionTransportDispositionCatalog $dispositions,
    ) {}

    public function handle(PolicyCompletionPreparationData $preparation): AuiDemonstrationPolicyResponseData
    {
        if ($preparation->driverId !== AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID
            || $preparation->driverVersion !== AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION) {
            throw new DomainException('The Pipedream demonstration adapter only accepts the reserved AUI driver.');
        }

        $disposition = $this->dispositions->for($preparation->driverId, $preparation->driverVersion);
        if ($disposition === null
            || $disposition->provider() !== 'pipedream-test'
            || $disposition->authenticationScheme() !== 'bearer-token'
            || ! $this->isPipedreamEndpoint($disposition->submissionEndpoint())) {
            throw new DomainException('An accepted Pipedream test transport disposition is required.');
        }

        $credential = config($disposition->credentialReference());
        if (! is_string($credential) || trim($credential) === '') {
            throw new DomainException('The Pipedream test transport credential is unavailable.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken(trim($credential))
            ->withHeaders([
                'Idempotency-Key' => $preparation->idempotencyKey,
                'X-XChange-Preparation-Fingerprint' => $preparation->fingerprint,
            ])
            ->connectTimeout($disposition->connectTimeoutSeconds())
            ->timeout($disposition->responseTimeoutSeconds())
            ->post(
                $disposition->submissionEndpoint(),
                (new AuiDemonstrationPolicyTransportRequestData($preparation))->toArray(),
            );

        if (! $response->successful()) {
            throw new DomainException('The Pipedream test transport did not return a successful response.');
        }

        return $this->response($response->json(), $preparation);
    }

    private function isPipedreamEndpoint(string $endpoint): bool
    {
        $host = parse_url($endpoint, PHP_URL_HOST);

        return is_string($host)
            && ($host === 'pipedream.net' || str_ends_with($host, '.pipedream.net'));
    }

    private function response(mixed $payload, PolicyCompletionPreparationData $preparation): AuiDemonstrationPolicyResponseData
    {
        if (! is_array($payload)
            || ($payload['schema'] ?? null) !== AuiDemonstrationPolicyResponseData::SCHEMA
            || ($payload['status'] ?? null) !== 'issued_demo'
            || ($payload['demonstration_only'] ?? null) !== true
            || ($payload['document_ready'] ?? null) !== false
            || ($payload['product_code'] ?? null) !== 'AUI-PA-DEMO'
            || ! is_string($payload['policy_reference'] ?? null)
            || ! str_starts_with($payload['policy_reference'], 'AUI-DEMO-')) {
            throw new DomainException('The Pipedream test transport returned an invalid demonstration response.');
        }

        try {
            $effectiveAt = CarbonImmutable::parse((string) ($payload['effective_at'] ?? ''));
            $expiresAt = isset($payload['expires_at'])
                ? CarbonImmutable::parse((string) $payload['expires_at'])
                : null;
        } catch (\Throwable) {
            throw new DomainException('The Pipedream test transport returned invalid coverage timestamps.');
        }

        if (! $effectiveAt->equalTo($preparation->coverageEffectiveAt)
            || ! $this->sameTimestamp($expiresAt, $preparation->coverageExpiresAt)) {
            throw new DomainException('The Pipedream test transport changed the authoritative coverage period.');
        }

        return new AuiDemonstrationPolicyResponseData(
            policyReference: $payload['policy_reference'],
            productCode: $payload['product_code'],
            effectiveAt: $effectiveAt,
            expiresAt: $expiresAt,
        );
    }

    private function sameTimestamp(?CarbonImmutable $left, ?CarbonImmutable $right): bool
    {
        return $left === null && $right === null
            || ($left !== null && $right !== null && $left->equalTo($right));
    }
}
