<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyResponseData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use LBHurtado\XChange\Services\Settlement\AuiPersonalAccidentPolicyCompletionDriver;
use LBHurtado\XChange\Services\Settlement\GenerateAuiDemonstrationPolicyResponse;

it('generates one deterministic and explicitly demonstrational response', function (): void {
    $preparation = demonstrationPolicyPreparation();
    $responder = new GenerateAuiDemonstrationPolicyResponse;

    $first = $responder->handle($preparation);
    $replay = $responder->handle($preparation);

    expect($first->toArray())
        ->toBe($replay->toArray())
        ->and($first->toArray())->toMatchArray([
            'schema' => AuiDemonstrationPolicyResponseData::SCHEMA,
            'status' => 'issued_demo',
            'product_code' => 'AUI-PA-DEMO',
            'document_ready' => false,
            'demonstration_only' => true,
        ])
        ->and($first->policyReference)->toMatch('/\AAUI-DEMO-[A-F0-9]{16}\z/')
        ->and($first->outcome()->resultCode)->toBe('policy_issued_demo')
        ->and($first->outcome()->safeResult['reason_code'])->toBe('demonstration_only')
        ->and(json_encode($first->toArray(), JSON_THROW_ON_ERROR))->not->toContain('Apple Hurtado')
        ->and(json_encode($first->outcome()->safeResult, JSON_THROW_ON_ERROR))->not->toContain('09175180722');
});

it('fails closed for another driver or missing applicant evidence', function (): void {
    $otherDriver = demonstrationPolicyPreparation(driverId: 'another.driver');
    $missingEvidence = demonstrationPolicyPreparation(applicantEvidence: []);
    $responder = new GenerateAuiDemonstrationPolicyResponse;

    expect(fn () => $responder->handle($otherDriver))
        ->toThrow(DomainException::class, 'reserved AUI driver')
        ->and(fn () => $responder->handle($missingEvidence))
        ->toThrow(DomainException::class, 'completed applicant evidence');
});

it('publishes a strict demonstration response schema', function (): void {
    $schema = json_decode((string) file_get_contents(
        dirname(__DIR__, 4).'/resources/policy-completion-contracts/aui-demonstration-policy-response-v1.schema.json',
    ), true, flags: JSON_THROW_ON_ERROR);

    expect($schema['additionalProperties'])->toBeFalse()
        ->and($schema['properties']['demonstration_only']['const'])->toBeTrue()
        ->and($schema['properties']['status']['const'])->toBe('issued_demo')
        ->and($schema['properties']['document_ready']['const'])->toBeFalse();
});

/**
 * @param  array<string, mixed>  $applicantEvidence
 */
function demonstrationPolicyPreparation(
    string $driverId = AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
    array $applicantEvidence = [
        'name' => 'Apple Hurtado',
        'mobile' => '09175180722',
    ],
): PolicyCompletionPreparationData {
    return new PolicyCompletionPreparationData(
        driverId: $driverId,
        driverVersion: AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
        idempotencyKey: 'aui-policy-completion:projection-1',
        fingerprint: str_repeat('a', 64),
        projectionReference: 'projection-1',
        envelopeReference: 'envelope-1',
        envelopePayloadVersion: 2,
        coverageReference: 'coverage-1',
        coverageType: 'provisional_personal_accident',
        coverageAmountMinor: 100_000,
        currency: 'PHP',
        coverageEffectiveAt: CarbonImmutable::parse('2026-09-24T00:00:00Z'),
        coverageExpiresAt: CarbonImmutable::parse('2026-10-24T00:00:00Z'),
        paymentRecognitionReference: 'recognition-1',
        paymentAmountMinor: 1_000,
        paymentSettledAt: CarbonImmutable::parse('2026-09-24T00:00:00Z'),
        completionPayCodeReference: 'completion-1',
        claimNumber: 1,
        applicantEvidence: $applicantEvidence,
    );
}
