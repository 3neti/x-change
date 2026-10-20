<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\ServiceProvider;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Contracts\RiderStampClaimCardComposerContract;
use LBHurtado\XChange\Data\Claim\ClaimShareCardData;
use LBHurtado\XChange\Data\Claim\RiderStampCopyData;
use LBHurtado\XChange\Http\Middleware\RequireCurrentAgreementAcceptance;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;
use LBHurtado\XChange\Services\Claim\GdRiderStampClaimShareCardRenderer;
use LBHurtado\XChange\Services\RiderStampDesignRegistry;
use LBHurtado\XChange\Services\Stamp\StampCatalog;
use LBHurtado\XChange\Services\Stamp\StampPreviewComposer;
use LBHurtado\XChange\Services\Stamp\StampProviderIdentity;
use LBHurtado\XStamp\Design;
use LBHurtado\XStamp\IdentifierPresentation;
use LBHurtado\XStamp\Renderer;
use Zxing\QrReader;

it('renders installed Stamp revisions for a guest without issuing a Pay Code', function (): void {
    Http::preventStrayRequests();
    $before = Voucher::count();
    foreach (app(StampCatalog::class)->options() as $design) {
        $response = $this->postJson(route('x-change.stamps.preview'), [
            'design_id' => $design['id'], 'revision' => $design['revision'],
            'slots' => ['brand' => 'Pay Code', 'amount' => '₱100.00', 'purpose' => '<script>alert(1)</script>'],
        ])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $image = base64_decode(substr($response->json('image'), strlen('data:image/png;base64,')));
        expect(getimagesizefromstring($image)[0])->toBe(1200);
    }
    expect(Voucher::count())->toBe($before);
    Http::assertNothingSent();
});

it('returns 422 for missing revisions or unexpected slot fields', function (array $payload): void {
    $this->postJson(route('x-change.stamps.preview'), $payload)->assertUnprocessable();
})->with([
    [['design_id' => 'cats', 'revision' => 99, 'slots' => ['amount' => '100']]],
    [['design_id' => 'cats', 'revision' => 1, 'slots' => ['url' => 'https://example.com']]],
    [['design_id' => 'cats', 'revision' => 1, 'slots' => ['amount' => str_repeat('x', 1025)]]],
]);

it('keeps historical palettes and exposes independently installed designs', function (): void {
    $catalog = app(StampCatalog::class);
    expect($catalog->resolve('valentine', 1)->id())->toBe('valentine');
    expect(array_unique(array_column($catalog->options(), 'id')))->toContain('steampunk', 'cats');
    expect(app(RiderStampDesignRegistry::class)->resolve('x-change-steampunk', 1)['palette']['start'])->toBe([49, 34, 20]);
});

it('pins system designs into issued artifacts without changing their revisions', function (string $id, array $rgb): void {
    Storage::fake('local');
    $user = actingAsTestUser();
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'inputs.fields', ['location', 'selfie', 'signature']);
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => $id, 'design_version' => 1, 'artwork_source' => 'x_change', 'claim_marker' => 'qr', 'claim_marker_position' => 'bottom_right']);
    $issued = app(PayCodeIssuanceContract::class)->issue($user, $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $artifact = app(RiderStampArtifactStoreContract::class)->read($voucher);
    expect($artifact)->not->toBeNull();
    $image = imagecreatefromstring($artifact->contents);
    $pixel = imagecolorsforindex($image, imagecolorat($image, 0, 0));
    expect([$pixel['red'], $pixel['green'], $pixel['blue']])->toBe($rgb);
    $presentation = app(GdRiderStampClaimShareCardRenderer::class)->systemIndicators(app(StampCatalog::class)->presentation($id, 1, 'bottom_right', '₱100.00'), ['input.location', 'input.selfie', 'input.signature'], 'bottom_right', 'qr', '₱100.00');
    $bounds = array_last($presentation['design']->document['layers']);
    $badgePixel = imagecolorsforindex($image, imagecolorat($image, $bounds['x'] + 5, $bounds['y'] + 20));
    expect([$badgePixel['red'], $badgePixel['green'], $badgePixel['blue']])->toBe([4, 120, 87]);
    imagedestroy($image);
})->with([
    ['steampunk', [245, 230, 200]],
    ['cats', [245, 239, 230]],
    ['valentine', [255, 240, 243]],
]);

it('moves content away from left-hand claim markers without modifying the registered design', function (): void {
    $catalog = app(StampCatalog::class);
    $original = $catalog->resolve('cats', 1);
    $presentation = $catalog->presentation('cats', 1, 'top_left');
    foreach ($presentation->document['layers'] as $layer) {
        if (isset($layer['slot'])) {
            expect($layer['x'])->toBe(330);
        }
    }
    expect($original->document['layers'][8]['x'])->not->toBe(330);
});

it('rejects amounts that cannot be displayed without truncating their value', function (): void {
    $this->postJson(route('x-change.stamps.preview'), [
        'design_id' => 'steampunk', 'revision' => 1, 'slots' => ['amount' => str_repeat('9', 1000)],
    ])->assertUnprocessable();
});

it('shares the installed catalog with authenticated cockpit issuance', function (): void {
    app()->register(ServiceProvider::class);
    $this->withoutMiddleware(RequireCurrentAgreementAcceptance::class);
    actingAsTestUser();
    $this->withHeader('X-Inertia', 'true')->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('props.xchange.stamp_default', 'steampunk')
        ->assertJsonPath('props.xchange.stamps', fn (array $stamps): bool => in_array('cats', array_column($stamps, 'id'), true));
});

it('keeps a system design when linked artwork is text-only', function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    $user = actingAsTestUser();
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'rider.url', 'https://unused.example.test/artwork');
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => 'cats', 'design_version' => 1, 'artwork_source' => 'url', 'artwork_treatment' => 'text', 'claim_marker' => 'qr']);
    $issued = app(PayCodeIssuanceContract::class)->issue($user, $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $artifact = app(RiderStampArtifactStoreContract::class)->read($voucher);
    $image = imagecreatefromstring($artifact->contents);
    $pixel = imagecolorsforindex($image, imagecolorat($image, 26, 100));
    expect([$pixel['red'], $pixel['green'], $pixel['blue']])->toBe([132, 97, 165]);
    imagedestroy($image);
    Http::assertNothingSent();
});

it('shares the same generic claim-entry QR for guest Stamp previews without issuing a voucher', function (): void {
    app()->register(ServiceProvider::class);
    Http::preventStrayRequests();
    $before = Voucher::count();
    Route::middleware(['web', ShareXChangeBranding::class])
        ->get('/stamp-claim-entry-test', fn () => Inertia\Inertia::render('StampPreview'));

    $this->withHeader('X-Inertia', 'true')->get('/stamp-claim-entry-test')
        ->assertOk()
        ->assertJsonPath('props.xchange.claim_entry_artifact.kind', 'claim_entry')
        ->assertJsonPath('props.xchange.claim_entry_artifact.destination', route('x-change.claim.start'))
        ->assertJsonPath('props.xchange.claim_entry_artifact.identifier', null)
        ->assertJsonPath('props.xchange.claim_entry_artifact.image_data_uri', fn (string $value): bool => str_starts_with($value, 'data:image/png;base64,'));
    expect(Voucher::count())->toBe($before);
    Http::assertNothingSent();
});

it('provides a warm light QR paper from the design palette', function (): void {
    $this->postJson(route('x-change.stamps.preview'), [
        'design_id' => 'cats', 'revision' => 1, 'slots' => ['amount' => '₱100.00'],
    ])->assertOk()->assertJsonPath('marker_palette.paper', '#F5EFE6');
    $catalog = app(StampCatalog::class);
    $design = $catalog->resolve('cats', 1);
    $darkDesign = new Design([...$design->document, 'background' => '#101719', 'foreground' => '#F8EDD4'], [
        'assets/fonts/NotoSans.ttf' => $design->asset('assets/fonts/NotoSans.ttf'),
    ]);
    expect($catalog->markerPalette($darkDesign)['paper'])->toBe('#F8EDD4');
});

it('keeps the themed QR scannable in the exported Stamp', function (): void {
    Storage::fake('local');
    $user = actingAsTestUser();
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => 'cats', 'design_version' => 1, 'artwork_source' => 'x_change', 'claim_marker' => 'qr']);
    $issued = app(PayCodeIssuanceContract::class)->issue($user, $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $artifact = app(RiderStampArtifactStoreContract::class)->read($voucher);
    $image = imagecreatefromstring($artifact->contents);
    $paper = imagecolorsforindex($image, imagecolorat($image, 964, 389));
    expect([$paper['red'], $paper['green'], $paper['blue']])->toBe([245, 239, 230]);
    imagedestroy($image);
    $decoded = new QrReader($artifact->contents, QrReader::SOURCE_TYPE_BLOB, false);
    expect($decoded->text())->toContain((string) $voucher->code);
});

it('keeps exported Stamp copy aligned with the preview sentences', function (string $title, string $description, bool $visible, string $expected): void {
    $copy = new RiderStampCopyData('custom', $title, $description, $description, $visible);
    expect($copy->plainText())->toBe($expected);
})->with([
    ['Pay Code', 'Money should adapt to people. Not the other way around.', true, "Money should adapt to people.\nNot the other way around."],
    ['For Amelia', 'Thank you for your help.', true, "For Amelia\nThank you for your help."],
    ['Hidden', 'Hidden', false, ''],
]);

it('renders the selected provider identity for guests using trusted package artwork only', function (): void {
    Http::preventStrayRequests();
    $before = Voucher::count();
    $payload = ['design_id' => 'cats', 'revision' => 5, 'provider' => 'netbank', 'slots' => ['brand' => 'Pay Code', 'amount' => '₱100.00']];
    $response = $this->postJson(route('x-change.stamps.preview'), $payload)
        ->assertOk()->assertJsonPath('provider_identity', ['code' => 'netbank', 'name' => 'NetBank']);
    $withoutProvider = $this->postJson(route('x-change.stamps.preview'), [...$payload, 'provider' => 'manual'])->assertOk()->assertJsonPath('provider_identity', null);
    expect($response->json('image'))->not->toBe($withoutProvider->json('image'));
    $this->postJson(route('x-change.stamps.preview'), [...$payload, 'provider' => 'https://example.test/logo.png'])->assertUnprocessable();
    $this->postJson(route('x-change.stamps.preview'), [...$payload, 'slots' => [...$payload['slots'], 'provider_logo' => '/etc/passwd']])->assertUnprocessable();
    expect(Voucher::count())->toBe($before);
    Http::assertNothingSent();
});

it('resolves configured provider defaults and omits unresolved identity', function (): void {
    config(['x-change.provider_runtime.default_provider' => 'netbank']);
    $resolver = app(StampProviderIdentity::class);
    expect($resolver->resolve()['name'])->toBe('NetBank');
    expect($resolver->resolve('unknown-provider'))->toBeNull();
    config(['x-change.provider_runtime.providers.example' => ['enabled' => true]]);
    expect($resolver->resolve('example'))->toBe(['code' => 'example', 'name' => 'Example', 'logo' => null]);
});

it('pins issued provider identity and ignores recipient bank and subsequent default changes', function (): void {
    Storage::fake('local');
    $user = actingAsTestUser();
    $instructions = validVoucherInstructions()->toArray();
    $instructions['provider'] = 'netbank';
    data_set($instructions, 'metadata.custom.stamp_provider', 'paynamics');
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => 'cats', 'design_version' => 6, 'artwork_source' => 'x_change', 'claim_marker' => 'qr']);
    $issued = app(PayCodeIssuanceContract::class)->issue($user, $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $artifact = app(RiderStampArtifactStoreContract::class)->read($voucher);
    config(['x-change.provider_runtime.default_provider' => 'paynamics']);
    $metadata = $voucher->metadata;
    data_set($metadata, 'bank_code', 'GXCHPHM2XXX');
    $voucher->metadata = $metadata;
    $resolver = app(StampProviderIdentity::class);
    expect($resolver->forVoucher($voucher)['code'])->toBe('netbank');
    $reader = new QrReader($artifact->contents, QrReader::SOURCE_TYPE_BLOB, false);
    expect($reader->text())->toContain($voucher->code);
    $image = imagecreatefromstring($artifact->contents);
    $logo = imagecolorsforindex($image, imagecolorat($image, 89, 86));
    expect($logo['blue'])->toBeGreaterThan($logo['red']);
    imagedestroy($image);
    data_set($metadata, 'treasury.pay_code_reservation.provider', 'paynamics');
    $voucher->metadata = $metadata;
    expect($resolver->forVoucher($voucher)['code'])->toBe('paynamics');
});

it('keeps the provider header elements separated when moving away from a left claim marker', function (): void {
    $design = app(StampCatalog::class)->presentation('steampunk', 6, 'top_left');
    $slots = array_column(array_filter($design->document['layers'], fn ($layer) => isset($layer['slot'])), null, 'slot');
    expect($slots['provider_logo']['x'])->toBeGreaterThan($slots['brand']['x'] + $slots['brand']['width']);
    expect($slots['provider_name']['x'])->toBeGreaterThan($slots['provider_logo']['x'] + $slots['provider_logo']['width']);
});

it('shows the provider logo or its name before Pay Code without duplicating the identity', function (string $provider, string $name, int $brandX) {
    config(['x-change.provider_runtime.providers.landbank' => ['label' => 'LandBank']]);
    $catalog = app(StampCatalog::class);
    $identity = app(StampProviderIdentity::class)->resolve($provider);
    $slots = ['brand' => 'Pay Code', 'amount' => '₱100.00'];
    $response = $this->postJson(route('x-change.stamps.preview'), [
        'design_id' => 'cats', 'revision' => 6, 'provider' => $provider, 'slots' => $slots,
    ])->assertOk();
    $design = $catalog->presentation('cats', 6, 'bottom_right');
    $design = $catalog->providerPresentation($design, $name, isset($identity['logo']));
    $header = array_column(array_filter($design->document['layers'], fn ($layer) => isset($layer['slot'])), null, 'slot');
    if ($brandX > 0) {
        expect($header['brand']['x'])->toBe($brandX);
    } else {
        expect($header['brand']['x'])->toBeGreaterThan($header['provider_name']['x']);
        expect($header['brand']['x'])->toBeLessThan(260);
    }
    $header = (new IdentifierPresentation)->compose($design, 'AA317', true);
    $expected = (new Renderer)->png($header['design'], [...$slots, 'provider_name' => $name], [...(isset($identity['logo']) ? ['provider_logo' => $identity['logo']] : []), ...$header['images']]);
    expect(hash('sha256', base64_decode(explode(',', $response->json('image'), 2)[1])))->toBe(hash('sha256', $expected));
})->with([
    ['netbank', '', 129],
    ['landbank', 'LandBank', 0],
    ['manual', '', 65],
]);

it('exports the same provider-first logo or name header as the guest preview', function (string $provider) {
    config(['x-change.provider_runtime.providers.landbank' => ['label' => 'LandBank']]);
    Storage::fake('local');
    $instructions = validVoucherInstructions()->toArray();
    $instructions['provider'] = $provider;
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => 'steampunk', 'design_version' => 7, 'artwork_source' => 'x_change', 'claim_marker' => 'none', 'show_tagline' => false]);
    $issued = app(PayCodeIssuanceContract::class)->issue(actingAsTestUser(), $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $artifact = app(RiderStampArtifactStoreContract::class)->read($voucher);
    $response = $this->postJson(route('x-change.stamps.preview'), [
        'design_id' => 'steampunk', 'revision' => 7, 'provider' => $provider, 'code' => $voucher->code,
        'slots' => ['brand' => 'Pay Code', 'amount' => '₱100.00'],
    ])->assertOk();
    $preview = base64_decode(substr($response->json('image'), strlen('data:image/png;base64,')));
    $header = function (string $png): string {
        $image = imagecreatefromstring($png);
        $crop = imagecrop($image, ['x' => 50, 'y' => 50, 'width' => 800, 'height' => 60]);
        ob_start();
        imagepng($crop);
        $contents = ob_get_clean();
        imagedestroy($crop);
        imagedestroy($image);

        return $contents;
    };
    expect($header($artifact->contents))->toBe($header($preview));
})->with(['netbank', 'landbank']);

it('shows artwork through the QR and its bounding box while keeping the exported code scannable', function (string $designId, int $revision, string $position) {
    Storage::fake('local');
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => $designId, 'design_version' => $revision, 'artwork_source' => 'x_change', 'claim_marker' => 'qr', 'claim_marker_position' => $position]);
    $issued = app(PayCodeIssuanceContract::class)->issue(actingAsTestUser(), $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $artifact = app(RiderStampArtifactStoreContract::class)->read($voucher);
    $image = imagecreatefromstring($artifact->contents);
    $x = $position === 'top_left' ? 54 : 956;
    $y = $position === 'top_left' ? 54 : 386;
    $paper = imagecolorsforindex($image, imagecolorat($image, $x + 8, $y + 3));
    expect([$paper['red'], $paper['green'], $paper['blue']])->not->toBe([248, 237, 212]);
    $quietZone = imagecolorsforindex($image, imagecolorat($image, $x + 12, $y + 12));
    expect($quietZone['red'])->toBeGreaterThan(100);
    expect($quietZone['red'])->toBeLessThan(240);
    $base = imagecreatefromstring((new Renderer)->png(app(StampCatalog::class)->presentation($designId, $revision, $position)));
    $background = imagecolorsforindex($base, imagecolorat($base, $x + 8, $y + 3));
    foreach (['red' => 248, 'green' => 237, 'blue' => 212] as $channel => $value) {
        expect(abs($paper[$channel] - (int) floor($value * .3 + $background[$channel] * .7)))->toBeLessThanOrEqual(1);
    }
    $quietBackground = imagecolorsforindex($base, imagecolorat($base, $x + 12, $y + 12));
    foreach (['red' => 248, 'green' => 237, 'blue' => 212] as $channel => $value) {
        expect(abs($quietZone[$channel] - (int) floor($value * .65 + $quietBackground[$channel] * .35)))->toBeLessThanOrEqual(2);
    }
    imagedestroy($base);
    $trim = imagecolorsforindex($image, imagecolorat($image, $x, $y));
    expect([$trim['red'], $trim['green'], $trim['blue']])->not->toBe([221, 187, 132]);
    imagedestroy($image);
    $decoded = new QrReader($artifact->contents, QrReader::SOURCE_TYPE_BLOB, false);
    expect($decoded->text())->toContain((string) $voucher->code);
})->with([
    ['cats', 6, 'bottom_right'], ['steampunk', 7, 'bottom_right'],
    ['cats', 6, 'top_left'], ['steampunk', 7, 'top_left'],
]);

it('renders requirement badges into guest Stamp images for both current designs', function (string $id): void {
    $revision = collect(app(StampCatalog::class)->options())->where('id', $id)->max('revision');
    $payload = ['design_id' => $id, 'revision' => $revision, 'marker' => 'none', 'slots' => ['amount' => '₱500'], 'indicator_keys' => ['input.location', 'input.selfie', 'input.signature']];
    $response = $this->postJson(route('x-change.stamps.preview'), $payload)->assertOk();
    $image = imagecreatefromstring(base64_decode(explode(',', $response->json('image'), 2)[1]));
    $presentation = app(GdRiderStampClaimShareCardRenderer::class)->systemIndicators(app(StampCatalog::class)->presentation($id, $revision, 'bottom_right', '₱500'), $payload['indicator_keys'], 'bottom_right', 'none', '₱500');
    $bounds = array_last($presentation['design']->document['layers']);
    expect($bounds['y'])->toBeGreaterThan(155)->toBeLessThan(255);
    foreach ([5, 53, 101] as $offset) {
        $pixel = imagecolorsforindex($image, imagecolorat($image, $bounds['x'] + $offset, $bounds['y'] + 20));
        expect([$pixel['red'], $pixel['green'], $pixel['blue']])->toBe([4, 120, 87]);
    }
    imagedestroy($image);
    $empty = $this->postJson(route('x-change.stamps.preview'), [...$payload, 'indicator_keys' => []])->assertOk();
    expect($response->json('image'))->not->toBe($empty->json('image'));
})->with(['cats', 'steampunk']);

it('keeps badge composition shared with issued rendering and clear of a top-right QR', function (): void {
    $renderer = app(GdRiderStampClaimShareCardRenderer::class);
    $design = app(StampCatalog::class)->resolve('cats', 7);
    $keys = ['input.location', 'input.selfie', 'input.signature'];
    $normal = $renderer->systemIndicators($design, $keys);
    $moved = $renderer->systemIndicators($design, $keys, 'top_right');
    $normalLayer = array_last($normal['design']->document['layers']);
    $movedLayer = array_last($moved['design']->document['layers']);
    expect($movedLayer['x'] + $movedLayer['width'])->toBeLessThanOrEqual(900)
        ->and($normalLayer['x'])->toBe($movedLayer['x'])
        ->and($normal['images'])->toBe($moved['images'])
        ->and($renderer->systemIndicators($design, [...$keys, 'validation.selfie', 'validation.signature', 'validation.location'])['images'])->toBe($normal['images'])
        ->and($renderer->systemIndicators($design, [])['design'])->toBe($design);
    expect(array_last($design->document['layers'])['slot'] ?? null)->not->toBe('claim_indicators');
});

it('rejects unbounded or malformed preview indicator keys', function (array $keys): void {
    $this->postJson(route('x-change.stamps.preview'), ['design_id' => 'cats', 'revision' => 7, 'slots' => [], 'indicator_keys' => $keys])->assertUnprocessable();
})->with([[array_fill(0, 33, 'input.selfie')], [['<script>']]]);

it('shows a branded sample identifier without using it as the draft QR destination', function (): void {
    $before = Voucher::count();
    $response = $this->postJson(route('x-change.stamps.preview'), [
        'design_id' => 'cats', 'revision' => 7, 'position' => 'top_right', 'marker' => 'both',
        'slots' => ['brand' => 'Pay Code', 'amount' => '₱500'],
    ])->assertOk()->assertJsonPath('identifier.label', '|| AA317 ||')->assertJsonPath('identifier.sample', true);
    expect($response->json('marker_bounds.y'))->toBeGreaterThanOrEqual($response->json('identifier.bounds.y') + $response->json('identifier.bounds.height'));
    $bytes = base64_decode(explode(',', $response->json('image'), 2)[1]);
    $decoded = (new QrReader($bytes, QrReader::SOURCE_TYPE_BLOB, false))->text();
    expect($decoded)->toContain('/x/claim')->not->toContain('AA317');
    expect(Voucher::count())->toBe($before);
    $this->postJson(route('x-change.stamps.preview'), [
        'design_id' => 'cats', 'revision' => 7, 'marker' => 'code', 'code' => 'ABCD1234', 'slots' => ['amount' => '₱500'],
    ])->assertOk()->assertJsonPath('identifier.label', '|| ABCD1234 ||')->assertJsonPath('identifier.sample', false)->assertJsonPath('marker_bounds', null);
});

it('keeps only deduplicated claim requirements beside a long amount without changing its value', function (): void {
    $renderer = app(GdRiderStampClaimShareCardRenderer::class);
    $amount = '₱999,999,999,999.99';
    $design = app(StampCatalog::class)->presentation('cats', 7, 'bottom_right', $amount);
    $keys = ['outcome.provider_disbursement', 'input.signature', 'validation.signature', 'input.selfie', 'input.location', 'input.mobile', 'input.name', 'input.otp'];
    $composition = $renderer->systemIndicators($design, $keys, 'bottom_right', 'qr', $amount);
    $badge = array_last($composition['design']->document['layers']);
    expect($badge['width'])->toBe(280);
    expect($badge['x'] + $badge['width'])->toBeLessThanOrEqual(900);
    $text = array_values(array_filter((new Renderer)->layout($composition['design'], ['amount' => $amount], $composition['images']), fn (array $layer): bool => ($layer['slot'] ?? null) === 'amount'));
    expect($text)->toHaveCount(1);
    expect($text[0]['text'])->toBe($amount);
    expect($text[0]['x'] + $text[0]['width'])->toBeLessThan($badge['x']);
    expect($badge['y'])->toBeGreaterThanOrEqual($text[0]['y']);
    expect($renderer->systemIndicators($design, ['outcome.provider_disbursement'])['images'])->toBeEmpty();
    expect($renderer->systemIndicators($design, ['input.selfie', 'validation.selfie'], 'bottom_right', 'qr', $amount)['images'])->toBe($renderer->systemIndicators($design, ['input.selfie'], 'bottom_right', 'qr', $amount)['images']);
});

it('renders canonical instruction previews for all installed designs without persisting vouchers', function (): void {
    Http::preventStrayRequests();
    $before = Voucher::count();
    foreach (app(StampCatalog::class)->options() as $design) {
        $instructions = validVoucherInstructions()->toArray();
        data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => $design['id'], 'design_version' => $design['revision'], 'artwork_source' => 'x_change']);
        $response = $this->postJson(route('x-change.stamps.preview'), ['instructions' => $instructions])->assertOk();
        $contents = base64_decode(explode(',', $response->json('image'), 2)[1], true);
        expect(array_slice(getimagesizefromstring($contents), 0, 2))->toBe([1200, 630]);
    }
    expect(Voucher::count())->toBe($before);
    Http::assertNothingSent();
});

it('renders the exact issued composition when preview identifiers and timing are resolved identically', function (string $design): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    $instructions = validVoucherInstructions()->toArray();
    $instructions['provider'] = 'netbank';
    $instructions['expires_at'] = '2030-12-25T12:00:00+00:00';
    data_set($instructions, 'inputs.fields', ['selfie', 'signature']);
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => $design, 'design_version' => 1, 'artwork_source' => 'x_change', 'claim_marker' => 'qr']);
    $user = actingAsTestUser();
    $issued = app(PayCodeIssuanceContract::class)->issue($user, $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $artifact = app(RiderStampArtifactStoreContract::class)->read($voucher);
    $preview = app(StampPreviewComposer::class)->preview($instructions, $voucher->code);

    expect(hash('sha256', base64_decode(explode(',', $preview['image'], 2)[1], true)))->toBe(hash('sha256', $artifact->contents));
    Http::assertNothingSent();
})->with(['cats', 'steampunk']);

it('previews custom splash through the export composer and ignores forged artifact metadata', function (): void {
    Storage::fake('local');
    Http::preventStrayRequests();
    $image = imagecreatetruecolor(20, 20);
    imagefill($image, 0, 0, imagecolorallocate($image, 210, 35, 90));
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'rider.splash', '<img src="data:image/png;base64,'.base64_encode($png).'" /><h2>Hello</h2>');
    data_set($instructions, 'rider.stamp', ['version' => 2, 'artwork_source' => 'splash', 'copy_source' => 'splash', 'claim_marker' => 'qr']);
    data_set($instructions, 'metadata.custom.rider_stamp_artifact', ['sha256' => str_repeat('a', 64)]);
    data_set($instructions, 'metadata.custom.rider_splash_artwork', ['sha256' => str_repeat('b', 64)]);

    $response = $this->postJson(route('x-change.stamps.preview'), ['instructions' => $instructions])->assertOk();
    $contents = base64_decode(explode(',', $response->json('image'), 2)[1], true);
    expect(array_slice(getimagesizefromstring($contents), 0, 2))->toBe([1200, 630]);
    expect(Voucher::count())->toBe(0);
    Http::assertNothingSent();
});

it('rejects invalid canonical preview inputs before fetching artwork', function (array $override): void {
    Http::preventStrayRequests();
    $instructions = array_replace_recursive(validVoucherInstructions()->toArray(), $override);
    $this->postJson(route('x-change.stamps.preview'), ['instructions' => $instructions])->assertUnprocessable();
    expect(Voucher::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    [['cash' => ['amount' => -1]]],
    [['rider' => ['stamp' => ['version' => 3, 'design_id' => 'cats', 'design_version' => 999]]]],
    [['rider' => ['url' => 'file:///etc/passwd']]],
    [['ttl' => 'not a duration']],
    [['rider' => 'not an object']],
    [['metadata' => ['custom' => 'not an object']]],
    [['rider' => ['splash_timeout' => 'not a number']]],
]);

it('uses the same allow-listed URL artwork pipeline in the guest canonical preview', function (): void {
    Http::preventStrayRequests();
    $image = imagecreatetruecolor(20, 20);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
    Http::fake([
        'https://open.spotify.com/track/example' => Http::response('<meta property="og:title" content="Example"><meta property="og:image" content="https://i.scdn.co/image/example">', 200, ['Content-Type' => 'text/html']),
        'https://i.scdn.co/image/example' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'rider.url', 'https://open.spotify.com/track/example');
    data_set($instructions, 'rider.stamp', ['version' => 2, 'artwork_source' => 'url', 'copy_source' => 'url']);

    $this->postJson(route('x-change.stamps.preview'), ['instructions' => $instructions])->assertOk()->assertJsonPath('marker_bounds.width', 190);
    expect(Voucher::count())->toBe(0);
    Http::assertSentCount(2);
});

it('refuses private-network splash artwork in the guest canonical preview', function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'rider.splash', '<img src="http://127.0.0.1/private.png">');
    data_set($instructions, 'rider.stamp', ['version' => 2, 'artwork_source' => 'splash']);

    $this->postJson(route('x-change.stamps.preview'), ['instructions' => $instructions])->assertUnprocessable();
    expect(Voucher::count())->toBe(0);
    Http::assertNothingSent();
});

it('matches the issued PNG for custom splash artwork with identical resolved values', function (): void {
    Storage::fake('local');
    Http::preventStrayRequests();
    $image = imagecreatetruecolor(20, 20);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 70, 160));
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
    Http::fake(['https://raw.githubusercontent.com/example/artwork/main/splash.png' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
    $instructions = validVoucherInstructions()->toArray();
    $instructions['provider'] = 'netbank';
    $instructions['expires_at'] = '2030-12-25T12:00:00+00:00';
    data_set($instructions, 'rider.splash', '<img src="https://raw.githubusercontent.com/example/artwork/main/splash.png" /><h2>Hello</h2>');
    data_set($instructions, 'rider.stamp', ['version' => 2, 'artwork_source' => 'splash', 'copy_source' => 'splash', 'claim_marker' => 'qr']);
    $instructions = app(RiderSplashArtworkSnapshotterContract::class)->prepare($instructions);
    $user = actingAsTestUser();
    $issued = app(PayCodeIssuanceContract::class)->issue($user, $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $artifact = app(RiderStampArtifactStoreContract::class)->read($voucher);

    $preview = app(StampPreviewComposer::class)->preview($instructions, $voucher->code);

    expect(hash('sha256', base64_decode(explode(',', $preview['image'], 2)[1], true)))->toBe(hash('sha256', $artifact->contents));
    Http::assertSentCount(2);
});

it('renders a minimal blank draft without requiring issuance-only fields', function (): void {
    Http::preventStrayRequests();
    $this->postJson(route('x-change.stamps.preview'), ['instructions' => ['cash' => ['amount' => 0, 'currency' => 'PHP'], 'claim' => ['default_outcome' => 'provider_disbursement']]])
        ->assertOk()->assertJsonPath('image', fn (string $image): bool => str_starts_with($image, 'data:image/png;base64,'));
    expect(Voucher::count())->toBe(0);
    Http::assertNothingSent();
});

it('honors the configured issuance composer for canonical previews without inventing interaction bounds', function (): void {
    Http::preventStrayRequests();
    $image = imagecreatetruecolor(1200, 630);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
    $composer = Mockery::mock(RiderStampClaimCardComposerContract::class);
    $composer->shouldReceive('compose')->once()->withArgs(fn (Voucher $voucher, string $url): bool => ! $voucher->exists && $voucher->code === 'AA317' && $url === route('x-change.claim.start'))
        ->andReturn(new ClaimShareCardData(contents: $png, etag: '"custom"'));
    app()->instance(RiderStampClaimCardComposerContract::class, $composer);

    $this->postJson(route('x-change.stamps.preview'), ['instructions' => validVoucherInstructions()->toArray()])
        ->assertOk()->assertJsonPath('image', 'data:image/png;base64,'.base64_encode($png))
        ->assertJsonPath('marker_bounds', null)->assertJsonPath('requirements.keys', []);
    expect(Voucher::count())->toBe(0);
    Http::assertNothingSent();
});
