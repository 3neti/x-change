<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use LBHurtado\FormFlowManager\Handlers\SplashHandler;
use LBHurtado\FormHandlerKYC\KYCHandler;
use LBHurtado\FormHandlerLocation\LocationHandler;
use LBHurtado\FormHandlerOtp\OtpHandler;
use LBHurtado\FormHandlerSelfie\SelfieHandler;
use LBHurtado\FormHandlerSignature\SignatureHandler;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;

beforeEach(function (): void {
    config()->set('x-change.legal.eula.enabled', false);
    config()->set('form-flow.handlers', [
        'splash' => SplashHandler::class,
        'kyc' => KYCHandler::class,
        'location' => LocationHandler::class,
        'otp' => OtpHandler::class,
        'selfie' => SelfieHandler::class,
        'signature' => SignatureHandler::class,
    ]);
});

it('exposes the claim preview contract on the quick generate read model', function (): void {
    expect(Route::has('x-change.cockpit.quick-generate.claim-previews.store'))->toBeTrue();

    $readModel = app(CockpitReadModelProviderContract::class)
        ->forQuickGenerate(new CockpitReadModelQueryData);

    expect($readModel->claim_preview_contract->schema)->toBe('x-change.cockpit.quick-generate-claim-preview.v1')
        ->and($readModel->claim_preview_contract->status)->toBe('registered')
        ->and($readModel->claim_preview_contract->route)->toBe('x-change.cockpit.quick-generate.claim-previews.store')
        ->and($readModel->claim_preview_contract->route_url)->toBe('/x/cockpit/quick-generate/claim-previews')
        ->and($readModel->claim_preview_contract->money_movement)->toBeFalse();
});

it('renders a dry-run claim preview from quick generate instructions', function (): void {
    actingAsTestUser();

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->post(route('x-change.cockpit.quick-generate.claim-previews.store'), [
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => null,
        ],
        'rider' => [
            'message' => 'Issuer preview message.',
            'url' => 'https://example.test/rider',
            'splash' => '<section>Issuer preview splash</section>',
            'redirect_timeout' => 4,
            'og_source' => 'message',
        ],
        'dry_run' => true,
        'refresh_preview' => true,
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('schema', 'x-change.claim-experience-preview.manifest.v1')
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('safety.preview_only', true)
        ->assertJsonPath('safety.money_movement', false)
        ->assertJsonPath('safety.provider_calls', false)
        ->assertJsonPath('safety.claim_submission', false)
        ->assertJsonPath('cache_hit', false)
        ->assertJsonPath('journey.viewport.profile', 'mobile_claim_v1')
        ->assertJsonPath('journey.viewport.width', 360)
        ->assertJsonPath('journey.viewport.height', 720)
        ->assertJsonPath('journey.steps.0.key', 'claim-entry');

    $body = $response->getContent();

    expect($body)
        ->not->toContain('/Users/')
        ->not->toContain('storage/app')
        ->not->toContain('file://')
        ->not->toContain('open_command')
        ->not->toContain('pay_code')
        ->not->toContain('09173011987');
});

it('shows a payable payment page instead of the disbursement claim journey', function (): void {
    $issuer = actingAsTestUser();
    $response = $this->postJson(route('x-change.cockpit.quick-generate.claim-previews.store'), [
        'cash' => ['amount' => 0, 'currency' => 'PHP'], 'inputs' => ['fields' => []],
        'feedback' => ['mobile' => null], 'rider' => ['message' => 'Payment for services'],
        'voucher_type' => 'payable', 'target_amount' => 100, 'fee_bearer' => 'issuer',
        'metadata' => ['collection_wallet_id' => (string) $issuer->wallet->id],
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default',
            'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']],
        'dry_run' => true, 'refresh_preview' => true,
    ]);
    $response->assertOk()->assertJsonPath('journey.step_count', 1)
        ->assertJsonPath('journey.steps.0.key', 'payment-page')
        ->assertJsonPath('journey.steps.0.actor', 'payer')
        ->assertJsonPath('journey.steps.0.screen.component', 'x-change/claim/Payment')
        ->assertJsonPath('journey.steps.0.screen.props.previewMode', true)
        ->assertJsonPath('journey.steps.0.screen.props.payment.target_amount_minor', 10000)
        ->assertJsonPath('journey.steps.0.screen.props.payment.amount_due_minor', 10000)
        ->assertJsonPath('journey.steps.0.screen.props.payment.can_create_attempt', false);
    expect($response->getContent())->not->toContain('09179999999');
});

it('shows the PhilHealth settlement phases in the generated claim preview', function (): void {
    $issuer = actingAsTestUser();
    $response = $this->postJson(route('x-change.cockpit.quick-generate.claim-previews.store'), [
        'cash' => ['amount' => 0, 'currency' => 'PHP'], 'inputs' => ['fields' => ['name']],
        'feedback' => ['mobile' => null], 'rider' => ['message' => null], 'voucher_type' => 'settlement', 'target_amount' => 100,
        'metadata' => ['collection_wallet_id' => (string) $issuer->wallet->id],
        'presentation' => ['share' => ['title' => 'Hospital settlement', 'image_source' => 'stamp']],
        'execution' => [
            'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
            'settlement' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable'],
            'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999'],
            'events' => [
                'attest' => ['actor' => 'beneficiary', 'surface' => 'claim', 'authentication' => 'anonymous', 'requires' => []],
                'review' => ['actor' => 'hospital_reviewer', 'surface' => 'claim', 'authentication' => 'required', 'requires' => ['attestation_recorded']],
                'settle' => ['actor' => 'payer', 'surface' => 'pay', 'authentication' => 'optional', 'requires' => ['settlement_ready', 'confirmed_payment']],
            ],
        ], 'dry_run' => true, 'refresh_preview' => true,
    ]);
    $response->assertOk();
    $steps = collect($response->json('journey.steps'))->keyBy('key');
    expect($steps->get('settlement-waiting')['screen']['props']['code'])->toBe('AA317')
        ->and($steps->get('settlement-review')['actor'])->toBe('Hospital')
        ->and($steps->get('settlement-payment-waiting')['actor'])->toBe('PhilHealth');
});

it('previews the PhilHealth instruction built by the cockpit form', function (): void {
    actingAsTestUser();
    $response = $this->postJson(route('x-change.cockpit.quick-generate.claim-previews.store'), [
        'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => [], 'fee_strategy' => 'absorb'],
        'fee_bearer' => 'issuer', 'provider' => 'netbank',
        'inputs' => ['fields' => ['signature'], 'requirements' => []], 'count' => 1,
        'feedback' => ['mobile' => null, 'email' => null, 'webhook' => null],
        'rider' => ['message' => null, 'message_format' => null, 'url' => null, 'redirect_timeout' => 0, 'splash' => null, 'splash_format' => null, 'splash_timeout' => 3, 'splash_meta' => null, 'og_source' => null],
        'validation' => [],
        'claim' => ['outcomes' => [['key' => 'provider_disbursement']], 'selection' => 'server', 'consumption' => 'one_of', 'default_outcome' => 'provider_disbursement', 'onboarding' => ['mode' => 'if_required'], 'claimant' => ['mode' => 'unbound'], 'profile' => 'voucher.claim.v1'],
        'metadata' => ['flow_type' => 'settlement', 'custom' => ['cockpit' => ['template_key' => 'blank-pay-code', 'source' => 'cockpit.quick-generate', 'builder' => 'guided-voucher-instruction-builder', 'payee' => ['kind' => 'open', 'explicit_secret' => false]]]],
        'voucher_type' => 'settlement', 'target_amount' => 20000,
        'presentation' => ['stamp' => ['design_id' => 'steampunk', 'design_version' => 8, 'show_logo' => true, 'show_tagline' => true, 'claim_marker' => 'qr']],
        'pricing' => ['quote_reference' => 'commercial-quote:'.str_repeat('a', 64), 'currency' => 'PHP', 'fee_bearer' => 'issuer', 'quote_hash' => str_repeat('a', 64)],
        'rules' => ['auto_close_on_full_payment' => true],
        'execution' => [
            'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
            'settlement' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable'],
            'events' => [
                'attest' => ['actor' => 'beneficiary', 'surface' => 'claim', 'authentication' => 'anonymous', 'requires' => []],
                'review' => ['actor' => 'hospital_reviewer', 'surface' => 'claim', 'authentication' => 'required', 'requires' => ['attestation_recorded']],
                'settle' => ['actor' => 'payer', 'surface' => 'pay', 'authentication' => 'optional', 'requires' => ['settlement_ready', 'confirmed_payment']],
            ],
            'value_flow' => ['destination' => 'instapay.gxchphm2xxx.account:09179999999'],
        ],
        'dry_run' => true, 'refresh_preview' => true,
    ]);
    $response->assertOk();
    expect(collect($response->json('journey.steps'))->pluck('key')->all())->toContain('settlement-waiting', 'settlement-review', 'settlement-payment-waiting');
});

it('rejects an unknown simulated claim progress state', function (): void {
    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
    ])->post(route('x-change.cockpit.quick-generate.claim-previews.store'), [
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['mobile' => null],
        'rider' => [
            'message' => null,
            'url' => null,
            'splash' => null,
        ],
        'preview_state' => 'fabricated_success',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('preview_state');
});

it('renders a recorded claim preview for an issuer with no available funds', function (): void {
    Process::fake([
        '*' => Process::result(output: json_encode([
            'status' => 'recorded',
        ], JSON_THROW_ON_ERROR)),
    ]);

    $issuer = actingAsTestUser(0);
    $voucherCountBefore = Voucher::query()->count();

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->post(route('x-change.cockpit.quick-generate.claim-previews.store'), [
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => null,
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'splash' => null,
        ],
        'refresh_preview' => true,
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('schema', 'x-change.claim-experience-preview.manifest.v1')
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('safety.money_movement', false);

    expect((int) $issuer->wallet->refresh()->balanceInt)->toBe(0)
        ->and(Voucher::query()->count())->toBe($voucherCountBefore);
});

it('falls back to a useful storyboard when browser capture is unavailable', function (): void {
    config()->set('x-change.claim_preview.recorder.mode', 'storyboard');
    Process::fake();

    $issuer = actingAsTestUser(0);
    $voucherCountBefore = Voucher::query()->count();

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->post(route('x-change.cockpit.quick-generate.claim-previews.store'), [
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => ['mobile'],
        ],
        'feedback' => [
            'mobile' => null,
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'splash' => null,
        ],
        'refresh_preview' => true,
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('schema', 'x-change.claim-experience-preview.manifest.v1')
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('safety.money_movement', false)
        ->assertJsonPath('journey.steps.0.render_kind', 'live_screen')
        ->assertJsonPath('journey.steps.0.status', 'rendered')
        ->assertJsonPath('journey.steps.0.screen.kind', 'claim_entry')
        ->assertJsonPath('journey.steps.0.screen.title', 'Claim Pay Code')
        ->assertJsonPath('journey.steps.0.screen.code', 'AA317')
        ->assertJsonPath('journey.steps.0.screen.xray.audience', 'guest')
        ->assertJsonPath('journey.steps.0.screen.xray.status', 'claimable')
        ->assertJsonPath('journey.steps.0.screen.fields.0.value', 'AA317');

    $disclosures = collect($response->json('journey.steps.0.screen.xray.disclosures'))->keyBy('key');

    expect(data_get($disclosures->get('reference'), 'value'))->toBe('AA317')
        ->and((string) data_get($disclosures->get('amount'), 'value'))->toContain('25.00');

    $screens = collect($response->json('journey.steps'))
        ->where('phase', 'form_flow');

    expect($screens)->not->toBeEmpty()
        ->and($screens->every(
            fn (array $step): bool => data_get($step, 'render_kind') === 'actual_screen'
                && data_get($step, 'screen.props.preview_mode') === true,
        ))->toBeTrue();

    expect((int) $issuer->wallet->refresh()->balanceInt)->toBe(0)
        ->and(Voucher::query()->count())->toBe($voucherCountBefore);

    Process::assertNothingRan();
});

it('serves claim preview manifests and frames only to their owner', function (): void {
    $owner = actingAsTestUser();
    $root = 'x-change/claim-previews/test-owned-frame';
    $absoluteRoot = storage_path('app/'.$root);
    File::ensureDirectoryExists($absoluteRoot.'/screenshots');
    $bytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );
    File::put($absoluteRoot.'/screenshots/01-claim-entry.png', $bytes);
    File::put($absoluteRoot.'/walkthrough-storyboard.pdf', '%PDF-1.4 test');
    File::put($absoluteRoot.'/claim-walkthrough-storyboard.html', '<!doctype html><title>Preview</title>');

    $artifact = ClaimPreviewArtifact::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'artifact_fingerprint' => hash('sha256', 'owned-frame'),
        'scenario_key' => 'claim_instructions_preview',
        'scenario_version' => 1,
        'profile' => 'issuer',
        'status' => 'ready',
        'artifact_disk' => 'local',
        'artifact_path' => $root,
        'metadata' => [
            'journey' => [
                'schema' => 'x-change.claim-experience-preview.journey.v1',
                'step_count' => 1,
                'steps' => [[
                    'sequence' => 1,
                    'key' => 'claim-entry',
                    'phase' => 'entry',
                    'title' => 'Claim entry',
                    'description' => 'Redeemer opens the Pay Code.',
                    'actor' => 'redeemer',
                    'render_kind' => 'captured_frame',
                    'status' => 'captured',
                    'frame' => [
                        'artifact' => 'screenshots/01-claim-entry.png',
                        'mime_type' => 'image/png',
                        'sha256' => hash('sha256', $bytes),
                        'width' => 1,
                        'height' => 1,
                    ],
                ]],
            ],
        ],
        'generated_at' => now(),
    ]);

    $this->get(route(
        'x-change.cockpit.quick-generate.claim-previews.show',
        $artifact,
    ))
        ->assertSuccessful()
        ->assertJsonPath('journey.viewport.width', 360)
        ->assertJsonPath('journey.viewport.height', 720)
        ->assertJsonPath(
            'journey.steps.0.preview_url',
            "/x/cockpit/quick-generate/claim-previews/{$artifact->reference}/steps/claim-entry",
        )
        ->assertJsonPath('journey.steps.0.frame.mime_type', 'image/png')
        ->assertJsonPath(
            'journey.steps.0.frame.url',
            "/x/cockpit/quick-generate/claim-previews/{$artifact->reference}/frames/claim-entry",
        );

    $frame = $this->get(route(
        'x-change.cockpit.quick-generate.claim-previews.frames.show',
        [$artifact, 'step' => 'claim-entry'],
    ));

    $frame
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/png')
        ->assertHeader('x-content-type-options', 'nosniff');
    expect(file_get_contents(
        $frame->baseResponse->getFile()->getPathname()
    ))->toBe($bytes);

    $stepResponse = $this->withHeader('X-Inertia', 'true')->get(route(
        'x-change.cockpit.quick-generate.claim-previews.steps.show',
        [$artifact, 'step' => 'claim-entry'],
    ));

    $stepResponse
        ->assertSuccessful()
        ->assertJsonPath('component', 'x-change/claim/Preview')
        ->assertJsonPath('props.viewport.profile', 'mobile_claim_v1')
        ->assertJsonPath('props.viewport.width', 360)
        ->assertJsonPath('props.viewport.height', 720)
        ->assertJsonPath('props.step.key', 'claim-entry')
        ->assertJsonPath(
            'props.step.frame.url',
            "/x/cockpit/quick-generate/claim-previews/{$artifact->reference}/frames/claim-entry",
        );

    expect($stepResponse->headers->get('cache-control'))
        ->toContain('no-store')
        ->toContain('private');

    $other = actingAsTestUser();
    expect($other->is($owner))->toBeFalse();

    $this->get(route(
        'x-change.cockpit.quick-generate.claim-previews.show',
        $artifact,
    ))->assertNotFound();
    $this->get(route(
        'x-change.cockpit.quick-generate.claim-previews.frames.show',
        [$artifact, 'step' => 'claim-entry'],
    ))->assertNotFound();
    $this->get(route(
        'x-change.cockpit.quick-generate.claim-previews.steps.show',
        [$artifact, 'step' => 'claim-entry'],
    ))->assertNotFound();
});

it('rejects unknown claim preview frames and exports', function (): void {
    $owner = actingAsTestUser();
    $artifact = ClaimPreviewArtifact::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'artifact_fingerprint' => hash('sha256', 'missing-frame'),
        'scenario_key' => 'claim_instructions_preview',
        'scenario_version' => 1,
        'profile' => 'issuer',
        'status' => 'ready',
        'artifact_disk' => 'local',
        'artifact_path' => 'x-change/claim-previews/missing-frame',
        'metadata' => ['journey' => ['steps' => []]],
        'generated_at' => now(),
    ]);

    $this->get(route(
        'x-change.cockpit.quick-generate.claim-previews.frames.show',
        [$artifact, 'step' => 'unknown'],
    ))->assertNotFound();
    $this->get(route(
        'x-change.cockpit.quick-generate.claim-previews.steps.show',
        [$artifact, 'step' => 'unknown'],
    ))->assertNotFound();
    $this->get(route(
        'x-change.cockpit.quick-generate.claim-previews.exports.show',
        [$artifact, 'format' => 'pdf'],
    ))->assertNotFound();
});

it('isolates artifact storage for identical designs owned by different cockpit issuers', function (): void {
    actingAsTestUser(0);
    $payload = [
        'cash' => ['amount' => 25, 'currency' => 'PHP'],
        'inputs' => ['fields' => []],
        'feedback' => ['mobile' => null],
        'rider' => ['message' => null, 'url' => null, 'splash' => null],
        'dry_run' => true,
    ];
    $first = $this->postJson(route('x-change.cockpit.quick-generate.claim-previews.store'), $payload)->assertSuccessful();
    actingAsTestUser(0);
    $second = $this->postJson(route('x-change.cockpit.quick-generate.claim-previews.store'), $payload)->assertSuccessful();

    expect($first->json('reference'))->not->toBe($second->json('reference'))
        ->and(ClaimPreviewArtifact::query()->pluck('artifact_path')->unique())->toHaveCount(2);
    $this->getJson(route('x-change.cockpit.quick-generate.claim-previews.show', $first->json('reference')))->assertNotFound();
});
