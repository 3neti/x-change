<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('backfills a durable Rider Splash artwork snapshot by Pay Code', function (): void {
    Storage::fake('local');
    config()->set(
        'x-change.claim.share.splash_artwork.allowed_hosts',
        ['raw.githubusercontent.com'],
    );
    $image = imagecreatetruecolor(8, 8);
    $blue = imagecolorallocate($image, 59, 130, 246);
    imagefilledrectangle($image, 0, 0, 8, 8, $blue);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    expect($contents)->toBeString()->not->toBeEmpty();

    Http::fake([
        'https://raw.githubusercontent.com/example/art/main/blue.png' => Http::response(
            $contents,
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'splash' => '<img src="https://github.com/example/art/blob/main/blue.png?raw=true">',
            'stamp' => [
                'version' => 2,
                'artwork_source' => 'splash',
            ],
        ],
    ]));

    $this->artisan('x-change:claim:snapshot-splash-artwork', [
        'code' => $voucher->code,
        '--json' => true,
    ])->assertSuccessful();

    $voucher->refresh();

    expect(data_get(
        $voucher,
        'instructions.metadata.custom.rider_splash_artwork.schema',
    ))->toBe('x-change.rider-splash-artwork-snapshot.v1')
        ->and(data_get(
            $voucher,
            'instructions.metadata.custom.rider_stamp_artifact.schema',
        ))->toBe('x-change.rider-stamp-artifact.v1')
        ->and(data_get(
            $voucher,
            'instructions.metadata.custom.rider_stamp_artifact.width',
        ))->toBe(1200)
        ->and(data_get(
            $voucher,
            'instructions.metadata.custom.rider_stamp_artifact.height',
        ))->toBe(630)
        ->and(data_get(
            $voucher,
            'instructions.metadata.custom.rider_stamp_artifact.mime_type',
        ))->toBe('image/png');

    $sha256 = data_get(
        $voucher,
        'instructions.metadata.custom.rider_stamp_artifact.sha256',
    );

    expect($sha256)->toBeString()->toHaveLength(64);
    Storage::disk('local')->assertExists(
        "x-change/claim/stamp-artifacts/{$sha256}.png",
    );
    Http::assertSentCount(1);
});
