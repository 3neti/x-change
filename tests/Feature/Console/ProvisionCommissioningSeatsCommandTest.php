<?php

declare(strict_types=1);

use LBHurtado\XProvisioning\Models\ProvisioningSeat;

it('provisions vacant commissioning seats without identities or active authority', function (): void {
    $this->artisan('x-change:provisioning:commission', ['--json' => true])
        ->expectsOutputToContain('x-change.commissioning-provisioning-seats.v1')
        ->assertSuccessful();

    $firstCount = ProvisioningSeat::query()->count();

    $this->artisan('x-change:provisioning:commission')->assertSuccessful();

    expect($firstCount)->toBeGreaterThan(0)
        ->and(ProvisioningSeat::query()->count())->toBe($firstCount)
        ->and(ProvisioningSeat::query()->whereNotNull('activated_subject_reference')->exists())->toBeFalse();
});
