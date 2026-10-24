<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Configuration\ApplicationProfileData;
use LBHurtado\XChange\Enums\ApplicationInstructionContract;
use LBHurtado\XChange\Enums\ApplicationJourneyKind;
use Symfony\Component\Yaml\Yaml;

it('accepts every packaged application profile as a typed static contract', function (): void {
    $directory = dirname(__DIR__, 4).'/config/app-profiles';
    $paths = glob($directory.'/*.yaml') ?: [];
    $keys = [];

    foreach ($paths as $path) {
        $attributes = Yaml::parseFile($path);
        expect($attributes)->toBeArray();

        $profile = ApplicationProfileData::fromArray($attributes);
        expect($profile->key)->toBe(pathinfo($path, PATHINFO_FILENAME));
        $keys[] = $profile->key;
    }

    sort($keys);
    expect($keys)->toBe([
        'bank', 'default', 'digital-marketing', 'lead-generation', 'lending-center',
        'local-remittance', 'money-changer', 'offshore-remittance', 'opt-in',
        'payout', 'transport-pos', 'vendor-pos',
    ]);
});

it('declares payout and transport collection as distinct instruction contracts', function (): void {
    $directory = dirname(__DIR__, 4).'/config/app-profiles';
    $payout = ApplicationProfileData::fromArray(Yaml::parseFile($directory.'/payout.yaml'));
    $transport = ApplicationProfileData::fromArray(Yaml::parseFile($directory.'/transport-pos.yaml'));

    expect($payout->home)->toBe('salary_payout')
        ->and(array_map(static fn ($journey) => $journey->key, $payout->journeys))
        ->toContain('gaming_payout', 'ayuda_payout')
        ->and($payout->journeys[0]->kind)->toBe(ApplicationJourneyKind::IssueBatch)
        ->and($payout->journeys[0]->instructionContract)->toBe(ApplicationInstructionContract::Voucher)
        ->and($transport->home)->toBe('fare_collection')
        ->and($transport->journeys[0]->kind)->toBe(ApplicationJourneyKind::Collect)
        ->and($transport->journeys[0]->instructionContract)->toBe(ApplicationInstructionContract::StoredValueCollection);
});

it('rejects invalid profile references and duplicate journey keys', function (array $changes): void {
    $attributes = [
        'schema' => ApplicationProfileData::SCHEMA,
        'key' => 'example',
        'label' => 'Example',
        'home' => 'overview',
        'navigation' => ['overview'],
        'journeys' => [
            ['key' => 'overview', 'label' => 'Overview', 'kind' => 'inspect', 'instruction_contract' => 'none'],
        ],
    ];

    expect(fn () => ApplicationProfileData::fromArray(array_replace($attributes, $changes)))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'unsupported schema' => [['schema' => 'x-change.app-profile.v2']],
    'missing home journey' => [['home' => 'missing']],
    'unknown navigation journey' => [['navigation' => ['missing']]],
    'duplicate navigation' => [['navigation' => ['overview', 'overview']]],
    'duplicate journey key' => [['journeys' => [
        ['key' => 'overview', 'label' => 'Overview', 'kind' => 'inspect', 'instruction_contract' => 'none'],
        ['key' => 'overview', 'label' => 'Other', 'kind' => 'inspect', 'instruction_contract' => 'none'],
    ]]],
    'unknown journey kind' => [['journeys' => [
        ['key' => 'overview', 'label' => 'Overview', 'kind' => 'unknown', 'instruction_contract' => 'none'],
    ]]],
    'mismatched instruction contract' => [['journeys' => [
        ['key' => 'overview', 'label' => 'Overview', 'kind' => 'collect', 'instruction_contract' => 'voucher'],
    ]]],
]);
