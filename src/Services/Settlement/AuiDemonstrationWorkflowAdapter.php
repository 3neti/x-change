<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use DomainException;
use Illuminate\Contracts\Config\Repository;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowIntegrationAdapter;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowIntegrationResult;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowSubmission;
use LBHurtado\SettlementEnvelope\Services\WorkflowConnectionReadiness;
use LBHurtado\XChange\Data\Settlement\AuiWorkflowIntegrationResultData;
use LBHurtado\XChange\Data\Settlement\AuiWorkflowSubmissionData;

final readonly class AuiDemonstrationWorkflowAdapter implements WorkflowIntegrationAdapter
{
    public const CONNECTION = 'aui-demo';

    public function __construct(
        private DispatchAuiDemonstrationPolicyViaPipedream $transport,
        private PolicyCompletionTransportDispositionCatalog $dispositions,
        private WorkflowConnectionReadiness $readiness,
        private Repository $config,
    ) {}

    public function workflowId(): string
    {
        return AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID;
    }

    public function workflowVersion(): string
    {
        return AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION;
    }

    public function submit(WorkflowSubmission $submission): WorkflowIntegrationResult
    {
        if (! $submission instanceof AuiWorkflowSubmissionData
            || $submission->workflowId() !== $this->workflowId()
            || $submission->workflowVersion() !== $this->workflowVersion()) {
            throw new DomainException('The AUI adapter requires its exact prepared submission.');
        }

        $disposition = $this->dispositions->for($this->workflowId(), $this->workflowVersion());
        $connection = $this->config->get('settlement-envelope.connections.'.self::CONNECTION);
        if (! $this->readiness->check(self::CONNECTION)->configured
            || $disposition === null
            || ! is_array($connection)
            || $connection['base_url'] !== $disposition->submissionEndpoint()
            || $connection['connect_timeout'] !== $disposition->connectTimeoutSeconds()
            || $connection['timeout'] !== $disposition->responseTimeoutSeconds()
            || ($connection['auth']['type'] ?? null) !== 'bearer'
            || ! is_string($credential = $this->config->get($disposition->credentialReference()))
            || ! hash_equals(trim($credential), trim($connection['auth']['token']))) {
            throw new DomainException('The named AUI connection must match the accepted transport disposition.');
        }

        return new AuiWorkflowIntegrationResultData($this->transport->handle($submission->preparation()));
    }
}
