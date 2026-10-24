<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Configuration\ApplicationProfileData;
use LBHurtado\XChange\Enums\ApplicationApprovalMode;
use LBHurtado\XChange\Enums\ApplicationInstructionContract;
use LBHurtado\XChange\Enums\ApplicationJourneyKind;
use LBHurtado\XChange\Services\Configuration\ApplicationProfileCompiler;
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
        foreach ($profile->journeys as $journey) {
            expect($journey->approvalMode)->toBe(ApplicationApprovalMode::Direct);
        }
        $keys[] = $profile->key;
    }

    sort($keys);
    expect($keys)->toBe([
        'bank', 'default', 'digital-marketing', 'lead-generation', 'lending-center',
        'local-remittance', 'money-changer', 'offshore-remittance', 'opt-in',
        'payout', 'transport-pos', 'vendor-pos',
    ]);
});

it('accepts maker checker as an explicit approval mode for any journey', function (): void {
    $directory = dirname(__DIR__, 4).'/config/app-profiles';

    foreach (glob($directory.'/*.yaml') ?: [] as $path) {
        $attributes = Yaml::parseFile($path);
        foreach ($attributes['journeys'] as &$journey) {
            $journey['approval'] = 'maker_checker';
        }
        unset($journey);

        $profile = ApplicationProfileData::fromArray($attributes);
        foreach ($profile->journeys as $journey) {
            expect($journey->approvalMode)->toBe(ApplicationApprovalMode::MakerChecker);
        }
    }
});

it('compiles every packaged profile to usable routes and composes additional journeys', function (): void {
    $compiler = app(ApplicationProfileCompiler::class);
    $directory = dirname(__DIR__, 4).'/config/app-profiles';

    foreach (glob($directory.'/*.yaml') ?: [] as $path) {
        $key = pathinfo($path, PATHINFO_FILENAME);
        $compiled = $compiler->compile($key);

        expect($compiled->primaryKey)->toBe($key)
            ->and($compiled->navigation)->not->toBeEmpty()
            ->and($compiled->homeRouteName)->toBeString();
    }

    $composed = $compiler->compile('payout', ['transport-pos', 'bank']);
    expect($composed->profileKeys)->toBe(['payout', 'transport-pos', 'bank'])
        ->and($composed->homeRouteName)->toBe('x-change.cockpit.campaigns.index')
        ->and(array_column($composed->navigation, 'key'))->toContain('salary_payout', 'fare_collection', 'issue_pay_code')
        ->and(array_count_values(array_column($composed->navigation, 'key'))['pay_code_activity'])->toBe(1);
});

it('rejects unknown app profile keys during compilation', function (): void {
    expect(fn () => app(ApplicationProfileCompiler::class)->compile('missing-profile'))
        ->toThrow(InvalidArgumentException::class);
});

it('reports the selected app profile composition in the readiness command', function (): void {
    config()->set('x-change.app_profile.primary', 'payout');
    config()->set('x-change.app_profile.addons', ['transport-pos']);

    $this->artisan('x-change:app-profiles:check')->assertSuccessful();
});

it('keeps local and offshore remittance journeys distinct when profiles coexist', function (): void {
    $compiled = app(ApplicationProfileCompiler::class)->compile('local-remittance', ['offshore-remittance']);
    $keys = array_column($compiled->navigation, 'key');

    expect($keys)->toContain('send_local_remittance', 'send_offshore_remittance')
        ->and($keys)->toHaveCount(count(array_unique($keys)));
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
    'unknown approval mode' => [['journeys' => [
        ['key' => 'overview', 'label' => 'Overview', 'kind' => 'inspect', 'instruction_contract' => 'none', 'approval' => 'unreviewed'],
    ]]],
]);
