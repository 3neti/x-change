<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Actions\Keepsake\PlanInstanceKeepsakeExport;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeArchiveWriter;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeCrypto;
use LBHurtado\XChange\Tests\Fakes\User;

function continuityArchiveFixture(bool $includeContinuity = true, bool $staleProviderCheckpoint = false): array
{
    $keys = app(InstanceKeepsakeCrypto::class)->generateKeyPair();
    config()->set('x-change.instance.id', 'x-change-test-source');
    $user = User::query()->create([
        'name' => 'Continuity User',
        'email' => 'continuity@example.test',
        'password' => 'secret',
    ]);
    $template = PayCodeTemplate::query()->create([
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
        'name' => 'Continuity template',
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => ['cash' => ['amount' => 100, 'currency' => 'PHP']],
        'include_amount' => true,
        'include_purpose' => true,
        'status' => 'active',
    ]);
    LeadCampaign::query()->create([
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
        'pay_code_template_id' => $template->getKey(),
        'merchant_display_name' => 'Continuity Merchant',
        'merchant_slug' => 'continuity-merchant',
        'endpoint_slug' => 'continuity-offer',
        'title' => 'Continuity Offer',
        'status' => 'active',
        'settings' => ['private_note' => 'must not enter the keepsake'],
    ]);
    ProviderBalanceSnapshot::query()->create([
        'provider_code' => 'netbank',
        'balance_key' => 'netbank_source_account',
        'scope_key' => 'global',
        'balance_minor' => 500_000,
        'available_balance_minor' => 450_000,
        'currency' => 'PHP',
        'account_reference_masked' => '********0001',
        'fetched_at' => $staleProviderCheckpoint ? now()->subHour() : now(),
        'refresh_status' => 'fresh',
    ]);
    $plan = app(PlanInstanceKeepsakeExport::class)->handle(
        allUsers: true,
        userIdentifiers: [],
        includes: $includeContinuity
            ? ['accounts', 'pay-codes', 'claim-evidence', 'campaigns', 'continuity', 'blueprint']
            : ['accounts', 'pay-codes', 'claim-evidence', 'blueprint'],
        includePersonalData: true,
        includeLocationData: false,
        allowIncomplete: false,
        materializeArtifacts: true,
    );
    $archive = app(InstanceKeepsakeArchiveWriter::class)->write($plan, $keys['public_key']);
    $privateKey = tempnam(sys_get_temp_dir(), 'continuity-private-key-');
    file_put_contents($privateKey, $keys['keypair']);

    return [$archive, $privateKey];
}

it('inspects a keepsake without changing application state', function () {
    Storage::fake('keepsakes');
    [$archive, $privateKey] = continuityArchiveFixture();
    $userCount = User::query()->count();

    try {
        $exitCode = Artisan::call('x-change:instance-keepsake:inspect', [
            'archive' => $archive['encrypted_path'],
            '--private-key-file' => $privateKey,
            '--expected-archive-sha256' => $archive['archive_sha256'],
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(0)
            ->and($payload['status'])->toBe('verified')
            ->and($payload['inventory']['accounts'])->toBe(1)
            ->and($payload['inventory']['endpoint_campaigns'])->toBe(1)
            ->and($payload['inventory']['provider_checkpoints'])->toBe(1)
            ->and($payload['source_instance']['id'])->toBe('x-change-test-source')
            ->and($payload['provider_checkpoint']['all_fresh'])->toBeTrue()
            ->and($payload['read_only'])->toBeTrue()
            ->and($payload['writes_database'])->toBeFalse()
            ->and($payload['moves_money'])->toBeFalse()
            ->and($payload['safe_to_reset'])->toBeFalse()
            ->and(User::query()->count())->toBe($userCount);
        fakePayoutProvider()->assertNoDisbursementAttempted();
        expect(fakePayoutProvider()->checkStatusCallCount)->toBe(0);
    } finally {
        app(InstanceKeepsakeArchiveWriter::class)->cleanup($archive['encrypted_path']);
        @unlink($privateKey);
    }
});

it('builds a deterministic read-only continuity plan', function () {
    [$archive, $privateKey] = continuityArchiveFixture();

    try {
        $arguments = [
            'archive' => $archive['encrypted_path'],
            '--private-key-file' => $privateKey,
            '--expected-archive-sha256' => $archive['archive_sha256'],
            '--destination' => 'x-payout-local-cleanroom',
            '--json' => true,
        ];
        expect(Artisan::call('x-change:continuity:plan', $arguments))->toBe(0);
        $first = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        expect(Artisan::call('x-change:continuity:plan', $arguments))->toBe(0);
        $second = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($first['continuity_plan_hash'])->toBe($second['continuity_plan_hash'])
            ->and($first['status'])->toBe('review_required')
            ->and($first['apply_supported'])->toBeFalse()
            ->and($first['requires_maker_checker'])->toBeTrue()
            ->and($first['writes_database'])->toBeFalse()
            ->and($first['restores_live_pay_codes'])->toBeFalse()
            ->and($first['blockers'])->toContain('financial_apply_not_supported')
            ->and($first['blockers'])->not->toContain('campaigns_not_included')
            ->and($first['blockers'])->not->toContain('provider_checkpoint_not_embedded')
            ->and($first['blockers'])->not->toContain('source_instance_identity_not_embedded')
            ->and(Artisan::all())->not->toHaveKey('x-change:continuity:apply');
    } finally {
        app(InstanceKeepsakeArchiveWriter::class)->cleanup($archive['encrypted_path']);
        @unlink($privateKey);
    }
});

it('keeps legacy images inspectable while identifying missing continuity coverage', function () {
    [$archive, $privateKey] = continuityArchiveFixture(false);

    try {
        $exitCode = Artisan::call('x-change:continuity:plan', [
            'archive' => $archive['encrypted_path'],
            '--private-key-file' => $privateKey,
            '--expected-archive-sha256' => $archive['archive_sha256'],
            '--destination' => 'x-payout-local-cleanroom',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(0)
            ->and($payload['blockers'])->toContain('campaigns_not_included')
            ->and($payload['blockers'])->toContain('provider_checkpoint_not_embedded')
            ->and($payload['blockers'])->toContain('source_instance_identity_not_embedded');
    } finally {
        app(InstanceKeepsakeArchiveWriter::class)->cleanup($archive['encrypted_path']);
        @unlink($privateKey);
    }
});

it('blocks continuity when the embedded provider checkpoint is stale', function () {
    config()->set('x-change.funding.provider_balance_max_age_seconds', 300);
    [$archive, $privateKey] = continuityArchiveFixture(staleProviderCheckpoint: true);

    try {
        $exitCode = Artisan::call('x-change:continuity:plan', [
            'archive' => $archive['encrypted_path'],
            '--private-key-file' => $privateKey,
            '--expected-archive-sha256' => $archive['archive_sha256'],
            '--destination' => 'x-payout-local-cleanroom',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(0)
            ->and($payload['provider_checkpoint']['all_fresh'])->toBeFalse()
            ->and($payload['blockers'])->toContain('provider_checkpoint_not_fresh');
    } finally {
        app(InstanceKeepsakeArchiveWriter::class)->cleanup($archive['encrypted_path']);
        @unlink($privateKey);
    }
});

it('rejects an invalid destination and a mismatched archive hash', function () {
    [$archive, $privateKey] = continuityArchiveFixture();

    try {
        $this->artisan('x-change:continuity:plan', [
            'archive' => $archive['encrypted_path'],
            '--private-key-file' => $privateKey,
            '--expected-archive-sha256' => $archive['archive_sha256'],
            '--destination' => '../unsafe',
            '--json' => true,
        ])->expectsOutputToContain('destination instance identifier')
            ->assertFailed();

        $this->artisan('x-change:instance-keepsake:inspect', [
            'archive' => $archive['encrypted_path'],
            '--private-key-file' => $privateKey,
            '--expected-archive-sha256' => str_repeat('0', 64),
            '--json' => true,
        ])->expectsOutputToContain('checksum')
            ->assertFailed();
    } finally {
        app(InstanceKeepsakeArchiveWriter::class)->cleanup($archive['encrypted_path']);
        @unlink($privateKey);
    }
});
