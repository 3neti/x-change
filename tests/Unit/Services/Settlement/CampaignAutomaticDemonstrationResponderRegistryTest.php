<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Contracts\CampaignAutomaticDemonstrationResponderContract;
use LBHurtado\XChange\Data\Settlement\MedicardDemoBenefitResponseData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionOutcomeData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use LBHurtado\XChange\Enums\PolicyCompletionOutcomeStatus;
use LBHurtado\XChange\Services\Settlement\CampaignAutomaticDemonstrationResponderRegistry;
use LBHurtado\XChange\Services\Settlement\MedicardDemoBenefitPolicyCompletionDriver;
use LBHurtado\XChange\Services\Settlement\MedicardDemoBenefitResponder;

function automaticDemonstrationResponder(
    string $id,
    string $version,
): CampaignAutomaticDemonstrationResponderContract {
    return new class($id, $version) implements CampaignAutomaticDemonstrationResponderContract
    {
        public function __construct(
            private readonly string $id,
            private readonly string $version,
        ) {}

        public function driverId(): string
        {
            return $this->id;
        }

        public function driverVersion(): string
        {
            return $this->version;
        }

        public function complete(
            PolicyCompletionPreparationData $preparation,
        ): PolicyCompletionOutcomeData {
            return new PolicyCompletionOutcomeData(
                status: PolicyCompletionOutcomeStatus::Succeeded,
                resultCode: 'test_demo',
            );
        }
    };
}

function medicardDemoPreparation(
    string $driverId = MedicardDemoBenefitPolicyCompletionDriver::DRIVER_ID,
    string $driverVersion = MedicardDemoBenefitPolicyCompletionDriver::DRIVER_VERSION,
    int $paymentAmountMinor = 5000,
    string $currency = 'PHP',
    array $applicantEvidence = ['name' => 'Demo Participant'],
): PolicyCompletionPreparationData {
    return new PolicyCompletionPreparationData(
        driverId: $driverId,
        driverVersion: $driverVersion,
        idempotencyKey: 'medicard-demo-benefit:projection-reference',
        fingerprint: str_repeat('a', 64),
        projectionReference: 'projection-reference',
        envelopeReference: 'envelope-reference',
        envelopePayloadVersion: 1,
        coverageReference: 'coverage-reference',
        coverageType: 'healthcare-access-journey-demonstration',
        coverageAmountMinor: null,
        currency: $currency,
        coverageEffectiveAt: CarbonImmutable::parse('2026-10-09T00:00:00+00:00'),
        coverageExpiresAt: CarbonImmutable::parse('2026-10-10T00:00:00+00:00'),
        paymentRecognitionReference: 'recognition-reference',
        paymentAmountMinor: $paymentAmountMinor,
        paymentSettledAt: CarbonImmutable::parse('2026-10-09T00:00:00+00:00'),
        completionPayCodeReference: 'issuance-reference',
        claimNumber: 1,
        applicantEvidence: $applicantEvidence,
    );
}

it('resolves exact automatic demonstration responders and rejects unknown versions', function (): void {
    $aui = automaticDemonstrationResponder('aui.personal-accident.provisional-cover', '1.0.0');
    $medicard = automaticDemonstrationResponder('medicard.demo-benefit', '1.0.0');
    $registry = new CampaignAutomaticDemonstrationResponderRegistry([$aui, $medicard]);

    expect($registry->supports($aui->driverId(), $aui->driverVersion()))->toBeTrue()
        ->and($registry->supports($medicard->driverId(), $medicard->driverVersion()))->toBeTrue()
        ->and($registry->supports('medicard.demo-benefit', '2.0.0'))->toBeFalse()
        ->and(fn () => $registry->for('medicard.demo-benefit', '2.0.0'))
        ->toThrow(LogicException::class, 'is unavailable');
});

it('rejects duplicate automatic demonstration responder identities', function (): void {
    expect(fn () => new CampaignAutomaticDemonstrationResponderRegistry([
        automaticDemonstrationResponder('medicard.demo-benefit', '1.0.0'),
        automaticDemonstrationResponder('medicard.demo-benefit', '1.0.0'),
    ]))->toThrow(LogicException::class, 'Multiple campaign automatic demonstration responders');
});

it('returns a deterministic sanitized Medicard demonstration outcome', function (): void {
    $responder = new MedicardDemoBenefitResponder;
    $preparation = medicardDemoPreparation();

    $first = $responder->complete($preparation);
    $replay = $responder->complete($preparation);

    expect($first->status)->toBe(PolicyCompletionOutcomeStatus::Succeeded)
        ->and($first->resultCode)->toBe(MedicardDemoBenefitResponseData::RESULT_CODE)
        ->and($first->providerReference)->toBe($replay->providerReference)
        ->and($first->providerReference)->toStartWith('MEDICARD-DEMO-')
        ->and($first->safeResult)->toBe([
            'decision' => 'ready_demo',
            'document_ready' => false,
            'provider_status' => 'ready_demo',
            'reason_code' => 'demonstration_only',
            'retryable' => false,
        ])
        ->and(json_encode($first, JSON_THROW_ON_ERROR))->not->toContain('Demo Participant');
});

it('publishes a strict Medicard demonstration response schema', function (): void {
    $schema = json_decode((string) file_get_contents(
        dirname(__DIR__, 4).'/resources/policy-completion-contracts/medicard-demo-benefit-response-v1.schema.json',
    ), true, flags: JSON_THROW_ON_ERROR);

    expect($schema['additionalProperties'])->toBeFalse()
        ->and($schema['properties']['result_code']['const'])->toBe('benefit_ready_demo')
        ->and($schema['properties']['demonstration_only']['const'])->toBeTrue()
        ->and($schema['properties']['membership_created']['const'])->toBeFalse()
        ->and($schema['properties']['healthcare_coverage_created']['const'])->toBeFalse()
        ->and($schema['properties']['treatment_authorized']['const'])->toBeFalse();
});

it('rejects mismatched Medicard completion preparations', function (
    PolicyCompletionPreparationData $preparation,
): void {
    expect(fn () => (new MedicardDemoBenefitResponder)->complete($preparation))
        ->toThrow(DomainException::class);
})->with([
    'wrong driver' => fn (): PolicyCompletionPreparationData => medicardDemoPreparation(driverId: 'another-driver'),
    'wrong version' => fn (): PolicyCompletionPreparationData => medicardDemoPreparation(driverVersion: '2.0.0'),
    'wrong amount' => fn (): PolicyCompletionPreparationData => medicardDemoPreparation(paymentAmountMinor: 5001),
    'wrong currency' => fn (): PolicyCompletionPreparationData => medicardDemoPreparation(currency: 'USD'),
    'missing applicant evidence' => fn (): PolicyCompletionPreparationData => medicardDemoPreparation(applicantEvidence: []),
]);
