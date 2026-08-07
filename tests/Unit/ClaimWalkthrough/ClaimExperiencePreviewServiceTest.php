<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewOptions;
use LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewService;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewVoucherDisposer;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewVoucherIssuer;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewVoucherPayloadFactory;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
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
        ->and(data_get($result, 'journey.schema'))->toBe('x-change.claim-experience-preview.journey.v1')
        ->and(data_get($result, 'journey.steps.0.key'))->toBe('claim-entry')
        ->and(collect(data_get($result, 'journey.steps'))->pluck('key')->all())
        ->not->toContain('og-social-preview')
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

it('compiles conditional redeemer journey steps from the instruction contract', function (): void {
    $issuer = actingAsTestUser();
    $instructions = validVoucherInstructions(25.00, overrides: [
        'inputs' => [
            'fields' => ['mobile'],
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
        'metadata' => [
            'custom' => [
                'claim_experience' => [
                    'handlers' => [
                        'kyc' => true,
                        'otp' => true,
                        'selfie' => true,
                    ],
                ],
            ],
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

    expect($steps->pluck('key')->all())
        ->toContain(
            'claim-entry',
            'pre-claim-rider-splash',
            'validation-kyc',
            'validation-otp',
            'validation-selfie',
            'validation-signature',
            'validation-location',
            'confirmation',
            'claim-success-rider-message',
            'rider-redirect-countdown',
            'rider-url',
        )
        ->not->toContain('og-social-preview', 'xray-preview')
        ->and($steps->pluck('sequence')->all())
        ->toBe(range(1, $steps->count()));
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

    app(ClaimPreviewVoucherDisposer::class)->dispose($voucher->getKey());

    expect($voucher->fresh())->toBeNull();
});
