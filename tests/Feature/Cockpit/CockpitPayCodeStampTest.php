<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailAccess;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeStampProjection;

beforeEach(function (): void {
    Storage::fake('local');
});

it('changes reconstructed image URLs when the voucher is updated', function (): void {
    $voucher = issueVoucher();
    $projection = app(CockpitPayCodeStampProjection::class);
    $original = $projection->forVoucher($voucher);

    $this->travel(1)->seconds();
    $voucher->forceFill(['metadata' => [...$voucher->metadata, 'stamp_revision_test' => true]])->save();
    $updated = $projection->forVoucher($voucher->fresh());

    expect($original['image_url'])->not->toBe($updated['image_url'])
        ->and($original['thumbnail_url'])->not->toBe($updated['thumbnail_url'])
        ->and($updated['download_url'])->toContain('&download=1');
});

it('shows the saved stamp in Cockpit projections and serves full and thumbnail images', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $artifact = app(RiderStampArtifactStoreContract::class)->materialize(
        $voucher,
        route('x-change.claim.show', ['code' => $voucher->code]),
    );
    expect(app(RiderStampArtifactStoreContract::class)->descriptor($voucher->fresh()))->not->toBeNull();
    $fullUrl = route('x-change.cockpit.pay-codes.stamp.show', [
        'code' => $voucher->code,
        'size' => 'full',
        'sha256' => $artifact->sha256,
    ]);
    $thumbnailUrl = route('x-change.cockpit.pay-codes.stamp.show', [
        'code' => $voucher->code,
        'size' => 'thumbnail',
        'sha256' => $artifact->sha256,
    ]);

    $this->actingAs($owner)->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('props.pay_codes_read_model.records.0.stamp.available', true)
        ->assertJsonPath('props.pay_codes_read_model.records.0.stamp.thumbnail_url', $thumbnailUrl);

    $this->actingAs($owner)->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code, 'tab' => 'stamp']))
        ->assertOk()
        ->assertJsonPath('props.read_model.voucher.stamp.image_url', $fullUrl);

    $this->actingAs($owner)->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]))
        ->assertJsonPath('props.read_model.voucher.stamp.fallback_url', route('x-change.cockpit.pay-codes.stamp.show', [
            'code' => $voucher->code,
            'size' => 'full',
            'sha256' => 'current',
            'revision' => $voucher->fresh()->updated_at->format('Uu'),
        ]));

    $full = $this->actingAs($owner)->get($fullUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    expect(hash('sha256', (string) $full->getContent()))->toBe($artifact->sha256);

    $thumbnail = $this->actingAs($owner)->get($thumbnailUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    expect(getimagesizefromstring((string) $thumbnail->getContent()))->toMatchArray([320, 168]);
    Storage::disk('local')->assertExists('x-change/claim/stamp-thumbnails/'.$artifact->sha256.'.png');

    $this->actingAs($owner)->get($fullUrl.'?download=1')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="Pay-Code-'.$voucher->code.'.png"');
});

it('keeps Cockpit stamp images private even when a code and digest are known', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $artifact = app(RiderStampArtifactStoreContract::class)->materialize(
        $voucher,
        route('x-change.claim.show', ['code' => $voucher->code]),
    );
    $systemPrincipal = enableNetbankTreasuryForTests();
    $url = route('x-change.cockpit.pay-codes.stamp.show', [
        'code' => $voucher->code,
        'size' => 'full',
        'sha256' => $artifact->sha256,
    ]);

    auth()->logout();
    Route::get('/login', static fn () => response('Login'))->name('login');
    Route::getRoutes()->refreshNameLookups();
    $this->get($url)->assertRedirect(route('login'));

    $otherOwner = actingAsTestUser();
    $this->actingAs($otherOwner)->get($url)->assertNotFound();
    $this->actingAs($owner)->get(str_replace($artifact->sha256, str_repeat('a', 64), $url))
        ->assertNotFound();

    expect(app(CockpitPayCodeDetailAccess::class)->canView($systemPrincipal, $voucher->fresh()))->toBeTrue();
    $this->actingAs($systemPrincipal)->get($url)->assertOk();
});

it('keeps a collectible Pay Code stamp accessible in Cockpit after its collection closes', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'voucher_type' => 'payable',
        'target_amount' => 25,
        'metadata' => [
            'flow_type' => 'collectible',
            'collection_wallet_id' => (string) $owner->wallet()->where('slug', 'platform')->sole()->getKey(),
        ],
    ]));
    $artifact = app(RiderStampArtifactStoreContract::class)->materialize(
        $voucher,
        route('x-change.claim.show', ['code' => $voucher->code]),
    );
    $voucher->forceFill(['expires_at' => now()->subHour()])->save();

    $this->actingAs($owner)->get(route('x-change.cockpit.pay-codes.stamp.show', [
        'code' => $voucher->code,
        'size' => 'full',
        'sha256' => $artifact->sha256,
    ]))->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('renders cached current thumbnails for legacy vouchers and missing issued bytes', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $currentThumbnailUrl = route('x-change.cockpit.pay-codes.stamp.show', [
        'code' => $voucher->code,
        'size' => 'thumbnail',
        'sha256' => 'current',
        'revision' => $voucher->updated_at->format('Uu'),
    ]);

    $this->actingAs($owner)->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', $voucher->code))
        ->assertOk()
        ->assertJsonPath('props.read_model.voucher.stamp.available', true)
        ->assertJsonPath('props.read_model.voucher.stamp.reconstructed', true)
        ->assertJsonPath('props.read_model.voucher.stamp.thumbnail_url', $currentThumbnailUrl);

    $currentThumbnail = $this->actingAs($owner)->get($currentThumbnailUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    expect(getimagesizefromstring((string) $currentThumbnail->getContent()))->toMatchArray([320, 168]);
    $this->actingAs($owner)->get(route('x-change.cockpit.pay-codes.stamp.show', [
        'code' => $voucher->code,
        'size' => 'full',
        'sha256' => 'current',
    ]))->assertOk()->assertHeader('Content-Type', 'image/png');

    $artifact = app(RiderStampArtifactStoreContract::class)->materialize(
        $voucher,
        route('x-change.claim.show', ['code' => $voucher->code]),
    );
    Storage::disk('local')->delete('x-change/claim/stamp-artifacts/'.$artifact->sha256.'.png');

    $savedThumbnailUrl = route('x-change.cockpit.pay-codes.stamp.show', [
        'code' => $voucher->code,
        'size' => 'thumbnail',
        'sha256' => $artifact->sha256,
    ]);
    $this->actingAs($owner)->get($savedThumbnailUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $this->actingAs($owner)->get(route('x-change.cockpit.pay-codes.stamp.show', [
        'code' => $voucher->code,
        'size' => 'full',
        'sha256' => $artifact->sha256,
    ]))->assertNotFound();

    auth()->logout();
    Route::get('/login', static fn () => response('Login'))->name('login');
    Route::getRoutes()->refreshNameLookups();
    $this->get($currentThumbnailUrl)->assertRedirect(route('login'));
});
