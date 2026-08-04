<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityIssuanceGuard;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityReadinessRegistry;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityRequirementResolver;

beforeEach(function (): void {
    config()->set('location-handler.map_provider', 'mapbox');
    config()->set('location-handler.opencage_api_key', null);
    config()->set('location-handler.mapbox_token', null);
    config()->set('kyc-handler.use_fake', false);
    config()->set('kyc-handler.hyperverge.app_id', null);
    config()->set('kyc-handler.hyperverge.app_key', null);
    config()->set('kyc-handler.hyperverge.workflow', 'onboarding');
    config()->set('otp-handler.driver', 'unavailable');
    config()->set('otp-handler.txtcmdr.base_url', 'https://txtcmdr.example.test');
    config()->set('otp-handler.txtcmdr.api_token', null);
});

it('reports optional evidence services without exposing credentials', function (): void {
    config()->set('location-handler.opencage_api_key', 'open-cage-secret');
    config()->set('location-handler.mapbox_token', 'mapbox-secret');

    $capabilities = app(InstructionCapabilityReadinessRegistry::class)->sanitized();
    $serialized = json_encode($capabilities, JSON_THROW_ON_ERROR);

    expect($capabilities['location'])
        ->status->toBe('ready')
        ->issuance_allowed->toBeTrue()
        ->missing_configuration->toBe([])
        ->and($serialized)
        ->not->toContain('open-cage-secret')
        ->not->toContain('mapbox-secret');
});

it('marks location unavailable when either authoritative service is missing', function (): void {
    config()->set('location-handler.opencage_api_key', 'configured');

    $location = app(InstructionCapabilityReadinessRegistry::class)->find('location');

    expect($location)->not->toBeNull()
        ->and($location->status)->toBe('unavailable')
        ->and($location->issuanceAllowed)->toBeFalse()
        ->and($location->missingConfiguration)->toBe(['MAPBOX_TOKEN']);
});

it('marks location unavailable when provider credentials contain whitespace', function (): void {
    config()->set('location-handler.opencage_api_key', "open-cage\ncredential");
    config()->set('location-handler.mapbox_token', ' mapbox-credential ');

    $location = app(InstructionCapabilityReadinessRegistry::class)->find('location');

    expect($location)->not->toBeNull()
        ->and($location->status)->toBe('unavailable')
        ->and($location->issuanceAllowed)->toBeFalse()
        ->and($location->missingConfiguration)->toBe([
            'OPENCAGE_API_KEY',
            'MAPBOX_TOKEN',
        ]);
});

it('allows explicitly simulated KYC only outside production', function (): void {
    config()->set('kyc-handler.use_fake', true);

    $kyc = app(InstructionCapabilityReadinessRegistry::class)->find('kyc');

    expect($kyc)->not->toBeNull()
        ->and($kyc->status)->toBe('simulation')
        ->and($kyc->issuanceAllowed)->toBeTrue();
});

it('resolves evidence and delivery requirements from normalized voucher instructions', function (): void {
    $required = app(InstructionCapabilityRequirementResolver::class)->forInstructions([
        'inputs' => [
            'fields' => ['mobile', 'signature'],
            'requirements' => ['kyc', 'otp', 'selfie'],
        ],
        'validation' => [
            'location' => ['required' => true],
        ],
        'feedback' => [
            'mobile' => '639171234567',
            'email' => 'recipient@example.test',
        ],
    ]);

    expect($required)->toBe([
        'feedback.email',
        'feedback.sms',
        'kyc',
        'location',
        'otp',
        'selfie',
        'signature',
    ]);
});

it('rejects unavailable evidence before issuance can mutate state', function (): void {
    $guard = app(InstructionCapabilityIssuanceGuard::class);

    expect(fn () => $guard->ensureAvailable([
        'cash' => ['amount' => 50, 'currency' => 'PHP'],
        'validation' => ['location' => ['required' => true]],
    ]))->toThrow(ValidationException::class);
});
