<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Actions\Keepsake\PlanInstanceKeepsakeExport;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeCrypto;
use LBHurtado\XChange\Services\Keepsake\VerifyInstanceKeepsakeArchive;
use LBHurtado\XChange\Tests\Fakes\User;

it('keeps a dry run read only and returns a reusable plan hash', function () {
    Storage::fake('keepsakes');
    config()->set('x-change.instance_keepsake.disk', 'keepsakes');
    $user = User::query()->create([
        'name' => 'Keepsake User',
        'email' => 'keepsake@example.test',
        'password' => 'secret',
    ]);
    $updatedAt = $user->updated_at;

    $exitCode = Artisan::call('x-change:instance-keepsake:export', [
        '--all-users' => true,
        '--confirm-sensitive-export' => true,
        '--include' => ['accounts', 'blueprint'],
        '--include-personal-data' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['dry_run'])->toBeTrue()
        ->and($payload['writes_storage'])->toBeFalse()
        ->and($user->refresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(fakePayoutProvider()->checkStatusCallCount)->toBe(0);

    fakePayoutProvider()->assertNoDisbursementAttempted();
    Storage::disk('keepsakes')->assertDirectoryEmpty('/');
});

it('creates downloads and independently verifies an encrypted keepsake', function () {
    Http::preventStrayRequests();
    Storage::fake('keepsakes');
    config()->set('x-change.instance_keepsake.disk', 'keepsakes');
    config()->set('x-change.instance_keepsake.directory', 'x-change/instance-keepsakes');
    $this->withoutMiddleware(ShareXChangeBranding::class);
    $this->withoutMiddleware(ThrottleRequests::class);
    provisionTestSystemPrincipalForCommissioning();
    $keys = app(InstanceKeepsakeCrypto::class)->generateKeyPair();
    config()->set('x-change.instance_keepsake.public_key', $keys['public_key']);
    $user = User::query()->create([
        'name' => 'Keepsake User',
        'email' => 'keepsake@example.test',
        'password' => 'secret',
    ]);
    $planner = app(PlanInstanceKeepsakeExport::class);
    $plan = $planner->handle(
        allUsers: true,
        userIdentifiers: [],
        includes: ['accounts', 'blueprint'],
        includePersonalData: true,
        includeLocationData: false,
        allowIncomplete: false,
        materializeArtifacts: false,
    );
    DB::flushQueryLog();
    DB::enableQueryLog();

    $exitCode = Artisan::call('x-change:instance-keepsake:export', [
        '--all-users' => true,
        '--confirm-sensitive-export' => true,
        '--include' => ['accounts', 'blueprint'],
        '--include-personal-data' => true,
        '--create' => true,
        '--plan-hash' => $plan->hash,
        '--export-reference' => 'before-fresh-test',
        '--authorization-reference' => 'test-authorization-001',
        '--download-user' => $user->email,
        '--json' => true,
    ]);
    expect($exitCode)->toBe(0);
    $created = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $granteeLookup = collect(DB::getQueryLog())
        ->first(fn (array $query): bool => str_contains($query['query'], 'from "users"')
            && in_array($user->email, $query['bindings'], true));

    expect($granteeLookup)->not->toBeNull()
        ->and($granteeLookup['query'])->toContain('"email" = ?')
        ->and($granteeLookup['query'])->not->toContain('"id" = ?');
    DB::disableQueryLog();

    $archivePath = 'x-change/instance-keepsakes/before-fresh-test/instance-keepsake.xck';
    Storage::disk('keepsakes')->assertExists($archivePath);
    $localArchive = tempnam(sys_get_temp_dir(), 'downloaded-keepsake-');
    $privateKey = tempnam(sys_get_temp_dir(), 'keepsake-private-key-');
    file_put_contents($localArchive, Storage::disk('keepsakes')->get($archivePath));
    file_put_contents($privateKey, $keys['keypair']);

    try {
        $verification = app(VerifyInstanceKeepsakeArchive::class)->handle(
            $localArchive,
            $privateKey,
            $created['archive_sha256'],
        );
        expect($verification['status'])->toBe('verified_outside_creation_service')
            ->and($verification['safe_to_reset'])->toBeFalse();
    } finally {
        @unlink($localArchive);
        @unlink($privateKey);
    }

    $storedArchive = Storage::disk('keepsakes')->get($archivePath);
    Storage::disk('keepsakes')->put($archivePath, 'tampered archive');
    $this->actingAs($user)
        ->post('/x/cockpit/instance-keepsakes/before-fresh-test/download')
        ->assertNotFound();
    Storage::disk('keepsakes')->put($archivePath, $storedArchive);

    $this->actingAs($user)
        ->get('/x/cockpit/instance-keepsakes/before-fresh-test/download')
        ->assertSuccessful()
        ->assertSee('Download encrypted archive');

    $this->actingAs($user)
        ->get('/x/cockpit/instance-keepsakes/before-fresh-test/download')
        ->assertSuccessful();

    $this->actingAs($user)
        ->post('/x/cockpit/instance-keepsakes/before-fresh-test/download')
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/octet-stream');

    $this->actingAs($user)
        ->post('/x/cockpit/instance-keepsakes/before-fresh-test/download')
        ->assertNotFound();

    fakePayoutProvider()->assertNoDisbursementAttempted();
    expect(fakePayoutProvider()->checkStatusCallCount)->toBe(0);
});

it('requires explicit bulk confirmation and a matching plan hash', function () {
    User::query()->create([
        'name' => 'Keepsake User',
        'email' => 'keepsake@example.test',
        'password' => 'secret',
    ]);

    $this->artisan('x-change:instance-keepsake:export', [
        '--all-users' => true,
        '--include' => ['accounts'],
        '--json' => true,
    ])->expectsOutputToContain('confirmation_required')
        ->assertFailed();

    expect(Artisan::all())->not->toHaveKey('x-change:instance-keepsake:import');
});

it('requires a separate acknowledgement for precise location data', function () {
    $user = User::query()->create([
        'name' => 'Keepsake User',
        'email' => 'keepsake@example.test',
        'password' => 'secret',
    ]);

    $this->artisan('x-change:instance-keepsake:export', [
        '--user' => [$user->getKey()],
        '--include' => ['claim-evidence'],
        '--include-location-data' => true,
        '--confirm-sensitive-export' => true,
        '--json' => true,
    ])->expectsOutputToContain('confirmation_required')
        ->assertFailed();
});

it('requires sensitive confirmation whenever claim evidence is in scope', function () {
    $user = User::query()->create([
        'name' => 'Keepsake User',
        'email' => 'keepsake@example.test',
        'password' => 'secret',
    ]);

    $this->artisan('x-change:instance-keepsake:export', [
        '--user' => [$user->getKey()],
        '--include' => ['claim-evidence'],
        '--json' => true,
    ])->expectsOutputToContain('confirmation_required')
        ->assertFailed();
});
