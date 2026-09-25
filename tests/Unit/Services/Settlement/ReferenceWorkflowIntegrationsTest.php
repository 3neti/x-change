<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Composer\InstalledVersions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowAccessPolicy;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowCatalog;
use LBHurtado\SettlementEnvelope\Data\WorkflowContext;
use LBHurtado\SettlementEnvelope\Enums\WorkflowIntegrationStatus;
use LBHurtado\SettlementEnvelope\Exceptions\DriverNotFoundException;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyResponseData;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyTransportRequestData;
use LBHurtado\XChange\Data\Settlement\AuiWorkflowSubmissionData;
use LBHurtado\XChange\Data\Settlement\PhilhealthBstDemoSubmissionData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use LBHurtado\XChange\Models\PolicyCompletionOutcome;
use LBHurtado\XChange\Services\Settlement\AuiDemonstrationWorkflowAdapter;
use LBHurtado\XChange\Services\Settlement\AuiPersonalAccidentPolicyCompletionDriver;
use LBHurtado\XChange\Services\Settlement\PhilhealthBstDemoWorkflowAdapter;
use LBHurtado\XChange\Services\Settlement\ReferenceWorkflowIntegrations;
use ThreeNeti\SettlementEnvelopeAui\AuiResources;
use ThreeNeti\SettlementEnvelopePhilhealth\WorkflowAssets;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('integration-drivers');
    config()->set('settlement-envelope.driver_disk', 'integration-drivers');
    app()->forgetInstance(DriverService::class);
    $this->context = new WorkflowContext('actor-demo', 'account-demo');
});

it('consumes standalone integration resources through Composer without resource drift', function (): void {
    foreach (['3neti/settlement-envelope', '3neti/settlement-envelope-aui', '3neti/settlement-envelope-philhealth'] as $package) {
        expect(InstalledVersions::isInstalled($package))->toBeTrue();
    }
    $root = dirname(__DIR__, 4);
    expect(file_get_contents(AuiResources::driverPath()))
        ->toBe(file_get_contents($root.'/config/envelope-drivers/aui.personal-accident.provisional-cover.yaml'))
        ->and(file_get_contents(WorkflowAssets::driverPath()))
        ->toBe(file_get_contents($root.'/config/envelope-drivers/philhealth.bst.demo.yaml'))
        ->and(file_get_contents(AuiResources::requestSchemaPath()))
        ->toBe(file_get_contents($root.'/resources/policy-completion-contracts/aui-demonstration-policy-transport-request-v1.schema.json'));
    Http::assertNothingSent();
});

it('discovers both reference definitions without activating either integration', function (): void {
    expect(Storage::disk('integration-drivers')->allFiles())->toBe([]);
    expect(app(WorkflowCatalog::class)->available($this->context))->toBe([]);
    allowReferenceWorkflows();
    configureReferenceAuiConnection();
    $catalog = app(WorkflowCatalog::class);
    $aui = $catalog->resolve(AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID, '1.0.0', $this->context);
    $bst = $catalog->resolve('philhealth.bst.demo', '1.0.0', $this->context);
    expect($aui->workflow->plans[0]->premium_minor)->toBe(5000)
        ->and($aui->workflow->plans[0]->benefit_minor)->toBe(500000)
        ->and($aui->workflow->requires_review)->toBeFalse()
        ->and($bst->workflow->requires_review)->toBeTrue()
        ->and($bst->documents)->toHaveCount(2)
        ->and($bst->gates)->toContain('settleable');
    $registry = app(ReferenceWorkflowIntegrations::class)->registry();
    expect($registry->resolve($aui->id, $aui->version, $this->context))->toBeInstanceOf(AuiDemonstrationWorkflowAdapter::class)
        ->and($registry->resolve($bst->id, $bst->version, $this->context))->toBeInstanceOf(PhilhealthBstDemoWorkflowAdapter::class)
        ->and(fn () => $registry->resolve($aui->id, '9.0.0', $this->context))->toThrow(DriverNotFoundException::class)
        ->and(fn () => $registry->resolve($bst->id, $bst->version, new WorkflowContext('actor-other', 'account-other')))->toThrow(DriverNotFoundException::class);
    Http::assertNothingSent();
});

it('uses the existing AUI wire contract and result with no local financial outcome', function (): void {
    configureReferenceAuiConnection();
    $submission = referenceAuiSubmission();
    Http::fake(['https://demo.m.pipedream.net/*' => Http::response(referenceAuiResponse())]);
    $before = PolicyCompletionOutcome::query()->count();
    $result = app(AuiDemonstrationWorkflowAdapter::class)->submit($submission);
    expect($result->status())->toBe(WorkflowIntegrationStatus::Completed)
        ->and($result->demonstrationOnly())->toBeTrue()
        ->and($result->reference())->toBe('AUI-DEMO-0123456789ABCDEF')
        ->and($result->response()->outcome()->resultCode)->toBe('policy_issued_demo')
        ->and(PolicyCompletionOutcome::query()->count())->toBe($before);
    Http::assertSentCount(1);
    [$request] = Http::recorded()->sole();
    expect($request->data())->toBe((new AuiDemonstrationPolicyTransportRequestData($submission->preparation()))->toArray())
        ->and($request->header('Idempotency-Key'))->toBe([$submission->idempotencyKey()])
        ->and($request->header('X-XChange-Preparation-Fingerprint'))->toBe([$submission->fingerprint()])
        ->and($request->body())->not->toContain('Synthetic Applicant');
});

it('rejects named connection drift before any AUI request', function (string $field, mixed $value): void {
    configureReferenceAuiConnection();
    config()->set('settlement-envelope.connections.aui-demo.'.$field, $value);
    expect(fn () => app(AuiDemonstrationWorkflowAdapter::class)->submit(referenceAuiSubmission()))
        ->toThrow(DomainException::class, 'named AUI connection');
    Http::assertNothingSent();
})->with([
    ['base_url', 'https://another.m.pipedream.net/policy-completion'],
    ['auth.token', 'different-test-token'], ['timeout', 9], ['connect_timeout', 4],
    ['auth.type', 'none'], ['base_url', 'http://demo.m.pipedream.net/policy-completion'],
]);

it('does not treat a configured connection as accepted provider authority', function (): void {
    configureReferenceAuiConnection();
    config()->set('x-change.settlement.policy_completion.transports', []);
    expect(fn () => app(AuiDemonstrationWorkflowAdapter::class)->submit(referenceAuiSubmission()))
        ->toThrow(DomainException::class, 'accepted transport disposition');
    Http::assertNothingSent();
});

it('rejects unstable preparation identities and unsupported versions before HTTP', function (array $changes): void {
    $preparation = referenceAuiSubmission()->preparation();
    $values = array_replace(get_object_vars($preparation), [
        'applicantEvidence' => $preparation->privateApplicantEvidence(),
    ], $changes);
    expect(function () use ($values) {
        $submission = new AuiWorkflowSubmissionData(new PolicyCompletionPreparationData(...$values));
        app(AuiDemonstrationWorkflowAdapter::class)->submit($submission);
    })->toThrow(DomainException::class);
    Http::assertNothingSent();
})->with([
    [['idempotencyKey' => '']], [['fingerprint' => 'invalid']], [['driverVersion' => '1.0.1']],
]);

it('preserves fail-closed AUI response checks', function (array $changes): void {
    configureReferenceAuiConnection();
    Http::fake(['https://demo.m.pipedream.net/*' => Http::response(array_replace(referenceAuiResponse(), $changes))]);
    expect(fn () => app(AuiDemonstrationWorkflowAdapter::class)->submit(referenceAuiSubmission()))->toThrow(DomainException::class);
    Http::assertSentCount(1);
})->with([
    [['demonstration_only' => false]], [['document_ready' => true]], [['status' => 'issued']],
    [['product_code' => 'OTHER']], [['effective_at' => '2026-09-26T00:00:00+00:00']],
    [['expires_at' => null]], [['schema' => 'unknown']],
]);

it('does not retry an ambiguous AUI transport failure', function (): void {
    configureReferenceAuiConnection();
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;
        throw new ConnectionException('Synthetic timeout');
    });
    expect(fn () => app(AuiDemonstrationWorkflowAdapter::class)->submit(referenceAuiSubmission()))->toThrow(ConnectionException::class)
        ->and($attempts)->toBe(1);
});

it('rejects an unsuccessful or non-JSON AUI response without retrying', function (mixed $body, int $status): void {
    configureReferenceAuiConnection();
    Http::fake(['https://demo.m.pipedream.net/*' => Http::response($body, $status)]);
    expect(fn () => app(AuiDemonstrationWorkflowAdapter::class)->submit(referenceAuiSubmission()))->toThrow(DomainException::class);
    Http::assertSentCount(1);
})->with([
    [[], 500], ['not-json', 200],
]);

it('rejects a missing named AUI connection before HTTP', function (): void {
    configureReferenceAuiConnection();
    config()->set('settlement-envelope.connections', []);
    expect(fn () => app(AuiDemonstrationWorkflowAdapter::class)->submit(referenceAuiSubmission()))->toThrow(DomainException::class);
    Http::assertNothingSent();
});

it('keeps BST synthetic submission pending review without transmitting or persisting evidence', function (): void {
    $submission = referenceBstSubmission();
    $adapter = app(PhilhealthBstDemoWorkflowAdapter::class);
    $before = Envelope::query()->count();
    $first = $adapter->submit($submission);
    $replay = $adapter->submit($submission);
    expect($first->status())->toBe(WorkflowIntegrationStatus::AwaitingReview)
        ->and($first->demonstrationOnly())->toBeTrue()
        ->and($first->reference())->toBe($replay->reference())
        ->and($submission->fingerprint())->not->toBe(referenceBstSubmission(20000)->fingerprint())
        ->and(Envelope::query()->count())->toBe($before)
        ->and(json_encode($first))->not->toContain('Synthetic Applicant');
    Http::assertNothingSent();
});

it('does not permit BST simulation in production or cross-workflow submissions', function (): void {
    expect(fn () => app(PhilhealthBstDemoWorkflowAdapter::class)->submit(referenceAuiSubmission()))->toThrow(DomainException::class)
        ->and(fn () => app(AuiDemonstrationWorkflowAdapter::class)->submit(referenceBstSubmission()))->toThrow(DomainException::class);
    $environment = app()->environment();
    try {
        app()->instance('env', 'production');
        expect(fn () => app(PhilhealthBstDemoWorkflowAdapter::class)->submit(referenceBstSubmission()))->toThrow(DomainException::class, 'local testing');
    } finally {
        app()->instance('env', $environment);
    }
    Http::assertNothingSent();
});

it('rejects incomplete BST evidence and invalid requested amounts', function (): void {
    expect(fn () => referenceBstSubmission(0))->toThrow(DomainException::class)
        ->and(fn () => new PhilhealthBstDemoSubmissionData('key', 'REF-123', 10000, 'Synthetic Applicant', '09170000000', []))->toThrow(DomainException::class);
});

function allowReferenceWorkflows(): void
{
    app()->bind(WorkflowAccessPolicy::class, fn () => new class implements WorkflowAccessPolicy
    {
        public function allows(WorkflowContext $context, string $id, string $version): bool
        {
            return $context->accountId === 'account-demo';
        }
    });
}

function referenceBstSubmission(int $amount = 10000): PhilhealthBstDemoSubmissionData
{
    return new PhilhealthBstDemoSubmissionData('bst-demo:1', 'CLAIM-001', $amount, 'Synthetic Applicant', '09170000000', [
        'claim_form' => str_repeat('a', 64), 'hospital_bill' => str_repeat('b', 64),
    ]);
}

function referenceAuiSubmission(): AuiWorkflowSubmissionData
{
    return new AuiWorkflowSubmissionData(new PolicyCompletionPreparationData(
        driverId: AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID, driverVersion: '1.0.0',
        idempotencyKey: 'aui-policy-completion:demo-projection', fingerprint: str_repeat('c', 64),
        projectionReference: 'demo-projection', envelopeReference: 'demo-envelope', envelopePayloadVersion: 2,
        coverageReference: 'demo-coverage', coverageType: 'provisional_personal_accident', coverageAmountMinor: 500000,
        currency: 'PHP', coverageEffectiveAt: CarbonImmutable::parse('2026-09-25T00:00:00Z'),
        coverageExpiresAt: CarbonImmutable::parse('2026-09-26T00:00:00Z'), paymentRecognitionReference: 'demo-payment',
        paymentAmountMinor: 5000, paymentSettledAt: CarbonImmutable::parse('2026-09-25T00:00:00Z'),
        completionPayCodeReference: 'demo-completion', claimNumber: 1,
        applicantEvidence: ['name' => 'Synthetic Applicant', 'mobile' => '09170000000'],
    ));
}

function referenceAuiResponse(): array
{
    return (new AuiDemonstrationPolicyResponseData('AUI-DEMO-0123456789ABCDEF', 'AUI-PA-DEMO',
        CarbonImmutable::parse('2026-09-25T00:00:00Z'), CarbonImmutable::parse('2026-09-26T00:00:00Z')))->toArray();
}

function configureReferenceAuiConnection(): void
{
    config()->set('services.pipedream.policy_completion_token', 'test-token');
    config()->set('settlement-envelope.connections.aui-demo', [
        'driver' => 'http', 'base_url' => 'https://demo.m.pipedream.net/policy-completion',
        'connect_timeout' => 3, 'timeout' => 10, 'auth' => ['type' => 'bearer', 'token' => 'test-token'],
    ]);
    config()->set('x-change.settlement.policy_completion.transports', [
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@1.0.0' => [
            'schema' => 'x-change.policy-completion-transport-disposition.v1', 'enabled' => true, 'accepted' => true,
            'driver_id' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID, 'driver_version' => '1.0.0',
            'provider' => 'pipedream-test', 'contract_id' => 'aui-demonstration-policy-completion', 'contract_version' => '1.0.0',
            'submission_endpoint' => 'https://demo.m.pipedream.net/policy-completion', 'authentication_scheme' => 'bearer-token',
            'request_schema_reference' => AuiDemonstrationPolicyTransportRequestData::SCHEMA, 'request_schema_version' => '1.0.0',
            'request_schema_digest' => 'sha256:'.str_repeat('a', 64),
            'response_schema_reference' => AuiDemonstrationPolicyResponseData::SCHEMA, 'response_schema_version' => '1.0.0',
            'response_schema_digest' => 'sha256:'.str_repeat('b', 64), 'idempotency_mechanism' => 'idempotency-key-header',
            'connect_timeout_seconds' => 3, 'response_timeout_seconds' => 10, 'retry_policy' => 'none-in-characterization',
            'ambiguous_outcome_policy' => 'fail-closed-no-outcome', 'reconciliation_mode' => 'manual-test-inspection',
            'credential_reference' => 'services.pipedream.policy_completion_token', 'acceptance_reference' => 'synthetic-test-acceptance',
            'accepted_at' => '2026-09-25T00:00:00Z', 'accepted_by_reference' => 'test-owner',
        ],
    ]);
}
