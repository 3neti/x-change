<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\RiderStampClaimCardComposerContract;
use LBHurtado\XChange\Services\RiderStampDesignRegistry;
use LBHurtado\XChange\Services\Stamp\StampCatalog;
use LBHurtado\XChange\Services\Stamp\StampPresentation;
use Zxing\QrReader;

it('lists only the latest design cards while retaining exact historical lookup', function (): void {
    $catalog = app(StampCatalog::class);
    expect(array_column($catalog->options(), 'revision', 'id'))->toBe(['steampunk' => 8, 'cats' => 7, 'valentine' => 1]);
    expect($catalog->resolve('cats', 6)->revision())->toBe(6);
});

it('pins resolved appearance before issuance and keeps it stable when configuration changes', function (): void {
    $registry = app(RiderStampDesignRegistry::class);
    $input = ['rider' => ['stamp' => ['design_id' => 'cats', 'design_version' => 7]], 'metadata' => ['custom' => ['stamp_presentation' => ['options' => ['preset' => 'soft', 'crop' => 'top', 'darkening' => 20]]]]];
    $saved = $registry->materialize($input);
    $snapshot = data_get($saved, 'metadata.custom.stamp_presentation');
    expect($snapshot['resolved']['paper'])->toBe(.9);
    config(['x-change.experience.stamp_presentation.presets.soft.paper' => 1]);
    expect(data_get($registry->materialize($saved), 'metadata.custom.stamp_presentation'))->toBe($snapshot);
    expect(data_get($registry->materialize($input), 'metadata.custom.stamp_presentation.resolved.paper'))->toBe(1);
});

it('rejects unsupported controls and mismatched saved design identities', function (array $candidate): void {
    expect(fn () => app(StampPresentation::class)->snapshot(app(StampCatalog::class)->resolve('steampunk', 8), $candidate))->toThrow(ValidationException::class);
})->with([
    [['options' => ['crop' => 'top']]],
    [['revision' => 7, 'options' => []]],
    [['options' => ['darkening' => 41]]],
    [['resolved' => ['paper' => .1, 'logo_backing' => .3, 'ink' => .9, 'frame' => .3]]],
]);

it('renders a complete scanable guest preview without creating vouchers or requesting remote artwork', function (string $id, int $revision, string $preset): void {
    Http::preventStrayRequests();
    $before = Voucher::count();
    $response = $this->postJson(route('x-change.stamps.preview'), [
        'design_id' => $id, 'revision' => $revision, 'marker' => 'qr',
        'presentation' => ['options' => ['preset' => $preset]],
        'slots' => ['brand' => 'Pay Code', 'amount' => '₱100', 'purpose' => 'For you'],
    ])->assertOk()->assertJsonPath('marker_bounds.x', 956);
    $png = base64_decode(explode(',', $response->json('image'), 2)[1]);
    $image = imagecreatefromstring($png);
    $crop = imagecrop($image, ['x' => 956, 'y' => 386, 'width' => 190, 'height' => 190]);
    ob_start();
    imagepng($crop);
    $bytes = ob_get_clean();
    expect((new QrReader($bytes, QrReader::SOURCE_TYPE_BLOB))->text())->toBe(route('x-change.claim.start'));
    imagedestroy($crop);
    imagedestroy($image);
    expect(Voucher::count())->toBe($before);
    Http::assertNothingSent();
})->with([['cats', 7, 'clear'], ['cats', 7, 'soft'], ['cats', 7, 'transparent'], ['steampunk', 8, 'clear'], ['steampunk', 8, 'soft'], ['steampunk', 8, 'transparent']]);

it('issues an immutable appearance snapshot and renders it independently of later defaults', function (): void {
    Storage::fake('local');
    $user = actingAsTestUser();
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => 'cats', 'design_version' => 7, 'artwork_source' => 'x_change', 'claim_marker' => 'qr']);
    data_set($instructions, 'metadata.custom.stamp_presentation', ['options' => ['crop' => 'top', 'darkening' => 20, 'preset' => 'soft']]);
    $issued = app(PayCodeIssuanceContract::class)->issue($user, $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $snapshot = data_get($voucher->metadata, 'instructions.metadata.custom.stamp_presentation');
    expect($snapshot['revision'])->toBe(7)->and($snapshot['options']['darkening'])->toBe(20)->and($snapshot['resolved']['paper'])->toBe(.9);
    $renderer = app(RiderStampClaimCardComposerContract::class);
    $url = route('x-change.claim.show', ['code' => $voucher->code]);
    $before = $renderer->compose($voucher, $url)->contents;
    config(['x-change.experience.stamp_presentation.presets.soft.paper' => 1, 'x-change.experience.stamp_presentation.defaults.darkening' => 40]);
    expect($renderer->compose($voucher->fresh(), $url)->contents)->toBe($before);
});
