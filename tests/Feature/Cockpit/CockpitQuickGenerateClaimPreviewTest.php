<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;

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
        ->assertJsonPath('journey.steps.0.screen.code', 'PREVIEW');

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
    File::put($absoluteRoot.'/walkthrough-storyboard.html', '<!doctype html><title>Preview</title>');

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
        'x-change.cockpit.quick-generate.claim-previews.exports.show',
        [$artifact, 'format' => 'pdf'],
    ))->assertNotFound();
});
