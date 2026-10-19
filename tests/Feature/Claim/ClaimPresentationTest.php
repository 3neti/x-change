<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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
use LBHurtado\XChange\Services\Claim\ClaimPresentationProfile;
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

it('loads the package YAML palette and icon registry into the public claim profile', function (): void {
    $yaml = Yaml::parseFile(dirname(__DIR__, 3).'/config/form-flow-presentations/guided-claim-v1.yaml');
    $profile = app(ClaimPresentationProfile::class)->current();
    expect($profile['appearance'])->toBe($yaml['appearance'])
        ->and($profile['icons'])->toBe($yaml['icons']);
});

it('retains the started claim profile separately for each Pay Code', function (): void {
    $profiles = app(ClaimPresentationProfile::class);
    $frozen = $profiles->current();
    $profiles->remember('AA317', ['instructions' => ['metadata' => ['form_flow_presentation' => $frozen]]]);
    config()->set('form-flow.presentation.profiles.guided-claim-v1.icons.size', 32);
    $request = Request::create('/x/claim/AA317');
    $request->setLaravelSession(session()->driver());
    $route = new Route('GET', '/x/claim/{code}', fn () => null);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    expect($profiles->forRequest($request))->toBe($frozen);
    $route->setParameter('code', 'OTHER');
    expect($profiles->forRequest($request)['icons']['size'])->toBe(32);
});

it('invalidates preview artifacts when the renderer changes', function (): void {
    $fingerprints = app(ClaimPreviewArtifactFingerprint::class);
    $old = $fingerprints->make(['key' => 'synthetic'], ['render_mode' => 'compiled_claim_screen_v2']);
    $current = $fingerprints->make(['key' => 'synthetic'], ['render_mode' => 'compiled_claim_screen_v3']);
    expect($current['fingerprint'])->not->toBe($old['fingerprint']);
});

it('never shares claim diagnostics outside local and testing even with screen overrides', function (): void {
    config()->set('form-flow.presentation.profiles.guided-claim-v1.screens.entry.diagnostics.show_versions', true);
    $profiles = app(ClaimPresentationProfile::class);
    expect($profiles->versionsForRequest(Request::create('/x/claim')))->not->toBeEmpty();
    $this->app['env'] = 'production';
    expect($profiles->versionsForRequest(Request::create('/x/claim')))->toBe([]);
    $this->app['env'] = 'testing';
});
