<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyResponseData;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyTransportRequestData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use LBHurtado\XChange\Services\Settlement\AuiPersonalAccidentPolicyCompletionDriver;
use LBHurtado\XChange\Services\Settlement\DispatchAuiDemonstrationPolicyViaPipedream;

it('sends only the authorized limited payload and validates the demonstration response', function (): void {
    $preparation = pipedreamPolicyPreparation();
    configurePipedreamTestDisposition();
    Http::preventStrayRequests();
    Http::fake([
        'https://demo.m.pipedream.net/*' => Http::response([
            'schema' => AuiDemonstrationPolicyResponseData::SCHEMA,
            'status' => 'issued_demo',
            'policy_reference' => 'AUI-DEMO-PIPEDREAM01',
            'product_code' => 'AUI-PA-DEMO',
            'effective_at' => $preparation->coverageEffectiveAt->toIso8601String(),
            'expires_at' => $preparation->coverageExpiresAt?->toIso8601String(),
            'document_ready' => false,
            'demonstration_only' => true,
        ]),
    ]);

    $result = app(DispatchAuiDemonstrationPolicyViaPipedream::class)->handle($preparation);

    expect($result->policyReference)->toBe('AUI-DEMO-PIPEDREAM01')
        ->and($result->outcome()->resultCode)->toBe('policy_issued_demo');

    $request = Http::recorded()->sole()[0];
    $data = $request->data();
    $body = json_encode($data, JSON_THROW_ON_ERROR);

    expect($request)->toBeInstanceOf(Request::class)
        ->and($request->url())->toBe('https://demo.m.pipedream.net/policy-completion')
        ->and($request->header('Authorization'))->toContain('Bearer test-pipedream-token')
        ->and($request->header('Idempotency-Key'))->toContain($preparation->idempotencyKey)
        ->and($data['schema'])->toBe(AuiDemonstrationPolicyTransportRequestData::SCHEMA)
        ->and($data['applicant_evidence_mode'])->toBe('withheld')
        ->and($data)->not->toHaveKey('applicant_evidence')
        ->and($body)->not->toContain('Apple Hurtado')
        ->and($body)->not->toContain('09175180722');
});

it('fails before HTTP for an unaccepted endpoint', function (): void {
    $preparation = pipedreamPolicyPreparation();
    configurePipedreamTestDisposition(endpoint: 'https://example.test/policy-completion');
    Http::fake();

    expect(fn () => app(DispatchAuiDemonstrationPolicyViaPipedream::class)->handle($preparation))
        ->toThrow(DomainException::class, 'accepted Pipedream test transport')
        ->and(fn () => Http::assertNothingSent())->not->toThrow(Throwable::class);
});

it('rejects a response that changes the authoritative coverage period', function (): void {
    $preparation = pipedreamPolicyPreparation();
    configurePipedreamTestDisposition();
    Http::fake([
        'https://demo.m.pipedream.net/*' => Http::response([
            'schema' => AuiDemonstrationPolicyResponseData::SCHEMA,
            'status' => 'issued_demo',
            'policy_reference' => 'AUI-DEMO-PIPEDREAM01',
            'product_code' => 'AUI-PA-DEMO',
            'effective_at' => $preparation->coverageEffectiveAt->addDay()->toIso8601String(),
            'expires_at' => $preparation->coverageExpiresAt?->toIso8601String(),
            'document_ready' => false,
            'demonstration_only' => true,
        ]),
    ]);

    expect(fn () => app(DispatchAuiDemonstrationPolicyViaPipedream::class)->handle($preparation))
        ->toThrow(DomainException::class, 'changed the authoritative coverage period');
});

it('publishes a strict transport request schema that withholds applicant evidence', function (): void {
    $schema = json_decode((string) file_get_contents(
        dirname(__DIR__, 4).'/resources/policy-completion-contracts/aui-demonstration-policy-transport-request-v1.schema.json',
    ), true, flags: JSON_THROW_ON_ERROR);

    expect($schema['additionalProperties'])->toBeFalse()
        ->and($schema['properties']['demonstration_only']['const'])->toBeTrue()
        ->and($schema['properties']['applicant_evidence_mode']['const'])->toBe('withheld')
        ->and($schema['properties'])->not->toHaveKey('applicant_evidence');
});

function configurePipedreamTestDisposition(string $endpoint = 'https://demo.m.pipedream.net/policy-completion'): void
{
    config()->set('services.pipedream.policy_completion_token', 'test-pipedream-token');
    config()->set('x-change.settlement.policy_completion.transports', [
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@'.AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION => [
            'schema' => 'x-change.policy-completion-transport-disposition.v1',
            'enabled' => true,
            'accepted' => true,
            'driver_id' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
            'driver_version' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
            'provider' => 'pipedream-test',
            'contract_id' => 'aui-demonstration-policy-completion',
            'contract_version' => '1.0.0',
            'submission_endpoint' => $endpoint,
            'authentication_scheme' => 'bearer-token',
            'request_schema_reference' => AuiDemonstrationPolicyTransportRequestData::SCHEMA,
            'request_schema_version' => '1.0.0',
            'request_schema_digest' => 'sha256:'.str_repeat('a', 64),
            'response_schema_reference' => AuiDemonstrationPolicyResponseData::SCHEMA,
            'response_schema_version' => '1.0.0',
            'response_schema_digest' => 'sha256:'.str_repeat('b', 64),
            'idempotency_mechanism' => 'idempotency-key-header',
            'connect_timeout_seconds' => 3,
            'response_timeout_seconds' => 10,
            'retry_policy' => 'none-in-characterization',
            'ambiguous_outcome_policy' => 'fail-closed-no-outcome',
            'reconciliation_mode' => 'manual-test-inspection',
            'credential_reference' => 'services.pipedream.policy_completion_token',
            'acceptance_reference' => 'user-authorized-limited-payload-2026-09-24',
            'accepted_at' => '2026-09-24T00:00:00+00:00',
            'accepted_by_reference' => 'x-change-owner',
        ],
    ]);
}

function pipedreamPolicyPreparation(): PolicyCompletionPreparationData
{
    return new PolicyCompletionPreparationData(
        driverId: AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
        driverVersion: AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
        idempotencyKey: 'aui-policy-completion:projection-pipedream',
        fingerprint: str_repeat('c', 64),
        projectionReference: 'projection-pipedream',
        envelopeReference: 'envelope-pipedream',
        envelopePayloadVersion: 2,
        coverageReference: 'coverage-pipedream',
        coverageType: 'provisional_personal_accident',
        coverageAmountMinor: 100_000,
        currency: 'PHP',
        coverageEffectiveAt: CarbonImmutable::parse('2026-09-24T00:00:00Z'),
        coverageExpiresAt: CarbonImmutable::parse('2026-10-24T00:00:00Z'),
        paymentRecognitionReference: 'recognition-pipedream',
        paymentAmountMinor: 1_000,
        paymentSettledAt: CarbonImmutable::parse('2026-09-24T00:00:00Z'),
        completionPayCodeReference: 'completion-pipedream',
        claimNumber: 1,
        applicantEvidence: [
            'name' => 'Apple Hurtado',
            'mobile' => '09175180722',
        ],
    );
}
