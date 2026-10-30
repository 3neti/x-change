<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LBHurtado\FormFlowManager\Handlers\SplashHandler;
use LBHurtado\FormHandlerKYC\KYCHandler;
use LBHurtado\FormHandlerLocation\LocationHandler;
use LBHurtado\FormHandlerOtp\OtpHandler;
use LBHurtado\FormHandlerSelfie\SelfieHandler;
use LBHurtado\FormHandlerSignature\SignatureHandler;
use LBHurtado\Voucher\Events\VouchersGenerated;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewOptions;
use LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewService;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewArtifactFingerprint;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewJourneyManifestFactory;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewVoucherDisposer;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewVoucherIssuer;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewVoucherPayloadFactory;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewWebManifestPresenter;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Enums\ClaimPreviewProgressState;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;
use LBHurtado\XChange\Services\Execution\CampaignCoverageCompletionExecutionDriver;

beforeEach(function (): void {
    config()->set('form-flow.handlers', [
        'splash' => SplashHandler::class,
        'kyc' => KYCHandler::class,
        'location' => LocationHandler::class,
        'otp' => OtpHandler::class,
        'selfie' => SelfieHandler::class,
        'signature' => SignatureHandler::class,
    ]);
});

it('uses the payment page for payable instructions across schema versions', function (string $schema): void {
    $scenario = ['fixture' => ['instructions' => [
        'voucher_type' => 'payable', 'target_amount' => 100, 'fee_bearer' => 'payer',
        'cash' => ['amount' => 0, 'currency' => 'PHP'], 'execution' => ['schema' => $schema],
    ]], 'checkpoints' => [['key' => 'claim-entry']]];
    $journey = app(ClaimPreviewJourneyManifestFactory::class)->fromReport([], $scenario);
    expect(collect($journey['steps'])->pluck('key')->all())->toBe(['payment-page'])
        ->and(data_get($journey, 'steps.0.screen.props.previewFeePending'))->toBeTrue()
        ->and(data_get($journey, 'steps.0.screen.props.payment.target_amount_minor'))->toBe(10000);
})->with(['voucher.execution.v1', 'voucher.execution.v2']);

it('projects the patient, hospital and payment waits for a PhilHealth settlement', function (): void {
    $scenario = ['fixture' => ['instructions' => [
        'target_amount' => 20000,
        'execution' => ['schema' => 'voucher.execution.v2', 'settlement' => ['driver' => 'philhealth-bst'],
            'events' => ['attest' => [], 'review' => [], 'settle' => []]],
    ]], 'checkpoints' => []];
    $journey = app(ClaimPreviewJourneyManifestFactory::class)->fromReport([], $scenario);
    $steps = collect($journey['steps'])->keyBy('key');

    expect($steps->get('settlement-waiting')['screen']['props']['code'])->toBe('AA317')
        ->and($steps->get('settlement-review')['screen']['props']['amountMinor'])->toBe(2000000)
        ->and($steps->get('settlement-review')['screen']['props']['preview'])->toBeTrue()
        ->and($steps->get('settlement-payment-waiting')['screen']['props']['reviewed'])->toBeTrue();
    $artifact = new ClaimPreviewArtifact(['reference' => 'preview123', 'metadata' => ['journey' => $journey]]);
    $manifest = app(ClaimPreviewWebManifestPresenter::class)->present($artifact);
    expect(collect($manifest['journey']['steps'])->firstWhere('key', 'settlement-review')['actor'])->toBe('Hospital');
});

it('projects simulated payment and payout stages only when instructed', function (): void {
    $scenario = ['fixture' => ['instructions' => ['execution' => [
        'schema' => 'voucher.execution.v2',
        'events' => ['settle' => ['actor' => 'payer']],
        'value_flow' => ['destination' => 'instapay.example.account:1234'],
    ]]], 'checkpoints' => []];
    $factory = app(ClaimPreviewJourneyManifestFactory::class);
    $steps = collect($factory->fromReport([], $scenario)['steps'])->keyBy('key');
    expect($steps->get('execution-payment')['screen']['simulated'])->toBeTrue()
        ->and($steps->get('execution-payout')['screen']['description'])->toContain('recovery');

    data_set($scenario, 'fixture.instructions.execution.value_flow.destination', 'client_funds');
    expect(collect($factory->fromReport([], $scenario)['steps'])->pluck('key')->all())
        ->toContain('execution-payment')->not->toContain('execution-payout');
    data_set($scenario, 'fixture.instructions.execution.schema', 'voucher.execution.v1');
    expect(collect($factory->fromReport([], $scenario)['steps'])->pluck('key')->all())
        ->not->toContain('execution-payment');
});

it('renders and caches preview artifacts from voucher instructions', function (): void {
    $issuer = actingAsTestUser();
    $instructions = validVoucherInstructions(42.00, overrides: [
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Preview rider message.',
            'url' => 'https://example.test/rider',
            'redirect_timeout' => 4,
            'splash' => '<section>Preview rider splash</section>',
            'splash_timeout' => 2,
            'og_source' => 'message',
        ],
        'metadata' => [
            'custom' => [
                'named_slices' => [
                    [
                        'id' => 'fare',
                        'amount' => '42.00',
                        'description' => 'Transport fare',
                    ],
                ],
            ],
        ],
    ]);

    /** @var ClaimExperiencePreviewService $service */
    $service = app(ClaimExperiencePreviewService::class);
    $result = $service->renderFromInstructions($instructions, new ClaimExperiencePreviewOptions(
        issuer: $issuer,
        baseUrl: 'http://x-change-sandbox.test',
        dryRun: true,
        refresh: true,
    ));

    expect($result['schema'])->toBe('x-change.claim-experience-preview.result.v1')
        ->and($result['status'])->toBe('ready')
        ->and($result['cache_hit'])->toBeFalse()
        ->and(data_get($result, 'journey.schema'))->toBe('x-change.claim-experience-preview.journey.v2')
        ->and(data_get($result, 'journey.steps.0.key'))->toBe('claim-entry')
        ->and(data_get($result, 'journey.steps.0.screen.code'))->toBe('AA317')
        ->and(data_get($result, 'journey.steps.0.screen.xray.audience'))->toBe('guest')
        ->and(data_get($result, 'journey.steps.0.screen.xray.status'))->toBe('claimable')
        ->and(data_get($result, 'journey.steps.0.screen.fields.0.value'))->toBe('AA317')
        ->and(data_get($result, 'journey.presentation.appearance.mode'))->toBe('dark')
        ->and(collect(data_get($result, 'journey.steps'))->firstWhere('key', 'pre-claim-rider-splash')['screen']['component'])->toBe('form-flow/core/Splash')
        ->and(collect(data_get($result, 'journey.steps'))->firstWhere('key', 'pre-claim-rider-splash')['screen']['props']['voucher_code'])->toBe('AA317')
        ->and(collect(data_get($result, 'journey.steps'))->pluck('key')->all())
        ->not->toContain('og-social-preview')
        ->and(data_get($result, 'artifacts.storyboard_pdf'))->toBeFile()
        ->and(data_get($result, 'artifacts.storyboard_html'))->toBeFile()
        ->and(data_get($result, 'artifacts.view_options.default.label'))->toBe('Default PDF');

    $disclosures = collect(data_get($result, 'journey.steps.0.screen.xray.disclosures'))->keyBy('key');

    expect(data_get($disclosures->get('reference'), 'value'))->toBe('AA317')
        ->and((string) data_get($disclosures->get('amount'), 'value'))->toContain('42.00')
        ->and($disclosures->has('redirect_url'))->toBeFalse();

    $storyboard = json_decode(file_get_contents(data_get($result, 'artifacts.storyboard_json')), true);

    expect(data_get($storyboard, 'scenario.fixture.amount'))->toBe('42')
        ->and(data_get($storyboard, 'scenario.fixture.rider.message'))->toBe('Preview rider message.')
        ->and(data_get($storyboard, 'scenario.fixture.rider_splash'))->toBeTrue()
        ->and(data_get($storyboard, 'scenario.fixture.rider_redirect'))->toBeTrue()
        ->and(data_get($storyboard, 'scenario.fixture.slices.0.description'))->toBe('Transport fare');

    $artifact = ClaimPreviewArtifact::query()
        ->where('artifact_fingerprint', $result['fingerprint'])
        ->first();

    expect($artifact)->not->toBeNull();

    $cached = $service->renderFromInstructions($instructions, new ClaimExperiencePreviewOptions(
        issuer: $issuer,
        baseUrl: 'http://x-change-sandbox.test',
        dryRun: true,
    ));

    expect($cached['cache_hit'])->toBeTrue()
        ->and($cached['fingerprint'])->toBe($result['fingerprint'])
        ->and(data_get($cached, 'journey.steps.0.screen.code'))->toBe('AA317');
});

it('changes preview artifact identity when the render contract changes', function (): void {
    $fingerprints = app(ClaimPreviewArtifactFingerprint::class);
    $scenario = ['key' => 'sample-claim', 'version' => 1];

    $current = $fingerprints->make($scenario, ['render_mode' => 'compiled_claim_screen_v2']);
    $previous = $fingerprints->make($scenario, ['render_mode' => 'compiled_claim_screen_v1']);
    config()->set('form-flow.presentation.profiles.guided-claim-v1.appearance.mode', 'light');
    $newPresentation = $fingerprints->make($scenario, ['render_mode' => 'compiled_claim_screen_v2']);

    expect($current['payload']['sample_code'])->toBe('AA317')
        ->and($current['fingerprint'])->not->toBe($previous['fingerprint'])
        ->and($current['fingerprint'])->not->toBe($newPresentation['fingerprint']);
});

it('compiles conditional redeemer journey steps from the instruction contract', function (): void {
    $issuer = actingAsTestUser();
    $instructions = validVoucherInstructions(25.00, overrides: [
        'inputs' => [
            'fields' => ['mobile', 'kyc', 'otp', 'selfie', 'signature', 'location'],
        ],
        'validation' => [
            'signature' => [
                'required' => true,
                'on_failure' => 'block',
            ],
            'location' => [
                'required' => true,
                'target_lat' => 14.5995,
                'target_lng' => 120.9842,
                'radius_meters' => 100,
                'on_failure' => 'block',
            ],
        ],
        'rider' => [
            'splash' => '<section>Welcome</section>',
            'message' => 'Thank you.',
            'url' => 'https://example.test/after',
        ],
    ]);

    $result = app(ClaimExperiencePreviewService::class)->renderFromInstructions(
        $instructions,
        new ClaimExperiencePreviewOptions(
            issuer: $issuer,
            baseUrl: 'http://x-change-sandbox.test',
            dryRun: true,
            refresh: true,
        ),
    );

    $steps = collect(data_get($result, 'journey.steps'));

    expect(collect(data_get($result, 'journey.steps.0.screen.xray.requirements'))->pluck('key')->all())
        ->toContain('mobile', 'kyc', 'selfie', 'signature', 'location');

    expect($steps->pluck('key')->all())
        ->toContain(
            'claim-entry',
            'pre-claim-rider-splash',
            'form-flow-01-form',
            'form-flow-02-kyc',
            'form-flow-03-otp',
            'form-flow-04-location',
            'form-flow-05-selfie',
            'form-flow-06-signature',
            'confirmation',
            'claim-success-rider-message',
            'rider-redirect-countdown',
            'rider-url',
        )
        ->not->toContain('og-social-preview', 'xray-preview')
        ->and($steps->pluck('sequence')->all())
        ->toBe(range(1, $steps->count()));

    expect($steps->where('phase', 'form_flow')->pluck('screen.component')->all())
        ->toBe([
            'form-flow/core/GenericForm',
            'form-flow/kyc/KYCInitiatePage',
            'form-flow/otp/OtpCapturePage',
            'form-flow/location/LocationCapturePage',
            'form-flow/selfie/SelfieCapturePage',
            'form-flow/signature/SignatureCapturePage',
        ])
        ->and($steps->where('phase', 'form_flow')->every(
            fn (array $step): bool => data_get($step, 'screen.props.preview_mode') === true,
        ))->toBeTrue();
});

it('projects explicit prepaid completion states without fabricating live outcomes', function (): void {
    $issuer = actingAsTestUser();
    $voucherCountBefore = Voucher::query()->count();
    $instructions = validVoucherInstructions(50.00, overrides: [
        'claim' => [
            'outcomes' => [
                ['key' => 'envelope_completion'],
            ],
            'default_outcome' => 'envelope_completion',
        ],
        'execution' => [
            'schema' => 'voucher.execution.v1',
            'driver' => CampaignCoverageCompletionExecutionDriver::Key,
            'metadata' => [
                'completion' => [
                    'schema' => 'x-change.campaign-coverage-completion.v1',
                    'coverage_reference' => 'coverage-preview',
                    'envelope_reference' => 'envelope-preview',
                    'driver_id' => 'aui.personal-accident.provisional-cover',
                    'driver_version' => '1.0.0',
                ],
            ],
        ],
    ]);
    $service = app(ClaimExperiencePreviewService::class);

    $processing = $service->renderFromInstructions(
        $instructions,
        new ClaimExperiencePreviewOptions(
            issuer: $issuer,
            baseUrl: 'http://x-change-sandbox.test',
            dryRun: true,
            refresh: true,
            progressState: ClaimPreviewProgressState::Processing,
        ),
    );
    $ready = $service->renderFromInstructions(
        $instructions,
        new ClaimExperiencePreviewOptions(
            issuer: $issuer,
            baseUrl: 'http://x-change-sandbox.test',
            dryRun: true,
            refresh: true,
            progressState: ClaimPreviewProgressState::Ready,
        ),
    );

    expect(data_get($processing, 'journey.simulation.state'))->toBe('processing')
        ->and(data_get($processing, 'journey.simulation.verified_live_outcome'))->toBeFalse()
        ->and(data_get($processing, 'journey.simulation.workflow_key'))->toBe('campaign.coverage-completion.v1')
        ->and(data_get($processing, 'journey.steps.'.(count(data_get($processing, 'journey.steps')) - 1).'.screen.kind'))
        ->toBe('claim_progress')
        ->and(data_get($processing, 'journey.steps.'.(count(data_get($processing, 'journey.steps')) - 1).'.screen.progress_state'))
        ->toBe('processing')
        ->and(data_get($ready, 'journey.simulation.state'))->toBe('ready')
        ->and($ready['fingerprint'])->not->toBe($processing['fingerprint'])
        ->and(Voucher::query()->count())->toBe($voucherCountBefore);
});

it('deletes only temporary preview vouchers after capture', function (): void {
    actingAsTestUser();

    $preview = issueVoucher(validVoucherInstructions(10.00, overrides: [
        'metadata' => [
            'custom' => [
                'walkthrough' => [
                    'preview' => true,
                ],
            ],
        ],
    ]));
    $regular = issueVoucher(validVoucherInstructions(11.00));

    app(ClaimPreviewVoucherDisposer::class)->dispose($preview->getKey());
    app(ClaimPreviewVoucherDisposer::class)->dispose($regular->getKey());

    expect($preview->fresh())->toBeNull()
        ->and($regular->fresh())->not->toBeNull();
});

it('preserves the preview-only marker through real issuance and cleanup', function (): void {
    $issuer = actingAsTestUser();
    $instructions = validVoucherInstructions(10.00);
    $payload = app(ClaimPreviewVoucherPayloadFactory::class)->make(
        $instructions,
        $issuer,
    );

    $issued = app(PayCodeIssuanceContract::class)->issue($issuer, $payload);
    $voucher = Voucher::query()->findOrFail(
        $issued['voucher_id'],
    );

    expect(data_get(
        $voucher->metadata,
        'instructions.metadata.custom.walkthrough.preview',
    ))->toBeTrue();

    app(ClaimPreviewVoucherDisposer::class)->dispose($voucher->getKey());

    expect($voucher->fresh())->toBeNull();
});

it('issues a temporary preview voucher without moving issuer funds', function (): void {
    Event::fake([VouchersGenerated::class]);
    $issuer = actingAsTestUser(0);
    $wallet = $issuer->wallet;
    $payload = app(ClaimPreviewVoucherPayloadFactory::class)->make(
        validVoucherInstructions(25.00),
        $issuer,
    );

    $issued = app(ClaimPreviewVoucherIssuer::class)->issue($issuer, $payload);
    $voucher = Voucher::query()->findOrFail($issued['voucher_id']);

    expect((int) $wallet->refresh()->balanceInt)->toBe(0)
        ->and($voucher->cash)->toBeNull()
        ->and(data_get($voucher->metadata, 'instructions.cash.amount'))->toBe(25)
        ->and(data_get(
            $voucher->metadata,
            'instructions.metadata.custom.walkthrough.preview',
        ))->toBeTrue();

    Event::assertNotDispatched(VouchersGenerated::class);

    app(ClaimPreviewVoucherDisposer::class)->dispose($voucher->getKey());

    expect($voucher->fresh())->toBeNull();
});
