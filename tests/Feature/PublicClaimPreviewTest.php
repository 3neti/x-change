<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\ServiceProvider;
use LBHurtado\Voucher\Events\VouchersGenerated;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

beforeEach(function (): void {
    app()->register(ServiceProvider::class);
    $this->withoutVite();
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'preview-escrow');
    config()->set('x-change.public_issuance_escrow.principal.legal_name', 'Preview Escrow');
    config()->set('x-change.public_issuance_escrow.principal.authorization_reference', 'preview-test');
    CommercialPrincipal::query()->create([
        'reference' => 'preview-escrow',
        'legal_name' => 'Preview Escrow',
        'authorization_reference' => 'preview-test',
        'active' => true,
    ]);
    $this->withCredentials()->withCookie(config('session.cookie'), str_repeat('a', 40));
});

function publicClaimPreviewPayload(): array
{
    return [
        'cash' => ['amount' => 25, 'currency' => 'PHP'],
        'inputs' => ['fields' => []],
        'feedback' => ['mobile' => null],
        'rider' => ['message' => null, 'url' => null, 'splash' => null],
        'dry_run' => true,
    ];
}

it('renders a guest preview using the escrow issuer without financial or delivery side effects', function (): void {
    $this->freezeTime();
    Http::preventStrayRequests();
    Queue::fake();
    Event::fake([VouchersGenerated::class]);
    $voucherCount = Voucher::query()->count();
    $checkoutCount = Checkout::query()->count();
    $orderCount = PayCodeIssuanceFundingOrder::query()->count();

    $response = $this->postJson('/x/auto-generate/claim-previews', [
        ...publicClaimPreviewPayload(), 'issuer_id' => 99999,
        'metadata' => ['issuer_id' => '99999'],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('safety.money_movement', false)
        ->assertJsonPath('safety.provider_calls', false)
        ->assertJsonPath('safety.claim_submission', false)
        ->assertJsonPath('expires_at', now()->addMinutes(30)->toIso8601String());
    $artifact = ClaimPreviewArtifact::query()->where('reference', $response->json('reference'))->sole();
    expect($artifact->owner_id)->toBe((int) CommercialPrincipal::query()->sole()->getKey())
        ->and(data_get($artifact->metadata, 'access.surface'))->toBe('public')
        ->and($response->json('journey.steps.0.preview_url'))->toStartWith('/x/auto-generate/claim-previews/')
        ->and($response->json('exports.pdf_url'))->toStartWith('/x/auto-generate/claim-previews/')
        ->and(Voucher::query()->count())->toBe($voucherCount)
        ->and(Checkout::query()->count())->toBe($checkoutCount)
        ->and(PayCodeIssuanceFundingOrder::query()->count())->toBe($orderCount);
    expect($response->getContent())->not->toContain('guest_scope')->not->toContain('artifact_path');
    Queue::assertNothingPushed();
    Event::assertNotDispatched(VouchersGenerated::class);
    Http::assertNothingSent();
});

it('serves guest manifests steps frames and exports only to their creating session', function (string $resource): void {
    $response = $this->postJson('/x/auto-generate/claim-previews', publicClaimPreviewPayload())->assertSuccessful();
    $reference = $response->json('reference');
    $artifact = ClaimPreviewArtifact::query()->where('reference', $reference)->sole();
    $step = $response->json('journey.steps.0.key');
    if ($resource === 'frames') {
        File::ensureDirectoryExists(storage_path('app/'.$artifact->artifact_path.'/screenshots'));
        File::put(storage_path('app/'.$artifact->artifact_path.'/screenshots/test.png'), 'preview-frame');
        $metadata = $artifact->metadata;
        data_set($metadata, 'journey.steps.0.frame', ['artifact' => 'screenshots/test.png', 'mime_type' => 'image/png']);
        $artifact->update(['metadata' => $metadata]);
    }
    $suffix = match ($resource) {
        'manifest' => '',
        'steps' => '/steps/'.$step,
        'frames' => '/frames/'.$step,
        'pdf' => '/exports/pdf',
        'html' => '/exports/html',
    };
    $url = '/x/auto-generate/claim-previews/'.$reference.$suffix;
    $this->get($url)->assertSuccessful()->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->withCookie(config('session.cookie'), str_repeat('b', 40));
    $this->getJson($url)->assertNotFound();
    $this->withCookie(config('session.cookie'), str_repeat('a', 40));
    $this->travel(30)->minutes();
    $this->getJson($url)->assertNotFound();
})->with(['manifest', 'steps', 'frames', 'pdf', 'html']);

it('isolates identical guest designs and keeps cache hits within the original expiry', function (): void {
    $this->freezeTime();
    $first = $this->postJson('/x/auto-generate/claim-previews', publicClaimPreviewPayload())->assertSuccessful();
    $this->travel(5)->minutes();
    $cached = $this->postJson('/x/auto-generate/claim-previews', publicClaimPreviewPayload())->assertSuccessful();
    $cached->assertJsonPath('cache_hit', true)->assertJsonPath('reference', $first->json('reference'))
        ->assertJsonPath('expires_at', $first->json('expires_at'));

    $this->withCookie(config('session.cookie'), str_repeat('b', 40));
    $second = $this->postJson('/x/auto-generate/claim-previews', publicClaimPreviewPayload())->assertSuccessful();
    expect($second->json('reference'))->not->toBe($first->json('reference'));
    $artifacts = ClaimPreviewArtifact::query()->get();
    expect($artifacts->pluck('artifact_path')->unique())->toHaveCount(2);
});

it('rejects expired guest previews and regenerates them with a fresh deadline', function (): void {
    $this->freezeTime();
    $first = $this->postJson('/x/auto-generate/claim-previews', publicClaimPreviewPayload())->assertSuccessful();
    $this->travel(30)->minutes();
    $this->getJson('/x/auto-generate/claim-previews/'.$first->json('reference'))->assertNotFound();
    $this->postJson('/x/auto-generate/claim-previews', publicClaimPreviewPayload())->assertSuccessful()
        ->assertJsonPath('cache_hit', false)
        ->assertJsonPath('expires_at', now()->addMinutes(30)->toIso8601String());
});

it('refreshes guest previews explicitly with a new deadline', function (): void {
    $this->freezeTime();
    $this->postJson('/x/auto-generate/claim-previews', publicClaimPreviewPayload())->assertSuccessful();
    $this->travel(5)->minutes();
    $this->postJson('/x/auto-generate/claim-previews', [...publicClaimPreviewPayload(), 'refresh_preview' => true])
        ->assertSuccessful()->assertJsonPath('cache_hit', false)
        ->assertJsonPath('expires_at', now()->addMinutes(30)->toIso8601String());
});

it('applies public instruction restrictions before rendering', function (array $override, string $field): void {
    $this->postJson('/x/auto-generate/claim-previews', array_replace_recursive(publicClaimPreviewPayload(), $override))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(ClaimPreviewArtifact::query()->count())->toBe(0);
})->with([
    'webhook' => [['feedback' => ['webhook' => 'https://example.com/hook']], 'feedback.webhook'],
    'invitation' => [['onboarding' => true], 'onboarding'],
    'batch' => [['count' => 2], 'count'],
]);

it('rejects invalid simulation states without rendering', function (): void {
    $this->postJson('/x/auto-generate/claim-previews', [...publicClaimPreviewPayload(), 'preview_state' => 'invented'])
        ->assertUnprocessable()->assertJsonValidationErrors('preview_state');
    expect(ClaimPreviewArtifact::query()->count())->toBe(0);
});

it('does not expose cockpit artifacts through the public route family', function (): void {
    actingAsTestUser(0);
    config()->set('x-change.legal.eula.enabled', false);
    $response = $this->postJson('/x/cockpit/quick-generate/claim-previews', publicClaimPreviewPayload())->assertSuccessful();
    $artifact = ClaimPreviewArtifact::query()->where('reference', $response->json('reference'))->sole();
    $artifact->update(['owner_type' => CommercialPrincipal::query()->sole()->getMorphClass(), 'owner_id' => CommercialPrincipal::query()->sole()->getKey()]);
    $this->getJson('/x/auto-generate/claim-previews/'.$artifact->reference)->assertNotFound();
});

it('does not expose guest artifacts to their escrow owner through cockpit routes', function (): void {
    $response = $this->postJson('/x/auto-generate/claim-previews', publicClaimPreviewPayload())->assertSuccessful();
    config()->set('x-change.legal.eula.enabled', false);
    $this->actingAs(CommercialPrincipal::query()->sole());
    $this->getJson('/x/cockpit/quick-generate/claim-previews/'.$response->json('reference'))->assertNotFound();
});

it('throttles guest generation without sharing the escrow owner bucket', function (): void {
    $payload = [...publicClaimPreviewPayload(), 'preview_state' => 'invalid'];
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->postJson('/x/auto-generate/claim-previews', $payload)->assertUnprocessable();
    }
    $this->postJson('/x/auto-generate/claim-previews', $payload)->assertTooManyRequests();
    $this->withCookie(config('session.cookie'), str_repeat('b', 40));
    $this->postJson('/x/auto-generate/claim-previews', $payload)->assertUnprocessable();
});

it('requires csrf protection for guest preview generation', function (): void {
    $this->app['env'] = 'local';
    $this->postJson('/x/auto-generate/claim-previews', publicClaimPreviewPayload())->assertStatus(419);
    expect(ClaimPreviewArtifact::query()->count())->toBe(0);
});

it('disposes temporary vouchers when artifact rendering fails', function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    Event::fake([VouchersGenerated::class]);
    $voucherCount = Voucher::query()->count();
    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('put')->withArgs(fn (string $path): bool => str_ends_with($path, '/claim-walkthrough-storyboard.json'))
        ->once()->andThrow(new RuntimeException('Preview artifact unavailable.'));
    $this->app->instance(Filesystem::class, $files);

    $this->postJson('/x/auto-generate/claim-previews', publicClaimPreviewPayload())->assertStatus(500);

    expect(Voucher::query()->count())->toBe($voucherCount)
        ->and(ClaimPreviewArtifact::query()->count())->toBe(0);
    Queue::assertNothingPushed();
    Event::assertNotDispatched(VouchersGenerated::class);
    Http::assertNothingSent();
});
