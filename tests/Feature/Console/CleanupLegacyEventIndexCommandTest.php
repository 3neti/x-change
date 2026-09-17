<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;

it('deletes only the legacy event index without flushing other cache data', function (): void {
    Cache::forever('xchange:events:index', array_fill(0, 10_000, 'legacy-event'));
    Cache::forever('xchange:events:v2:recent-buckets', ['current-bucket']);
    Cache::forever('unrelated-session-value', 'preserved');

    $this->artisan('x-change:events:cleanup-legacy-index')
        ->expectsOutputToContain('Legacy event index deleted or already absent.')
        ->assertSuccessful();

    expect(Cache::get('xchange:events:index'))->toBeNull()
        ->and(Cache::get('xchange:events:v2:recent-buckets'))->toBe(['current-bucket'])
        ->and(Cache::get('unrelated-session-value'))->toBe('preserved');
});
