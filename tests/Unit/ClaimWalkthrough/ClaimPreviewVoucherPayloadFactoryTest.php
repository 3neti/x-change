<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewVoucherPayloadFactory;
use LBHurtado\XChange\Data\Claim\RiderSplashArtworkSnapshotData;

beforeEach(function (): void {
    Storage::fake('local');
    Http::preventStrayRequests();
    config()->set(
        'x-change.claim.share.splash_artwork.allowed_hosts',
        ['raw.githubusercontent.com'],
    );
});

it('prepares a trusted Splash snapshot for its temporary preview Pay Code', function (): void {
    $image = imagecreatetruecolor(8, 8);
    $blue = imagecolorallocate($image, 59, 130, 246);
    imagefilledrectangle($image, 0, 0, 8, 8, $blue);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    expect($contents)->toBeString()->not->toBeEmpty();

    Http::fake([
        'https://raw.githubusercontent.com/example/art/main/preview.png' => Http::response(
            $contents,
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $payload = app(ClaimPreviewVoucherPayloadFactory::class)->make(
        validVoucherInstructions(overrides: [
            'rider' => [
                'splash' => '<img src="https://github.com/example/art/blob/main/preview.png?raw=true">',
                'splash_format' => 'html',
                'stamp' => [
                    'version' => 2,
                    'artwork_source' => 'splash',
                ],
            ],
        ]),
        actingAsTestUser(),
    );

    $snapshot = RiderSplashArtworkSnapshotData::fromArray(data_get(
        $payload,
        'metadata.custom.rider_splash_artwork',
    ));

    expect($snapshot)->toBeInstanceOf(RiderSplashArtworkSnapshotData::class);

    Storage::disk('local')->assertExists(
        'x-change/claim/splash-artwork/'.$snapshot->sha256.'.png',
    );
    expect(data_get($payload, 'metadata.custom.walkthrough.preview'))->toBeTrue();
    Http::assertSentCount(1);
});
