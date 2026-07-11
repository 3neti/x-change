<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitQuickGeneratePostIssuanceNavigationData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGeneratePostIssuanceNavigationItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateReadModelData;

it('defines an operator safe post issuance navigation contract for quick generate', function () {
    $navigation = new CockpitQuickGeneratePostIssuanceNavigationData(
        status: 'available',
        items: [
            new CockpitQuickGeneratePostIssuanceNavigationItemData(
                key: 'detail',
                label: 'Open Cockpit detail',
                href: '/x/cockpit/pay-codes/PC-34B',
                status: 'available',
                enabled: true,
                reason: 'Read-only voucher detail route for the generated Pay Code.',
            ),
            new CockpitQuickGeneratePostIssuanceNavigationItemData(
                key: 'distribution',
                label: 'Open Distribution workspace',
                href: '/x/cockpit/pay-codes/PC-34B/distribution',
                status: 'available',
                enabled: true,
                reason: 'Read-only distribution/share workspace route for the generated Pay Code.',
            ),
        ],
        redactions: [
            'payloads' => 'post-issuance-navigation-only',
            'excluded' => ['raw_payload', 'provider_payload', 'wallet', 'idempotency_key'],
        ],
    );

    expect($navigation->toArray())->toBe([
        'schema' => 'x-change.cockpit.quick-generate-post-issuance-navigation.v1',
        'status' => 'available',
        'auto_redirect' => false,
        'items' => [
            [
                'key' => 'detail',
                'label' => 'Open Cockpit detail',
                'href' => '/x/cockpit/pay-codes/PC-34B',
                'status' => 'available',
                'enabled' => true,
                'read_only' => true,
                'reason' => 'Read-only voucher detail route for the generated Pay Code.',
            ],
            [
                'key' => 'distribution',
                'label' => 'Open Distribution workspace',
                'href' => '/x/cockpit/pay-codes/PC-34B/distribution',
                'status' => 'available',
                'enabled' => true,
                'read_only' => true,
                'reason' => 'Read-only distribution/share workspace route for the generated Pay Code.',
            ],
        ],
        'redactions' => [
            'payloads' => 'post-issuance-navigation-only',
            'excluded' => ['raw_payload', 'provider_payload', 'wallet', 'idempotency_key'],
        ],
    ])
        ->and($navigation->toArray())->not->toHaveKeys([
            'request_payload',
            'provider_payload',
            'wallet',
            'idempotency_key',
        ]);
});

it('attaches the post issuance navigation contract to the quick generate read model safely by default', function () {
    $readModel = new CockpitQuickGenerateReadModelData(status: 'available', authorized: true);

    expect($readModel->toArray())->toMatchArray([
        'post_issuance_navigation' => [
            'schema' => 'x-change.cockpit.quick-generate-post-issuance-navigation.v1',
            'status' => 'not_wired',
            'auto_redirect' => false,
            'items' => [],
            'redactions' => ['payloads' => 'not-loaded'],
        ],
    ]);
});
