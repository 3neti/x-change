<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\ServiceProvider;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Data\Claim\RiderStampCopyData;
use LBHurtado\XChange\Http\Middleware\RequireCurrentAgreementAcceptance;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;
use LBHurtado\XChange\Services\RiderStampDesignRegistry;
use LBHurtado\XChange\Services\Stamp\StampCatalog;
use LBHurtado\XChange\Services\Stamp\StampProviderIdentity;
use LBHurtado\XStamp\Design;
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
    expect(array_unique(array_column($catalog->options(), 'id')))->toContain('steampunk', 'cats', 'valentine');
    expect(app(RiderStampDesignRegistry::class)->resolve('x-change-steampunk', 1)['palette']['start'])->toBe([49, 34, 20]);
});

it('pins system designs into issued artifacts without changing their revisions', function (string $id, array $rgb): void {
    Storage::fake('local');
    $user = actingAsTestUser();
    $instructions = validVoucherInstructions()->toArray();
    data_set($instructions, 'rider.stamp', ['version' => 3, 'design_id' => $id, 'design_version' => 1, 'artwork_source' => 'x_change', 'claim_marker' => 'qr', 'claim_marker_position' => 'bottom_right']);
    $issued = app(PayCodeIssuanceContract::class)->issue($user, $instructions);
    $voucher = Voucher::findOrFail($issued['voucher_id']);
    $artifact = app(RiderStampArtifactStoreContract::class)->read($voucher);
    expect($artifact)->not->toBeNull();
    $image = imagecreatefromstring($artifact->contents);
    $pixel = imagecolorsforindex($image, imagecolorat($image, 0, 0));
    expect([$pixel['red'], $pixel['green'], $pixel['blue']])->toBe($rgb);
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
    $expected = (new Renderer)->png($design, [...$slots, 'provider_name' => $name], isset($identity['logo']) ? ['provider_logo' => $identity['logo']] : []);
    expect($response->json('image'))->toBe('data:image/png;base64,'.base64_encode($expected));
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
        'design_id' => 'steampunk', 'revision' => 7, 'provider' => $provider,
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
