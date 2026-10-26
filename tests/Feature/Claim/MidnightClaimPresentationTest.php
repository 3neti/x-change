<?php

declare(strict_types=1);

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Services\FormFlowPresentationResolver;
use Symfony\Component\Yaml\Yaml;

it('applies midnight to redemption steps without changing their handlers or callbacks', function (): void {
    $driver = Yaml::parseFile(__DIR__.'/../../../config/form-flow-drivers/voucher-redemption.yaml');
    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'midnight-presentation-test',
        'presentation' => $driver['presentation'],
        'callbacks' => ['on_complete' => '/claim/complete'],
        'steps' => [
            ['handler' => 'form', 'config' => ['fields' => [['name' => 'mobile', 'type' => 'text']]]],
            ['handler' => 'otp', 'config' => ['digits' => 6]],
        ],
    ]);

    $prepared = app(FormFlowPresentationResolver::class)->prepare($instructions)->toArray();

    expect($prepared['callbacks'])->toBe($instructions->toArray()['callbacks']);
    foreach ($prepared['steps'] as $index => $step) {
        expect($step['handler'])->toBe(['form', 'otp'][$index])
            ->and($step['config']['presentation']['appearance']['mode'])->toBe('dark')
            ->and($step['config']['presentation']['appearance']['dark']['canvas'])->toBe('#0c1020')
            ->and($step['config']['presentation']['tokens']['spacing'])->toBe('spacious')
            ->and($step['config']['presentation']['tokens']['control_size'])->toBe('large')
            ->and($step['config']['presentation']['actions']['placement'])->toBe('viewport_bottom')
            ->and($step['config']['presentation']['actions']['reserve_content_space'])->toBeTrue();
    }
});

it('preserves explicit screen appearance overrides', function (): void {
    $resolved = app(FormFlowPresentationResolver::class)->resolve(
        ['profile' => 'guided-claim-v1'],
        ['appearance' => ['mode' => 'light']],
        'entry',
    );

    expect($resolved['appearance']['mode'])->toBe('light')
        ->and($resolved['brand']['logo_height'])->toBe(80);
});

it('labels disbursement details without renaming final redemption confirmation', function (): void {
    $driver = Yaml::parseFile(__DIR__.'/../../../config/form-flow-drivers/voucher-redemption.yaml');
    $resolver = app(FormFlowPresentationResolver::class);
    $details = $resolver->resolve($driver['presentation'], $driver['steps']['wallet']['config']['presentation'], 'form');
    $defaults = $resolver->resolve($driver['presentation']);

    expect($details['actions']['roles']['submit']['label'])->toBe('Confirm Details')
        ->and($details['actions']['roles']['continue']['label'])->toBe('Confirm Details')
        ->and(data_get($details, 'actions.roles.confirm_claim.label'))->toBeNull()
        ->and(data_get($defaults, 'actions.roles.submit.label'))->toBeNull();
});

it('keeps midnight body text and actions readable', function (): void {
    $palette = app(FormFlowPresentationResolver::class)->resolve(['profile' => 'guided-claim-v1'])['appearance']['dark'];
    $luminance = static function (string $hex): float {
        $channels = array_map(static function (string $channel): float {
            $value = hexdec($channel) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };

    foreach ([['foreground', 'canvas'], ['muted', 'surface'], ['accent_foreground', 'accent']] as [$text, $surface]) {
        $values = [$luminance($palette[$text]), $luminance($palette[$surface])];
        expect((max($values) + 0.05) / (min($values) + 0.05))->toBeGreaterThanOrEqual(4.5);
    }
});
