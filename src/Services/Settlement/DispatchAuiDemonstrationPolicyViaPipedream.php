<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use DomainException;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyResponseData;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyTransportRequestData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use ThreeNeti\SettlementEnvelopeAui\Data\AuiPolicySubmission;
use ThreeNeti\SettlementEnvelopeAui\Services\AuiPolicyTransport;
use ThreeNeti\SettlementEnvelopeAui\Services\ParseAuiPolicyResponse;

final readonly class DispatchAuiDemonstrationPolicyViaPipedream
{
    public function __construct(
        private PolicyCompletionTransportDispositionCatalog $dispositions,
        private ParseAuiDemonstrationPolicyResponse $responses = new ParseAuiDemonstrationPolicyResponse,
        private ?AuiPolicyTransport $transport = null,
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

        $transport = $this->transport ?? new AuiPolicyTransport(Http::getFacadeRoot(), new ParseAuiPolicyResponse);
        $result = $transport->submit(
            new AuiPolicySubmission((new AuiDemonstrationPolicyTransportRequestData($preparation))->toArray()),
            $disposition->submissionEndpoint(),
            trim($credential),
            $disposition->connectTimeoutSeconds(),
            $disposition->responseTimeoutSeconds(),
        );

        return $this->responses->handle($result->toArray(), $preparation);
    }

    private function isPipedreamEndpoint(string $endpoint): bool
    {
        $host = parse_url($endpoint, PHP_URL_HOST);

        return is_string($host)
            && ($host === 'pipedream.net' || str_ends_with($host, '.pipedream.net'));
    }
}
