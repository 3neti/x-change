<?php

declare(strict_types=1);

use LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewOptions;
use LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewService;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;

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
        ->and(data_get($result, 'artifacts.storyboard_pdf'))->toBeFile()
        ->and(data_get($result, 'artifacts.storyboard_html'))->toBeFile()
        ->and(data_get($result, 'artifacts.view_options.default.label'))->toBe('Default PDF');

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
        ->and($cached['fingerprint'])->toBe($result['fingerprint']);
});
