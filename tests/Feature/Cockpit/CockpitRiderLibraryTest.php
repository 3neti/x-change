<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Actions\Cockpit\RememberRiderLibraryUsage;
use LBHurtado\XChange\Models\RiderLibraryEntry;

it('stores encrypted owner-scoped Rider Links and deduplicates them', function () {
    $operator = actingAsTestUser();

    $payload = [
        'kind' => 'url',
        'label' => 'Friday playlist',
        'payload' => [
            'url' => 'https://open.spotify.com/track/example?token=private',
        ],
    ];

    $this->post(route('x-change.cockpit.rider-library.store'), $payload)
        ->assertRedirect();
    $this->post(route('x-change.cockpit.rider-library.store'), $payload)
        ->assertRedirect();

    $entry = RiderLibraryEntry::query()->sole();
    $raw = DB::table('x_change_rider_library_entries')
        ->where('id', $entry->getKey())
        ->first();

    expect($entry->owner_type)->toBe($operator->getMorphClass())
        ->and($entry->owner_id)->toBe((string) $operator->getKey())
        ->and($entry->kind->value)->toBe('url')
        ->and($entry->label_ciphertext)->toBe('Friday playlist')
        ->and($entry->content_ciphertext)->toBe([
            'url' => 'https://open.spotify.com/track/example?token=private',
        ])
        ->and($entry->saved_at)->not->toBeNull()
        ->and($entry->pinned_at)->not->toBeNull()
        ->and(RiderLibraryEntry::query()->count())->toBe(1)
        ->and($raw->content_ciphertext)->not->toContain('open.spotify.com')
        ->and($raw->content_ciphertext)->not->toContain('private')
        ->and($raw->label_ciphertext)->not->toContain('Friday playlist')
        ->and($entry->toArray())->not->toHaveKeys([
            'content_ciphertext',
            'content_fingerprint',
            'label_ciphertext',
        ]);
});

it('sanitizes HTML Rider Splashes before encrypted persistence', function () {
    actingAsTestUser();

    $this->post(route('x-change.cockpit.rider-library.store'), [
        'kind' => 'splash',
        'payload' => [
            'format' => 'html',
            'splash' => '<div>Hello</div><script>alert("unsafe")</script>',
        ],
    ])->assertRedirect();

    $entry = RiderLibraryEntry::query()->sole();

    expect(data_get($entry->content_ciphertext, 'format'))->toBe('html')
        ->and(data_get($entry->content_ciphertext, 'splash'))->toContain('Hello')
        ->and(data_get($entry->content_ciphertext, 'splash'))->not->toContain('<script')
        ->and(data_get($entry->content_ciphertext, 'meta.sanitized'))->toBeTrue()
        ->and(data_get($entry->content_ciphertext, 'meta.html_profile'))->toBe('rider_splash');
});

it('hydrates only the current owners Rider Library', function () {
    $owner = actingAsTestUser();

    $this->post(route('x-change.cockpit.rider-library.store'), [
        'kind' => 'url',
        'payload' => ['url' => 'https://example.test/owner-link'],
    ])->assertRedirect();

    $entry = RiderLibraryEntry::query()->sole();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonCount(1, 'props.rider_library')
        ->assertJsonPath('props.rider_library.0.reference', $entry->reference)
        ->assertJsonPath('props.rider_library.0.payload.url', 'https://example.test/owner-link')
        ->assertJsonMissingPath('props.rider_library.0.id')
        ->assertJsonMissingPath('props.rider_library.0.owner_id')
        ->assertJsonMissingPath('props.rider_library.0.owner_type')
        ->assertJsonMissingPath('props.rider_library.0.content_ciphertext')
        ->assertJsonMissingPath('props.rider_library.0.label_ciphertext')
        ->assertJsonMissingPath('props.rider_library.0.content_fingerprint');

    $other = actingAsTestUser();

    expect($other->is($owner))->toBeFalse();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonCount(0, 'props.rider_library');

    $this->patch(
        route('x-change.cockpit.rider-library.pin', $entry),
        ['pinned' => false],
    )->assertForbidden();
    $this->delete(
        route('x-change.cockpit.rider-library.forget', $entry),
    )->assertForbidden();

    expect($entry->fresh())->not->toBeNull();
});

it('unpins saved Riders without making them disposable and may forget them', function () {
    actingAsTestUser();

    $this->post(route('x-change.cockpit.rider-library.store'), [
        'kind' => 'url',
        'payload' => ['url' => 'https://example.test/saved'],
    ])->assertRedirect();

    $entry = RiderLibraryEntry::query()->sole();

    $this->patch(
        route('x-change.cockpit.rider-library.pin', $entry),
        ['pinned' => false],
    )->assertRedirect();

    $entry->refresh();

    expect($entry->saved_at)->not->toBeNull()
        ->and($entry->pinned_at)->toBeNull();

    $this->delete(
        route('x-change.cockpit.rider-library.forget', $entry),
    )->assertRedirect();

    expect($entry->fresh())->toBeNull();
});

it('remembers successful Rider use, increments duplicates, and prunes only recent entries', function () {
    config()->set(
        'x-change.cockpit.quick_generate.rider_library.recent_limit_per_kind',
        2,
    );
    $operator = actingAsTestUser();
    $remember = app(RememberRiderLibraryUsage::class);

    $this->post(route('x-change.cockpit.rider-library.store'), [
        'kind' => 'url',
        'label' => 'Keep me',
        'payload' => ['url' => 'https://example.test/saved'],
    ])->assertRedirect();

    foreach (['one', 'two', 'three'] as $path) {
        $remember->handle($operator, [
            'rider' => ['url' => "https://example.test/{$path}"],
        ]);
    }

    $remember->handle($operator, [
        'rider' => ['url' => 'https://example.test/three'],
    ]);

    $entries = RiderLibraryEntry::query()
        ->whereMorphedTo('owner', $operator)
        ->get();
    $recent = $entries->whereNull('saved_at');
    $usedTwice = $entries->first(
        fn (RiderLibraryEntry $entry): bool => data_get(
            $entry->content_ciphertext,
            'url',
        ) === 'https://example.test/three',
    );

    expect($entries)->toHaveCount(3)
        ->and($entries->whereNotNull('saved_at'))->toHaveCount(1)
        ->and($recent)->toHaveCount(2)
        ->and($usedTwice)->not->toBeNull()
        ->and($usedTwice?->use_count)->toBe(2)
        ->and($usedTwice?->first_used_at)->not->toBeNull()
        ->and($usedTwice?->last_used_at)->not->toBeNull();
});

it('rejects malformed Rider Library payloads', function (array $payload, string $error) {
    actingAsTestUser();

    $this->post(route('x-change.cockpit.rider-library.store'), $payload)
        ->assertSessionHasErrors($error);

    expect(RiderLibraryEntry::query()->count())->toBe(0);
})->with([
    'non-http Rider Link' => [[
        'kind' => 'url',
        'payload' => ['url' => 'javascript:alert(1)'],
    ], 'payload.url'],
    'missing Rider Link' => [[
        'kind' => 'url',
        'payload' => [],
    ], 'payload.url'],
    'missing Rider Splash' => [[
        'kind' => 'splash',
        'payload' => ['format' => 'plain'],
    ], 'payload.splash'],
    'unknown Rider Splash format' => [[
        'kind' => 'splash',
        'payload' => ['format' => 'script', 'splash' => 'Hello'],
    ], 'payload.format'],
]);
