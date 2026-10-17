<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\ServiceProvider;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Http\Middleware\RequireCurrentAgreementAcceptance;
use LBHurtado\XChange\Services\RiderStampDesignRegistry;
use LBHurtado\XChange\Services\Stamp\StampCatalog;

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
    expect(array_column($catalog->options(), 'id'))->toBe(['steampunk', 'cats', 'valentine']);
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
        ->assertJsonCount(3, 'props.xchange.stamps')
        ->assertJsonPath('props.xchange.stamps.1.id', 'cats');
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
