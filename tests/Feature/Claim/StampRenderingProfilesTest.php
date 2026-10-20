<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareMetadataResolverContract;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Data\Claim\RiderStampArtifactData;
use LBHurtado\XChange\Services\Stamp\StampPreviewComposer;
use LBHurtado\XChange\Services\Stamp\StampRenderingProfiles;
use LBHurtado\XStamp\RenderingProfile;
use Spatie\Browsershot\Browsershot;
use Zxing\QrReader;

it('freezes the selected rendering profile and ignores subsequent defaults', function (): void {
    config(['x-change.experience.stamp_rendering.allowed' => ['standard', 'browser', 'browser-hd']]);
    $profiles = app(StampRenderingProfiles::class);
    $input = $profiles->materialize(['metadata' => ['custom' => ['stamp_rendering' => ['profile' => 'browser-hd', 'chrome_path' => '/untrusted']]]]);
    expect(data_get($input, 'metadata.custom.stamp_rendering'))->toMatchArray(['schema' => 'x-change.stamp-rendering/1', 'profile' => 'browser-hd']);
    expect(data_get($input, 'metadata.custom.stamp_rendering.chrome_path'))->toBeNull();
    $voucher = new Voucher;
    $voucher->forceFill(['metadata' => ['instructions' => array_replace_recursive(validVoucherInstructions()->toArray(), $input)]]);
    config(['x-change.experience.stamp_rendering.default' => 'browser', 'x-change.experience.stamp_rendering.allowed' => ['standard']]);
    expect($profiles->forVoucher($voucher))->toBe(RenderingProfile::BrowserHd);
    expect($profiles->materialize($input))->toBe($input);
    data_set($input, 'metadata.custom.stamp_rendering.signature', 'forged');
    expect(fn () => $profiles->materialize($input))->toThrow(ValidationException::class);
});

it('rejects profiles not enabled for new orders', function (): void {
    $this->postJson(route('x-change.stamps.preview'), ['instructions' => ['cash' => ['amount' => 500, 'currency' => 'PHP'], 'metadata' => ['custom' => ['stamp_rendering' => ['profile' => 'browser-hd']]]]])
        ->assertUnprocessable()->assertJsonValidationErrors('metadata.custom.stamp_rendering.profile');
});

it('reads legacy descriptors and validates browser dimensions independently of configuration', function (): void {
    $legacy = new RiderStampArtifactData(str_repeat('a', 64), 1200, 630, 'image/png', 'manifest', '2026-10-20T00:00:00Z');
    expect(RiderStampArtifactData::fromArray($legacy->toArray())?->renderProfile)->toBe('standard');
    $hd = new RiderStampArtifactData(str_repeat('b', 64), 2400, 1260, 'image/png', 'manifest', '2026-10-20T00:00:00Z', 'browser-hd');
    expect(RiderStampArtifactData::fromArray($hd->toArray())?->width)->toBe(2400);
    expect(RiderStampArtifactData::fromArray([...$hd->toArray(), 'scale' => 1]))->toBeNull();
    expect(RiderStampArtifactData::fromArray([...$legacy->toArray(), 'width' => 2400, 'height' => 1260]))->toBeNull();
});

it('keeps comparison tooling inaccessible outside an explicitly enabled local environment', function (): void {
    config(['x-change.experience.stamp_rendering.comparison_enabled' => true]);
    $this->get(route('x-change.stamps.rendering-comparison'))->assertNotFound();
    app()->detectEnvironment(fn (): string => 'local');
    config(['x-change.experience.stamp_rendering.comparison_enabled' => false]);
    $this->get(route('x-change.stamps.rendering-comparison'))->assertNotFound();
});

it('renders the browser profile through the canonical guest and immutable artifact paths', function (): void {
    if (! getenv('STAMP_BROWSER_MODULE_PATH') || ! class_exists(Browsershot::class)) {
        $this->markTestSkipped('Requires optional Browsershot and STAMP_BROWSER_MODULE_PATH.');
    }
    config([
        'x-change.experience.stamp_rendering.allowed' => ['standard', 'browser', 'browser-hd'],
        'x-change.experience.stamp_rendering.node_module_path' => getenv('STAMP_BROWSER_MODULE_PATH'),
        'x-change.experience.stamp_rendering.chrome_path' => getenv('STAMP_BROWSER_CHROME') ?: null,
    ]);
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => 'cats', 'design_version' => 7, 'artwork_source' => 'x_change', 'claim_marker' => 'qr']);
    data_set($instructions, 'metadata.custom.stamp_rendering.profile', 'browser-hd');
    $result = app(StampPreviewComposer::class)->preview($instructions);
    expect([$result['width'], $result['height']])->toBe([2400, 1260]);
    expect($result['requirements']['keys'])->toBeArray();
    $canvas = imagecreatefromstring(base64_decode(explode(',', $result['image'], 2)[1]));
    $bounds = $result['marker_bounds'];
    $qr = imagecrop($canvas, array_map(static fn (int $value): int => $value * 2, $bounds));
    ob_start();
    imagepng($qr);
    $qrBytes = ob_get_clean();
    imagedestroy($qr);
    imagedestroy($canvas);
    expect((new QrReader($qrBytes, QrReader::SOURCE_TYPE_BLOB, false))->text())->toBe(route('x-change.claim.start'));
    Storage::fake('local');
    $user = actingAsTestUser();
    $issued = app(PayCodeIssuanceContract::class)->issue($user, $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $store = app(RiderStampArtifactStoreContract::class);
    $artifact = $store->descriptor($voucher);
    $matched = app(StampPreviewComposer::class)->preview($instructions, $voucher->code);
    expect(base64_decode(explode(',', $matched['image'], 2)[1]))->toBe($store->read($voucher)?->contents);
    config(['x-change.experience.stamp_rendering.default' => 'standard', 'x-change.experience.stamp_rendering.allowed' => ['standard']]);
    expect($artifact?->width)->toBe(2400);
    expect($store->read($voucher)?->contents)->not->toBeNull();
    $metadata = app(ClaimShareMetadataResolverContract::class)->resolve($voucher, 'https://example.test/claim', 'https://example.test/card.png');
    expect([$metadata->imageWidth, $metadata->imageHeight])->toBe([2400, 1260]);
});

it('renders sanitized full splash HTML beneath the system frame with a verified image snapshot', function (): void {
    if (! getenv('STAMP_BROWSER_MODULE_PATH') || ! class_exists(Browsershot::class)) {
        $this->markTestSkipped('Requires optional Chromium runtime.');
    }
    Storage::fake('local');
    Http::preventStrayRequests();
    $image = imagecreatetruecolor(1200, 630);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 35, 70));
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
    Http::fake(['https://raw.githubusercontent.com/example/art/main/rose.png' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
    config([
        'x-change.experience.stamp_rendering.allowed' => ['standard', 'browser-hd'],
        'x-change.experience.stamp_rendering.node_module_path' => getenv('STAMP_BROWSER_MODULE_PATH'),
        'x-change.experience.stamp_rendering.chrome_path' => getenv('STAMP_BROWSER_CHROME') ?: null,
    ]);
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'rider.splash', '<div class="relative"><img src="https://raw.githubusercontent.com/example/art/main/rose.png"><div class="absolute inset-0 flex items-center justify-end"><h2 style="color:white">Unique artwork title</h2></div></div><script>alert(1)</script>');
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => 'cats', 'design_version' => 7, 'artwork_source' => 'splash', 'copy_source' => 'splash', 'claim_marker' => 'qr']);
    data_set($instructions, 'metadata.custom.stamp_rendering.profile', 'browser-hd');
    $result = app(StampPreviewComposer::class)->preview($instructions);
    expect([$result['width'], $result['height']])->toBe([2400, 1260]);
    expect($result['marker_bounds']['width'])->toBe(190);
    Http::assertSentCount(1);
});
