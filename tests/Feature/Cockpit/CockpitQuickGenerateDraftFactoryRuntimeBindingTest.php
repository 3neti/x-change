<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitQuickGenerateDraftFactoryContract;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitQuickGenerateDraftFactory;

it('binds the cockpit quick generate draft factory contract to the default factory', function () {
    $factory = app(CockpitQuickGenerateDraftFactoryContract::class);

    expect($factory)->toBeInstanceOf(DefaultCockpitQuickGenerateDraftFactory::class);
});

it('resolves quick generate drafts through the runtime seam without invoking issuance', function () {
    $draft = app(CockpitQuickGenerateDraftFactoryContract::class)->fromPayload([
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'count' => 1,
        'feedback' => [
            'mobile' => '09173011987',
        ],
        'rider' => [
            'message' => 'Runtime binding',
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'template_key' => 'money-changer',
                ],
            ],
        ],
    ]);

    expect($draft->template_key)->toBe('money-changer')
        ->and($draft->amount)->toBe(25)
        ->and($draft->recipient_reference)->toBe('09173011987');
});
