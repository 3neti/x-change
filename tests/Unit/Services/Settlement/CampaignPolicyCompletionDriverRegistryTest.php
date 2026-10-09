<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CampaignPolicyCompletionDriverContract;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use LBHurtado\XChange\Exceptions\CampaignPolicyCompletionDriverUnavailable;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;
use LBHurtado\XChange\Services\Settlement\CampaignPolicyCompletionDriverRegistry;

function completionDriver(string $id, string $version): CampaignPolicyCompletionDriverContract
{
    return new class($id, $version) implements CampaignPolicyCompletionDriverContract
    {
        public function __construct(
            private readonly string $id,
            private readonly string $version,
        ) {}

        public function driverId(): string
        {
            return $this->id;
        }

        public function driverVersion(): string
        {
            return $this->version;
        }

        public function prepare(
            CompletionClaimEvidenceProjection $projection,
        ): PolicyCompletionPreparationData {
            throw new LogicException('Preparation is outside this registry characterization.');
        }
    };
}

it('resolves AUI and Medicard completion drivers by exact identity', function (): void {
    $aui = completionDriver('aui.personal-accident.provisional-cover', '1.0.0');
    $medicard = completionDriver('medicard.demo-benefit', '1.0.0');
    $registry = new CampaignPolicyCompletionDriverRegistry([$aui, $medicard]);

    expect($registry->for($aui->driverId(), $aui->driverVersion()))->toBe($aui)
        ->and($registry->for($medicard->driverId(), $medicard->driverVersion()))->toBe($medicard);
});

it('fails closed for an unavailable product version', function (): void {
    $registry = new CampaignPolicyCompletionDriverRegistry([
        completionDriver('aui.personal-accident.provisional-cover', '1.0.0'),
        completionDriver('medicard.demo-benefit', '1.0.0'),
    ]);

    expect(fn () => $registry->for('medicard.demo-benefit', '2.0.0'))
        ->toThrow(CampaignPolicyCompletionDriverUnavailable::class);
});

it('rejects duplicate identities across built-in and configured drivers', function (): void {
    expect(fn () => new CampaignPolicyCompletionDriverRegistry([
        completionDriver('medicard.demo-benefit', '1.0.0'),
        completionDriver('medicard.demo-benefit', '1.0.0'),
    ]))->toThrow(LogicException::class, 'Multiple campaign policy completion drivers');
});

it('rejects blank driver identities', function (string $id, string $version): void {
    expect(fn () => new CampaignPolicyCompletionDriverRegistry([
        completionDriver($id, $version),
    ]))->toThrow(LogicException::class, 'must declare an ID and version');
})->with([
    'blank driver ID' => ['', '1.0.0'],
    'blank driver version' => ['medicard.demo-benefit', ''],
]);
