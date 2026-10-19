<?php

declare(strict_types=1);

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\FormFlowServiceProvider;
use LBHurtado\FormFlowManager\Services\FormFlowPresentationResolver;
use LBHurtado\FormFlowManager\Services\FormFlowPreviewCompiler;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormHandlerKYC\KYCHandlerServiceProvider;
use LBHurtado\FormHandlerLocation\LocationHandlerServiceProvider;
use LBHurtado\FormHandlerOtp\OtpHandlerServiceProvider;
use LBHurtado\FormHandlerSelfie\SelfieHandlerServiceProvider;
use LBHurtado\FormHandlerSignature\SignatureHandlerServiceProvider;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewArtifactFingerprint;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    foreach ([
        FormFlowServiceProvider::class,
        OtpHandlerServiceProvider::class,
        KYCHandlerServiceProvider::class,
        LocationHandlerServiceProvider::class,
        SelfieHandlerServiceProvider::class,
        SignatureHandlerServiceProvider::class,
    ] as $provider) {
        app()->register($provider);
    }
});

it('opts Pay Code drivers into the same constrained presentation profile', function (string $driver): void {
    $yaml = Yaml::parseFile(dirname(__DIR__, 3).'/config/form-flow-drivers/'.$driver.'.yaml');
    $resolved = app(FormFlowPresentationResolver::class)->resolve($yaml['presentation']);
    expect($resolved['profile'])->toBe('guided-claim-v1')
        ->and($resolved['brand']['logo_height'])->toBe(32)
        ->and($resolved['actions']['placement'])->toBe('viewport_bottom');
})->with(['voucher-redemption', 'campaign-payment-completion']);

it('delivers the frozen profile to every installed handler preview without enabling capture', function (string $handler): void {
    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'synthetic-presentation-'.$handler,
        'presentation' => ['profile' => 'guided-claim-v1'],
        'callbacks' => ['on_cancel' => '/synthetic-cancel'],
        'steps' => [['handler' => $handler, 'config' => ['title' => 'Synthetic capture']]],
    ]);
    $screens = app(FormFlowPreviewCompiler::class)->compile($instructions);
    $flows = app(FormFlowService::class);
    $live = $flows->startFlow($instructions);
    expect($screens[0]->props['presentation'])->toBe($live['instructions']['steps'][0]['config']['presentation']);
    $flows->updateStepData($live['flow_id'], 0, ['synthetic_evidence' => true]);
    expect($flows->completeFlow($live['flow_id'])['status'])->toBe('completed');
    expect($screens[0]->component)->toStartWith('form-flow/'.$handler.'/')
        ->and($screens[0]->props['presentation']['profile'])->toBe('guided-claim-v1')
        ->and($screens[0]->props['presentation']['progress'])->toBe(['show' => true, 'current' => 1, 'total' => 1])
        ->and($screens[0]->props['preview_mode'])->toBeTrue();
})->with(['otp', 'kyc', 'location', 'selfie', 'signature']);

it('invalidates cached claim previews when the presentation profile changes', function (): void {
    $fingerprints = app(ClaimPreviewArtifactFingerprint::class);
    $before = $fingerprints->make(['key' => 'synthetic']);
    config()->set('form-flow.presentation.profiles.guided-claim-v1.brand.logo_height', 24);
    $after = $fingerprints->make(['key' => 'synthetic']);
    expect($after['fingerprint'])->not->toBe($before['fingerprint']);
});
