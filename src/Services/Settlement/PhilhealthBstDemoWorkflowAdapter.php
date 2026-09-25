<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use DomainException;
use Illuminate\Contracts\Foundation\Application;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowIntegrationAdapter;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowIntegrationResult;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowSubmission;
use LBHurtado\XChange\Data\Settlement\PhilhealthBstDemoResultData;
use LBHurtado\XChange\Data\Settlement\PhilhealthBstDemoSubmissionData;
use ThreeNeti\SettlementEnvelopePhilhealth\Services\PhilhealthBstDemoWorkflowAdapter as IntegrationAdapter;

/** Local characterization only. Does not approve, persist, dispatch or pay a claim. */
final readonly class PhilhealthBstDemoWorkflowAdapter implements WorkflowIntegrationAdapter
{
    public function __construct(private Application $app) {}

    public function workflowId(): string
    {
        return 'philhealth.bst.demo';
    }

    public function workflowVersion(): string
    {
        return '1.0.0';
    }

    public function submit(WorkflowSubmission $submission): WorkflowIntegrationResult
    {
        if (! $this->app->environment('local', 'testing')) {
            throw new DomainException('The synthetic BST adapter is restricted to local testing.');
        }
        if (! $submission instanceof PhilhealthBstDemoSubmissionData) {
            throw new DomainException('The BST adapter requires its synthetic intake submission.');
        }

        $result = (new IntegrationAdapter($this->app))->submit($submission->integrationSubmission());

        return new PhilhealthBstDemoResultData($result->reference());
    }
}
