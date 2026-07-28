<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;
use LBHurtado\XChange\Data\Claim\RiderSplashArtworkSnapshotData;
use LBHurtado\XChange\Exceptions\RiderStampArtworkUnavailable;

beforeEach(function (): void {
    Storage::fake('local');
    Http::preventStrayRequests();
    config()->set(
        'x-change.claim.share.splash_artwork.allowed_hosts',
        ['raw.githubusercontent.com'],
    );
});

it('captures validated remote Splash artwork into private content-addressed storage', function (): void {
    $image = imagecreatetruecolor(8, 8);
    $red = imagecolorallocate($image, 244, 63, 94);
    imagefilledrectangle($image, 0, 0, 8, 8, $red);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    expect($contents)->toBeString()->not->toBeEmpty();

    Http::fake([
        'https://raw.githubusercontent.com/example/art/main/rose.png' => Http::response(
            $contents,
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $input = validVoucherInstructions(overrides: [
        'rider' => [
            'splash' => '<h2>A rose</h2><img src="https://github.com/example/art/blob/main/rose.png?raw=true">',
            'stamp' => [
                'version' => 2,
                'artwork_source' => 'splash',
            ],
        ],
    ])->toArray();
    $prepared = app(RiderSplashArtworkSnapshotterContract::class)
        ->prepare($input);
    $snapshot = RiderSplashArtworkSnapshotData::fromArray(data_get(
        $prepared,
        'metadata.custom.rider_splash_artwork',
    ));

    expect($snapshot)->toBeInstanceOf(RiderSplashArtworkSnapshotData::class)
        ->and($snapshot->mimeType)->toBe('image/png')
        ->and($snapshot->width)->toBe(8)
        ->and($snapshot->height)->toBe(8);

    Storage::disk('local')->assertExists(
        'x-change/claim/splash-artwork/'.$snapshot->sha256.'.png',
    );

    $voucher = issueVoucher($prepared);

    expect(data_get(
        $voucher,
        'instructions.metadata.custom.rider_splash_artwork.sha256',
    ))->toBe($snapshot->sha256)
        ->and(app(RiderSplashArtworkSnapshotterContract::class)
            ->assertStored($voucher)?->sha256)
        ->toBe($snapshot->sha256);
    Http::assertSentCount(1);
});

it('rejects persisted Splash artwork without a trusted stored snapshot', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'splash' => '<img src="https://github.com/example/art/blob/main/missing.png?raw=true">',
            'stamp' => [
                'version' => 2,
                'artwork_source' => 'splash',
            ],
        ],
    ]));

    expect(fn () => app(RiderSplashArtworkSnapshotterContract::class)
        ->assertStored($voucher))
        ->toThrow(
            RiderStampArtworkUnavailable::class,
            RiderStampArtworkUnavailable::Message,
        );
});

it('rolls back issuance when the persisted Splash snapshot invariant fails', function (): void {
    $user = actingAsTestUser();
    $voucherCount = Voucher::query()->count();
    $input = validVoucherInstructions(overrides: [
        'rider' => [
            'splash' => '<img src="https://github.com/example/art/blob/main/missing.png?raw=true">',
            'stamp' => [
                'version' => 2,
                'artwork_source' => 'splash',
            ],
        ],
    ])->toArray();

    expect(fn () => app(PayCodeIssuanceContract::class)->issue($user, $input))
        ->toThrow(
            RiderStampArtworkUnavailable::class,
            RiderStampArtworkUnavailable::Message,
        );

    expect(Voucher::query()->count())->toBe($voucherCount);
});

it('retries transient remote failures before persisting Splash artwork', function (): void {
    $image = imagecreatetruecolor(8, 8);
    $blue = imagecolorallocate($image, 59, 130, 246);
    imagefilledrectangle($image, 0, 0, 8, 8, $blue);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    expect($contents)->toBeString()->not->toBeEmpty();

    config()->set(
        'x-change.claim.share.splash_artwork.retry_sleep_milliseconds',
        0,
    );

    Http::fake([
        'https://raw.githubusercontent.com/example/art/main/blue.png' => Http::sequence()
            ->push('temporarily unavailable', 503)
            ->push($contents, 200, ['Content-Type' => 'image/png']),
    ]);

    $prepared = app(RiderSplashArtworkSnapshotterContract::class)
        ->prepare(validVoucherInstructions(overrides: [
            'rider' => [
                'splash' => '<img src="https://github.com/example/art/blob/main/blue.png?raw=true">',
                'stamp' => [
                    'version' => 2,
                    'artwork_source' => 'splash',
                ],
            ],
        ])->toArray());
    $snapshot = RiderSplashArtworkSnapshotData::fromArray(data_get(
        $prepared,
        'metadata.custom.rider_splash_artwork',
    ));

    expect($snapshot)->toBeInstanceOf(RiderSplashArtworkSnapshotData::class)
        ->and($snapshot->sha256)->toBe(hash('sha256', $contents));
    Http::assertSentCount(2);
});

it('reuses the last verified Splash snapshot during a transient remote outage', function (): void {
    $image = imagecreatetruecolor(8, 8);
    $purple = imagecolorallocate($image, 139, 92, 246);
    imagefilledrectangle($image, 0, 0, 8, 8, $purple);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    expect($contents)->toBeString()->not->toBeEmpty();

    config()->set(
        'x-change.claim.share.splash_artwork.retry_attempts',
        1,
    );

    $attempt = 0;
    Http::fake(function () use (&$attempt, $contents) {
        $attempt++;

        return $attempt === 1
            ? Http::response($contents, 200, ['Content-Type' => 'image/png'])
            : Http::failedConnection('temporary DNS failure');
    });

    $input = validVoucherInstructions(overrides: [
        'rider' => [
            'splash' => '<img src="https://github.com/example/art/blob/main/purple.png?raw=true">',
            'stamp' => [
                'version' => 2,
                'artwork_source' => 'splash',
            ],
        ],
    ])->toArray();
    $snapshots = app(RiderSplashArtworkSnapshotterContract::class);
    $captured = RiderSplashArtworkSnapshotData::fromArray(data_get(
        $snapshots->prepare($input),
        'metadata.custom.rider_splash_artwork',
    ));
    $reused = RiderSplashArtworkSnapshotData::fromArray(data_get(
        $snapshots->prepare($input),
        'metadata.custom.rider_splash_artwork',
    ));

    expect($captured)->toBeInstanceOf(RiderSplashArtworkSnapshotData::class)
        ->and($reused?->sha256)->toBe($captured->sha256)
        ->and($reused?->width)->toBe($captured->width)
        ->and($reused?->height)->toBe($captured->height);

    Storage::disk('local')->assertExists(
        'x-change/claim/splash-artwork/sources/'
        .hash(
            'sha256',
            'https://raw.githubusercontent.com/example/art/main/purple.png',
        )
        .'.json',
    );
    Http::assertSentCount(2);
});

it('does not require an artwork snapshot for a non-Splash Stamp', function (): void {
    $input = validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Dinner',
            'splash' => '<img src="https://untrusted.example.test/image.png">',
            'stamp' => [
                'version' => 2,
                'artwork_source' => 'x_change',
            ],
        ],
    ])->toArray();

    $prepared = app(RiderSplashArtworkSnapshotterContract::class)
        ->prepare($input);

    expect($prepared)->toBe($input)
        ->and(data_get(
            $prepared,
            'metadata.custom.rider_splash_artwork',
        ))->toBeNull();
    Http::assertNothingSent();
});

it('replaces caller-supplied snapshot metadata with a server-derived descriptor', function (): void {
    $image = imagecreatetruecolor(8, 8);
    $yellow = imagecolorallocate($image, 250, 204, 21);
    imagefilledrectangle($image, 0, 0, 8, 8, $yellow);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    expect($contents)->toBeString()->not->toBeEmpty();

    Http::fake([
        'https://raw.githubusercontent.com/example/art/main/yellow.png' => Http::response(
            $contents,
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $input = validVoucherInstructions(overrides: [
        'rider' => [
            'splash' => '<img src="https://github.com/example/art/blob/main/yellow.png?raw=true">',
            'stamp' => [
                'version' => 2,
                'artwork_source' => 'splash',
            ],
        ],
        'metadata' => [
            'custom' => [
                'rider_splash_artwork' => [
                    'schema' => RiderSplashArtworkSnapshotData::Schema,
                    'sha256' => str_repeat('a', 64),
                    'mime_type' => 'image/png',
                    'width' => 1,
                    'height' => 1,
                    'captured_at' => now()->toIso8601String(),
                ],
            ],
        ],
    ])->toArray();

    Storage::disk('local')->put(
        'x-change/claim/splash-artwork/'.str_repeat('a', 64).'.png',
        'caller-controlled',
    );

    $prepared = app(RiderSplashArtworkSnapshotterContract::class)
        ->prepare($input);
    $snapshot = RiderSplashArtworkSnapshotData::fromArray(data_get(
        $prepared,
        'metadata.custom.rider_splash_artwork',
    ));

    expect($snapshot)->toBeInstanceOf(RiderSplashArtworkSnapshotData::class)
        ->and($snapshot->sha256)->toBe(hash('sha256', $contents))
        ->not->toBe(str_repeat('a', 64));
    Http::assertSentCount(1);
});

it('backfills existing Pay Codes idempotently and reads only verified bytes', function (): void {
    $image = imagecreatetruecolor(8, 8);
    $green = imagecolorallocate($image, 16, 185, 129);
    imagefilledrectangle($image, 0, 0, 8, 8, $green);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    expect($contents)->toBeString()->not->toBeEmpty();

    Http::fake([
        'https://raw.githubusercontent.com/example/art/main/green.png' => Http::response(
            $contents,
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'splash' => '<img src="https://github.com/example/art/blob/main/green.png?raw=true">',
            'stamp' => [
                'version' => 2,
                'artwork_source' => 'splash',
            ],
        ],
    ]));
    $snapshots = app(RiderSplashArtworkSnapshotterContract::class);
    $captured = $snapshots->capture($voucher);
    $replayed = $snapshots->capture($voucher);

    expect($captured)->toBeInstanceOf(RiderSplashArtworkSnapshotData::class)
        ->and($replayed?->sha256)->toBe($captured->sha256)
        ->and($snapshots->dataUrl($voucher))
        ->toStartWith('data:image/png;base64,');

    Http::assertSentCount(1);

    Storage::disk('local')->put(
        'x-change/claim/splash-artwork/'.$captured->sha256.'.png',
        'tampered',
    );

    expect($snapshots->dataUrl($voucher))->toBeNull();
});

it('blocks issuance for unapproved hosts and forged image responses', function (
    string $source,
    string $contentType,
): void {
    Http::fake([
        '*' => Http::response('not an image', 200, [
            'Content-Type' => $contentType,
        ]),
    ]);

    $input = validVoucherInstructions(overrides: [
        'rider' => [
            'splash' => '<img src="'.$source.'">',
            'stamp' => [
                'version' => 2,
                'artwork_source' => 'splash',
            ],
        ],
    ])->toArray();
    expect(
        fn (): array => app(RiderSplashArtworkSnapshotterContract::class)
            ->prepare($input),
    )->toThrow(
        RiderStampArtworkUnavailable::class,
        RiderStampArtworkUnavailable::Message,
    );
})->with([
    'unapproved host' => [
        'https://untrusted.example.test/image.png',
        'image/png',
    ],
    'forged content type' => [
        'https://raw.githubusercontent.com/example/art/main/image.png',
        'image/png',
    ],
]);
