<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;

it('lists available claim walkthrough scenarios', function (): void {
    $this->artisan('xchange:claim-walkthrough', [
        '--list' => true,
    ])
        ->expectsOutputToContain('claim_basic_no_rider')
        ->expectsOutputToContain('claim_basic_15_no_inputs_no_riders_no_feedbacks')
        ->expectsOutputToContain('claim_basic_15_preview_with_rider')
        ->expectsOutputToContain('claim_named_three_slices_preview')
        ->expectsOutputToContain('claim_paynamics_approval_walkthrough')
        ->assertSuccessful();
});

it('scaffolds the basic fifteen peso no extras walkthrough', function (): void {
    $runId = 'claim-basic-15-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_basic_15_no_inputs_no_riders_no_feedbacks',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $storyboard = json_decode(file_get_contents($payload['artifacts']['storyboard_json']), true);

    expect($payload['scenario'])->toBe('claim_basic_15_no_inputs_no_riders_no_feedbacks')
        ->and($storyboard['scenario']['fixture']['amount'])->toBe('15.00')
        ->and($storyboard['scenario']['fixture']['rider_redirect'])->toBeFalse()
        ->and($storyboard['scenario']['fixture']['feedback'])->toBeFalse()
        ->and($storyboard['scenario']['fixture']['handlers']['otp'])->toBeFalse()
        ->and($storyboard['checkpoint_count'] ?? count($storyboard['checkpoints']))->toBe(3);
});

it('scaffolds a no money named three slices walkthrough', function (): void {
    $runId = 'claim-named-slices-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_named_three_slices_preview',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $storyboard = json_decode(file_get_contents($payload['artifacts']['storyboard_json']), true);
    $slices = data_get($storyboard, 'scenario.fixture.slices');

    expect($payload['scenario'])->toBe('claim_named_three_slices_preview')
        ->and(data_get($storyboard, 'scenario.fixture.amount'))->toBe('150.00')
        ->and($slices)->toHaveCount(3)
        ->and(collect($slices)->pluck('description')->all())->toBe([
            'Breakfast allowance',
            'Transport fare',
            'Snack budget',
        ])
        ->and(collect($slices)->sum(fn (array $slice): float => (float) $slice['amount']))->toBe(150.0);
});

it('creates non money movement walkthrough fixtures without funding allocation', function (): void {
    $user = actingAsTestUser(0);
    $runId = 'claim-no-money-fixture-test-'.strtolower(str()->random(8));
    $issuance = Mockery::mock(PayCodeIssuanceContract::class);

    $issuance
        ->shouldReceive('issue')
        ->once()
        ->withArgs(fn (mixed $issuer, array $payload): bool => $issuer->is($user)
            && data_get($payload, 'metadata.walkthrough.scenario') === 'claim_basic_15_no_inputs_no_riders_no_feedbacks')
        ->andReturn([
            'voucher_id' => 123,
            'code' => 'QA-UI15',
            'amount' => 15.00,
            'currency' => 'PHP',
            'links' => [
                'redeem' => 'http://x-change-sandbox.test/x/claim/QA-UI15',
                'redeem_path' => '/x/claim/QA-UI15',
            ],
        ]);

    app()->instance(PayCodeIssuanceContract::class, $issuance);

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_basic_15_no_inputs_no_riders_no_feedbacks',
        '--create-fixture' => true,
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--issuer' => (string) $user->id,
        '--run-id' => $runId,
    ]);

    expect($exitCode)->toBe(0);
});

it('creates dry run claim walkthrough artifacts in storage', function (): void {
    $runId = 'claim-walkthrough-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_paynamics_approval_walkthrough',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $root = storage_path('app/x-change/walkthroughs/'.$runId);

    expect($payload)
        ->toBeArray()
        ->and($payload['dry_run'])->toBeTrue()
        ->and($payload['artifacts']['root'])->toBe($root)
        ->and($payload['artifacts']['view_options']['default']['path'])->toBe($payload['artifacts']['storyboard_pdf'])
        ->and($payload['artifacts']['view_options']['html']['path'])->toBe($payload['artifacts']['storyboard_html'])
        ->and($payload['artifacts']['view_options']['folder']['path'])->toBe($payload['artifacts']['root'])
        ->and($payload['scenario'])->toBe('claim_paynamics_approval_walkthrough');

    expect($payload['artifacts']['storyboard_html'])->toBeFile()
        ->and($payload['artifacts']['storyboard_json'])->toBeFile()
        ->and($payload['artifacts']['storyboard_pdf'])->toBeFile()
        ->and($payload['artifacts']['report_json'])->toBeFile()
        ->and($payload['artifacts']['metadata_json'])->toBeFile()
        ->and($payload['artifacts']['action_log_jsonl'])->toBeFile();

    expect(file_get_contents($payload['artifacts']['storyboard_pdf']))
        ->toStartWith('%PDF-1.4');
});

it('caches deterministic no money claim preview artifacts', function (): void {
    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_basic_15_no_inputs_no_riders_no_feedbacks',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--preview-cache' => true,
        '--profile' => 'issuer',
        '--refresh-preview' => true,
    ]);

    expect($exitCode)->toBe(0);

    $firstPayload = json_decode(Artisan::output(), true);

    expect(data_get($firstPayload, 'cache.hit'))->toBeFalse()
        ->and(data_get($firstPayload, 'cache.artifact_fingerprint'))->toBeString()
        ->and(data_get($firstPayload, 'artifacts.view_options.default.label'))->toBe('Default PDF')
        ->and(data_get($firstPayload, 'artifacts.view_options.folder.open_command'))->toStartWith('open ');

    $artifact = ClaimPreviewArtifact::query()
        ->where('artifact_fingerprint', data_get($firstPayload, 'cache.artifact_fingerprint'))
        ->first();

    expect($artifact)->not->toBeNull()
        ->and($artifact?->profile)->toBe('issuer')
        ->and($artifact?->artifact_path)->toStartWith('x-change/claim-previews/')
        ->and(data_get($artifact?->metadata, 'fingerprint_payload.dry_run'))->toBeTrue();

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_basic_15_no_inputs_no_riders_no_feedbacks',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--preview-cache' => true,
        '--profile' => 'issuer',
    ]);

    expect($exitCode)->toBe(0);

    $secondPayload = json_decode(Artisan::output(), true);

    expect(data_get($secondPayload, 'cache.hit'))->toBeTrue()
        ->and(data_get($secondPayload, 'cache.artifact_fingerprint'))->toBe(data_get($firstPayload, 'cache.artifact_fingerprint'));
});

it('scaffolds the default rider preview fixture into the artifact contract', function (): void {
    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_basic_15_preview_with_rider',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--preview-cache' => true,
        '--profile' => 'issuer',
        '--refresh-preview' => true,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $storyboard = json_decode(file_get_contents($payload['artifacts']['storyboard_json']), true);
    $artifact = ClaimPreviewArtifact::query()
        ->where('artifact_fingerprint', data_get($payload, 'cache.artifact_fingerprint'))
        ->first();

    expect($storyboard['scenario']['fixture']['rider']['message'])->toBe('The quick brown fox jumps over the lazy dog.')
        ->and($storyboard['scenario']['fixture']['rider_splash'])->toBeTrue()
        ->and($storyboard['scenario']['fixture']['rider_redirect'])->toBeTrue()
        ->and($storyboard['scenario']['fixture']['rider']['url'])->toBe('https://open.spotify.com/track/6kyxQuFD38mo4S3urD2Wkw?si=6yq6W4oRQ76HGpbDCG-74w&utm_source=copy-link&rowId=35e1bf6b4faf0da8')
        ->and($storyboard['scenario']['fixture']['rider']['splash'])->toContain('planetary-rose.PNG')
        ->and($storyboard['scenario']['fixture']['og_preview']['source'])->toBe('default')
        ->and($storyboard['scenario']['fixture']['og_preview']['og_meta']['headline'])->toBe('{code}')
        ->and(collect($storyboard['checkpoints'])->pluck('key')->first())->toBe('og-social-preview')
        ->and(data_get($artifact?->metadata, 'fingerprint_payload.fixture.rider.message'))->toBe('The quick brown fox jumps over the lazy dog.');
});

it('allows claim preview rider defaults to be overridden through config', function (): void {
    config()->set('x-change.claim_preview.rider.message', 'Configured rider message.');
    config()->set('x-change.claim_preview.rider.url', 'https://example.test/configured-rider');
    config()->set('x-change.claim_preview.rider.splash_html', '<section>Configured rider splash</section>');
    config()->set('x-change.claim_preview.rider.redirect_timeout', 9);
    config()->set('x-change.claim_preview.rider.og_source', 'message');

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_basic_15_preview_with_rider',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--preview-cache' => true,
        '--profile' => 'issuer',
        '--refresh-preview' => true,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $storyboard = json_decode(file_get_contents($payload['artifacts']['storyboard_json']), true);

    expect($storyboard['scenario']['fixture']['rider']['message'])->toBe('Configured rider message.')
        ->and($storyboard['scenario']['fixture']['rider']['url'])->toBe('https://example.test/configured-rider')
        ->and($storyboard['scenario']['fixture']['rider']['splash'])->toBe('<section>Configured rider splash</section>')
        ->and($storyboard['scenario']['fixture']['rider']['redirect_timeout'])->toBe(9)
        ->and($storyboard['scenario']['fixture']['rider']['og_source'])->toBe('message')
        ->and($storyboard['scenario']['fixture']['og_preview']['source'])->toBe('message')
        ->and($storyboard['scenario']['fixture']['og_preview']['reference'])->toBe('rider.message');
});

it('does not cache money movement claim walkthroughs as preview artifacts', function (): void {
    $this->artisan('xchange:claim-walkthrough', [
        'scenario' => 'claim_basic_15_full_browser_handoff',
        '--dry-run' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--preview-cache' => true,
    ])
        ->expectsOutputToContain('Preview artifact caching is only available for no-money claim preview scenarios.')
        ->assertFailed();
});

it('scaffolds first class success and rider handoff frames', function (): void {
    $runId = 'claim-rider-handoff-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_basic_15_full_browser_handoff',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $storyboard = json_decode(file_get_contents($payload['artifacts']['storyboard_json']), true);
    $keys = collect($storyboard['checkpoints'])->pluck('key')->all();

    expect($keys)
        ->toContain('claim-success-rider-message')
        ->not->toContain('rider-message')
        ->not->toContain('post-claim-rider-splash')
        ->toContain('rider-redirect-countdown')
        ->toContain('rider-url');
});

it('rejects non local walkthrough base urls', function (): void {
    $this->artisan('xchange:claim-walkthrough', [
        '--dry-run' => true,
        '--base-url' => 'https://example.com',
    ])
        ->expectsOutputToContain('only run against local URLs')
        ->assertFailed();
});

it('rejects unknown claim walkthrough scenarios', function (): void {
    $this->artisan('xchange:claim-walkthrough', [
        'scenario' => 'missing_scenario',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Unknown claim walkthrough scenario')
        ->assertFailed();
});
