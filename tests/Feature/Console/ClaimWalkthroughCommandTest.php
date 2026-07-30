<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\ClaimWalkthrough\ClaimWalkthroughQaMatrix;
use LBHurtado\XChange\ClaimWalkthrough\ClaimWalkthroughScenarioRepository;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;

it('lists available claim walkthrough scenarios', function (): void {
    $this->artisan('xchange:claim-walkthrough', [
        '--list' => true,
    ])
        ->expectsOutputToContain('claim_basic_no_rider')
        ->expectsOutputToContain('claim_basic_15_no_inputs_no_riders_no_feedbacks')
        ->expectsOutputToContain('claim_basic_15_preview_with_rider')
        ->expectsOutputToContain('claim_fake_kyc_handler_preview')
        ->expectsOutputToContain('claim_fake_otp_handler_preview')
        ->expectsOutputToContain('claim_mocked_location_handler_preview')
        ->expectsOutputToContain('claim_mocked_selfie_handler_preview')
        ->expectsOutputToContain('claim_named_three_slices_preview')
        ->expectsOutputToContain('claim_paynamics_approval_walkthrough')
        ->expectsOutputToContain('claim_signature_handler_preview')
        ->assertSuccessful();
});

it('outputs a safe public claim storyboard qa matrix', function (): void {
    $this->artisan('xchange:claim-walkthrough', [
        '--qa-matrix' => true,
        '--json' => true,
    ])
        ->expectsOutputToContain('"schema": "x-change.claim-walkthrough.qa-matrix.v1"')
        ->assertSuccessful();

    $payload = app(ClaimWalkthroughQaMatrix::class)->report(app(ClaimWalkthroughScenarioRepository::class));
    $entries = collect($payload['entries']);
    $commands = $entries
        ->pluck('command')
        ->filter()
        ->implode(' ');

    expect($payload['schema'])->toBe('x-change.claim-walkthrough.qa-matrix.v1')
        ->and($payload['boundary']['surface'])->toBe('public_claim_and_form_flow')
        ->and($payload['boundary']['cockpit'])->toBeFalse()
        ->and($payload['boundary']['money_movement'])->toBeFalse()
        ->and($payload['boundary']['submit_claim'])->toBeFalse()
        ->and($payload['recommended_options'])->toBe([
            '--dry-run',
            '--preview-cache',
            '--profile=qa',
            '--json',
        ])
        ->and($entries->pluck('scenario')->all())->toContain(
            'claim_basic_no_rider',
            'claim_basic_15_no_inputs_no_riders_no_feedbacks',
            'claim_basic_15_preview_with_rider',
            'claim_fake_kyc_handler_preview',
            'claim_fake_otp_handler_preview',
            'claim_mocked_location_handler_preview',
            'claim_mocked_selfie_handler_preview',
            'claim_named_three_slices_preview',
            'claim_paynamics_approval_walkthrough',
            'claim_signature_handler_preview',
        )
        ->and($entries->pluck('status')->all())->not->toContain('planned')
        ->and($commands)->toContain('--dry-run')
        ->and($commands)->toContain('--preview-cache')
        ->and($commands)->toContain('--profile=qa')
        ->and($commands)->not->toContain('--submit-claim')
        ->and($commands)->not->toContain('/x/cockpit')
        ->and($entries->pluck('money_movement')->contains(true))->toBeFalse();
});

it('renders a no money qa batch index for available matrix scenarios', function (): void {
    $runId = 'claim-qa-batch-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        '--qa-batch' => true,
        '--json' => true,
        '--profile' => 'qa',
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
        '--refresh-preview' => true,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $entries = collect($payload['entries']);
    $scenarios = $entries->pluck('scenario')->all();

    expect($payload['schema'])->toBe('x-change.claim-walkthrough.qa-batch.v1')
        ->and($payload['dry_run'])->toBeTrue()
        ->and($payload['profile'])->toBe('qa')
        ->and($payload['boundary']['cockpit'])->toBeFalse()
        ->and($payload['boundary']['money_movement'])->toBeFalse()
        ->and($payload['boundary']['submit_claim'])->toBeFalse()
        ->and($payload['review_checklist'])->toContain('Frames represent meaningful visible states without duplicate-looking steps.')
        ->and($payload['review_checklist'])->toContain('No provider call, Cockpit route, or real money movement appears in this preview.')
        ->and($payload['entry_count'])->toBe(10)
        ->and($scenarios)->toContain(
            'claim_basic_no_rider',
            'claim_basic_15_no_inputs_no_riders_no_feedbacks',
            'claim_basic_15_preview_with_rider',
            'claim_fake_kyc_handler_preview',
            'claim_fake_otp_handler_preview',
            'claim_mocked_location_handler_preview',
            'claim_mocked_selfie_handler_preview',
            'claim_named_three_slices_preview',
            'claim_paynamics_approval_walkthrough',
            'claim_signature_handler_preview',
        )
        ->and($entries->pluck('storyboard_html')->filter()->count())->toBe(10)
        ->and($entries->pluck('storyboard_pdf')->filter()->count())->toBe(10)
        ->and($entries->pluck('review.status')->unique()->values()->all())->toBe(['unreviewed'])
        ->and($entries->pluck('review.allowed_statuses')->flatten()->unique()->values()->all())->toBe([
            'pass',
            'needs_fix',
            'blocker',
        ])
        ->and($payload['artifacts']['index_json'])->toBeFile()
        ->and($payload['artifacts']['index_html'])->toBeFile()
        ->and($payload['artifacts']['review_markdown'])->toBeFile()
        ->and($payload['artifacts']['view_options']['default']['label'])->toBe('Default HTML index')
        ->and($payload['artifacts']['view_options']['default']['path'])->toBe($payload['artifacts']['index_html'])
        ->and($payload['artifacts']['view_options']['json']['path'])->toBe($payload['artifacts']['index_json'])
        ->and($payload['artifacts']['view_options']['review']['label'])->toBe('Markdown review worksheet')
        ->and($payload['artifacts']['view_options']['review']['path'])->toBe($payload['artifacts']['review_markdown'])
        ->and($payload['artifacts']['view_options']['folder']['path'])->toBe($payload['artifacts']['root'])
        ->and($payload['artifacts']['view_options']['default']['open_command'])->toStartWith('open ')
        ->and(file_get_contents($payload['artifacts']['index_html']))->toContain('Claim Storyboard QA Batch')
        ->and(file_get_contents($payload['artifacts']['index_html']))->toContain('Open Markdown review worksheet')
        ->and(file_get_contents($payload['artifacts']['index_html']))->toContain('Reviewer status')
        ->and(file_get_contents($payload['artifacts']['index_html']))->toContain('needs fix')
        ->and(file_get_contents($payload['artifacts']['index_html']))->toContain('Review checklist')
        ->and(file_get_contents($payload['artifacts']['index_html']))->toContain('No provider call, Cockpit route, or real money movement appears')
        ->and(file_get_contents($payload['artifacts']['review_markdown']))->toContain('# Claim Storyboard QA Review')
        ->and(file_get_contents($payload['artifacts']['review_markdown']))->toContain('Allowed reviewer statuses: pass, needs_fix, blocker')
        ->and(file_get_contents($payload['artifacts']['review_markdown']))->toContain('- [ ] needs_fix')
        ->and(file_get_contents($payload['artifacts']['index_html']))->toContain('claim_signature_handler_preview')
        ->and(json_encode($payload, JSON_UNESCAPED_SLASHES))->not->toContain('--submit-claim')
        ->and(json_encode($payload, JSON_UNESCAPED_SLASHES))->not->toContain('/x/cockpit');
});

it('summarizes qa review worksheets and writes an acceptance report', function (): void {
    $runId = 'claim-qa-review-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        '--qa-batch' => true,
        '--json' => true,
        '--profile' => 'qa',
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
        '--refresh-preview' => true,
    ]);

    expect($exitCode)->toBe(0);

    $batch = json_decode(Artisan::output(), true);
    $worksheet = $batch['artifacts']['review_markdown'];
    $reviewed = file_get_contents($worksheet);
    $reviewed = preg_replace('/- \[ \] pass/', '- [x] pass', $reviewed, 1);
    $reviewed = preg_replace('/- \[ \] needs_fix/', '- [x] needs_fix', (string) $reviewed, 2);
    file_put_contents($worksheet, $reviewed);

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        '--qa-review' => $worksheet,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0);

    $summary = json_decode(Artisan::output(), true);

    expect($summary['schema'])->toBe('x-change.claim-walkthrough.qa-review-summary.v1')
        ->and($summary['entry_count'])->toBe(10)
        ->and($summary['counts']['pass'])->toBe(1)
        ->and($summary['counts']['needs_fix'])->toBe(1)
        ->and($summary['counts']['blocker'])->toBe(0)
        ->and($summary['counts']['unreviewed'])->toBe(8)
        ->and($summary['accepted'])->toBeFalse()
        ->and($summary['artifacts']['review_summary_json'])->toBeFile()
        ->and(file_get_contents($summary['artifacts']['review_summary_json']))->toContain('x-change.claim-walkthrough.qa-review-summary.v1');

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        '--qa-acceptance' => $worksheet,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0);

    $acceptance = json_decode(Artisan::output(), true);

    expect($acceptance['schema'])->toBe('x-change.claim-walkthrough.qa-acceptance.v1')
        ->and($acceptance['report_markdown'])->toBeFile()
        ->and(file_get_contents($acceptance['report_markdown']))->toContain('# Claim UX Acceptance Report')
        ->and(file_get_contents($acceptance['report_markdown']))->toContain('P1: Polish needs_fix lanes')
        ->and(file_get_contents($acceptance['report_markdown']))->toContain('P2: Complete human review')
        ->and(file_get_contents($acceptance['report_markdown']))->toContain('| Priority | Scenario | Status | HTML | PDF | Notes |')
        ->and(file_get_contents($acceptance['report_markdown']))->toContain('## Reviewer Notes By Status');
});

it('diffs two qa batch manifests by scenario artifact fingerprint and storyboard paths', function (): void {
    $runId = 'claim-qa-diff-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        '--qa-batch' => true,
        '--json' => true,
        '--profile' => 'qa',
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
        '--refresh-preview' => true,
    ]);

    expect($exitCode)->toBe(0);

    $from = json_decode(Artisan::output(), true);
    $to = $from;
    $to['run_id'] = $runId.'-changed';
    $to['entries'][0]['artifact_fingerprint'] = 'changed-fingerprint';
    $to['entries'][0]['storyboard_html'] = '/tmp/changed-storyboard.html';
    $toPath = dirname($from['artifacts']['index_json']).'/claim-walkthrough-qa-batch-changed.json';
    file_put_contents($toPath, json_encode($to, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        '--qa-diff-from' => $from['artifacts']['index_json'],
        '--qa-diff-to' => $toPath,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0);

    $diff = json_decode(Artisan::output(), true);
    $changed = collect($diff['entries'])->firstWhere('status', 'changed');

    expect($diff['schema'])->toBe('x-change.claim-walkthrough.qa-diff.v1')
        ->and($diff['counts']['changed'])->toBe(1)
        ->and($diff['counts']['unchanged'])->toBe(9)
        ->and($diff['artifacts']['diff_markdown'])->toBeFile()
        ->and(file_get_contents($diff['artifacts']['diff_markdown']))->toContain('# Claim UX QA Diff Report')
        ->and(file_get_contents($diff['artifacts']['diff_markdown']))->toContain('artifact_fingerprint_changed')
        ->and($changed['reasons'])->toContain('artifact_fingerprint_changed')
        ->and($changed['reasons'])->toContain('storyboard_html_changed');
});

it('scaffolds a no money redeemer otp handler walkthrough', function (): void {
    $runId = 'claim-fake-otp-handler-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_fake_otp_handler_preview',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $storyboard = json_decode(file_get_contents($payload['artifacts']['storyboard_json']), true);
    $keys = collect($storyboard['checkpoints'])->pluck('key')->all();

    expect($payload['scenario'])->toBe('claim_fake_otp_handler_preview')
        ->and($storyboard['scenario']['fixture']['money_movement'])->toBeFalse()
        ->and($storyboard['scenario']['fixture']['handlers']['otp'])->toBeTrue()
        ->and($storyboard['scenario']['fixture']['validation']['otp']['purpose'])->toBe('redeemer_mobile_verification')
        ->and($storyboard['scenario']['description'])->toContain('not Paynamics issuer payout OTP')
        ->and($keys)->toContain('validation-otp')
        ->and(collect($storyboard['checkpoints'])->firstWhere('key', 'validation-otp')['expected'])
        ->toContain('must not resemble Paynamics issuer OTP approval');
});

it('scaffolds a no money kyc handler walkthrough', function (): void {
    $runId = 'claim-fake-kyc-handler-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_fake_kyc_handler_preview',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $storyboard = json_decode(file_get_contents($payload['artifacts']['storyboard_json']), true);
    $keys = collect($storyboard['checkpoints'])->pluck('key')->all();

    expect($payload['scenario'])->toBe('claim_fake_kyc_handler_preview')
        ->and($storyboard['scenario']['fixture']['money_movement'])->toBeFalse()
        ->and($storyboard['scenario']['fixture']['handlers']['kyc'])->toBeTrue()
        ->and($storyboard['scenario']['fixture']['validation']['kyc']['purpose'])->toBe('redeemer_identity_verification')
        ->and($storyboard['scenario']['description'])->toContain('identity verification copy')
        ->and($keys)->toContain('validation-kyc')
        ->and(collect($storyboard['checkpoints'])->firstWhere('key', 'validation-kyc')['expected'])
        ->toContain('loading, retry, and continue states');
});

it('scaffolds a no money mocked location handler walkthrough', function (): void {
    $runId = 'claim-mocked-location-handler-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_mocked_location_handler_preview',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $storyboard = json_decode(file_get_contents($payload['artifacts']['storyboard_json']), true);
    $keys = collect($storyboard['checkpoints'])->pluck('key')->all();

    expect($payload['scenario'])->toBe('claim_mocked_location_handler_preview')
        ->and($storyboard['scenario']['fixture']['money_movement'])->toBeFalse()
        ->and($storyboard['scenario']['fixture']['handlers']['location'])->toBeTrue()
        ->and($storyboard['scenario']['fixture']['validation']['location']['purpose'])->toBe('redeemer_location_verification')
        ->and($storyboard['scenario']['fixture']['validation']['location']['mocked'])->toBeTrue()
        ->and($storyboard['scenario']['description'])->toContain('location permission')
        ->and($keys)->toContain('validation-location')
        ->and(collect($storyboard['checkpoints'])->firstWhere('key', 'validation-location')['expected'])
        ->toContain('permission guidance, retry controls, and a map surface');
});

it('scaffolds a no money mocked selfie handler walkthrough', function (): void {
    $runId = 'claim-mocked-selfie-handler-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_mocked_selfie_handler_preview',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $storyboard = json_decode(file_get_contents($payload['artifacts']['storyboard_json']), true);
    $keys = collect($storyboard['checkpoints'])->pluck('key')->all();

    expect($payload['scenario'])->toBe('claim_mocked_selfie_handler_preview')
        ->and($storyboard['scenario']['fixture']['money_movement'])->toBeFalse()
        ->and($storyboard['scenario']['fixture']['handlers']['selfie'])->toBeTrue()
        ->and($storyboard['scenario']['fixture']['validation']['selfie']['purpose'])->toBe('redeemer_selfie_verification')
        ->and($storyboard['scenario']['fixture']['validation']['selfie']['mocked'])->toBeTrue()
        ->and($storyboard['scenario']['description'])->toContain('camera permission')
        ->and($keys)->toContain('validation-selfie')
        ->and(collect($storyboard['checkpoints'])->firstWhere('key', 'validation-selfie')['expected'])
        ->toContain('large selfie preview, retake control, and continue action');
});

it('scaffolds a no money signature handler walkthrough', function (): void {
    $runId = 'claim-signature-handler-test-'.strtolower(str()->random(8));

    $exitCode = Artisan::call('xchange:claim-walkthrough', [
        'scenario' => 'claim_signature_handler_preview',
        '--dry-run' => true,
        '--json' => true,
        '--base-url' => 'http://x-change-sandbox.test',
        '--run-id' => $runId,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $storyboard = json_decode(file_get_contents($payload['artifacts']['storyboard_json']), true);
    $keys = collect($storyboard['checkpoints'])->pluck('key')->all();

    expect($payload['scenario'])->toBe('claim_signature_handler_preview')
        ->and($storyboard['scenario']['fixture']['money_movement'])->toBeFalse()
        ->and($storyboard['scenario']['fixture']['handlers']['signature'])->toBeTrue()
        ->and($storyboard['scenario']['fixture']['validation']['signature']['purpose'])->toBe('redeemer_signature_capture')
        ->and($storyboard['scenario']['description'])->toContain('signature pad sizing')
        ->and($keys)->toContain('validation-signature')
        ->and(collect($storyboard['checkpoints'])->firstWhere('key', 'validation-signature')['expected'])
        ->toContain('signature pad large enough to sign comfortably');
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
        ->and(data_get($artifact?->metadata, 'renderer.dry_run'))->toBeTrue()
        ->and(data_get($artifact?->metadata, 'fingerprint_payload'))->toBeNull();

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
        ->and(data_get($artifact?->metadata, 'fingerprint_payload'))->toBeNull();
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
