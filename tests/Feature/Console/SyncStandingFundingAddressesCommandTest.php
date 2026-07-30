<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Jobs\Funding\SyncStandingFundingAddressJob;
use LBHurtado\XChange\Models\StandingFundingAddress;

it('queues only active provider addresses within the configured batch', function () {
    Bus::fake();
    config([
        'x-change.funding.standing_addresses.enabled' => true,
        'x-change.funding.standing_addresses.scheduled_batch_size' => 1,
        'x-change.funding.providers.netbank.enabled' => true,
    ]);
    $active = standingAddressForCommand();
    standingAddressForCommand([
        'provider_code' => 'netbank',
        'status' => FundingAddressStatus::Suspended,
        'funding_address_hash' => hash('sha256', 'suspended-address'),
        'binding_key' => hash('sha256', 'suspended-binding'),
    ]);
    standingAddressForCommand([
        'provider_code' => 'paynamics_constellation',
        'funding_address_hash' => hash('sha256', 'other-provider-address'),
        'binding_key' => hash('sha256', 'other-provider-binding'),
    ]);

    $this->artisan('xchange:funding:sync-standing', [
        '--provider' => 'netbank',
        '--limit' => 10,
    ])->assertSuccessful()
        ->expectsOutputToContain('Queued 1 Standing Funding Address synchronization check(s).');

    Bus::assertDispatchedTimes(SyncStandingFundingAddressJob::class, 1);
    Bus::assertDispatched(
        SyncStandingFundingAddressJob::class,
        fn (SyncStandingFundingAddressJob $job): bool => $job->standingFundingAddressId === $active->getKey()
            && $job->providerCode === 'netbank'
            && $job->trigger === 'schedule',
    );
});

it('registers the package-owned standing address schedule', function () {
    config([
        'x-change.funding.standing_addresses.enabled' => true,
        'x-change.funding.standing_addresses.scheduled_sync_enabled' => true,
    ]);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'xchange:funding:sync-standing:netbank');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->expiresAt)->toBe(5)
        ->and($event->command)->toContain(
            'xchange:funding:sync-standing --provider=netbank --limit=100',
        );
});

function standingAddressForCommand(array $overrides = []): StandingFundingAddress
{
    return StandingFundingAddress::query()->create(array_replace([
        'binding_key' => hash('sha256', 'command-binding'),
        'account_reference' => 'wallet:command-account',
        'provider_code' => 'netbank',
        'purpose' => FundingAddressPurpose::AccountFunding,
        'recognition_mode' => FundingRecognitionMode::ObserveOnly,
        'status' => FundingAddressStatus::Active,
        'version' => 1,
        'provider_reference' => 'standing:netbank:command',
        'funding_address_ciphertext' => '915001234567890123456',
        'funding_address_hash' => hash('sha256', '915001234567890123456'),
        'currency' => 'PHP',
        'activated_at' => now(),
    ], $overrides));
}
