<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitDistributionWorkspaceItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitDistributionWorkspaceReadModelData;

it('carries the distribution workspace read model contract without executable behavior', function () {
    $readModel = new CockpitDistributionWorkspaceReadModelData(
        status: 'available',
        authorized: true,
        code: 'PC-DIST-001',
        summary: [
            'display_status' => 'ready',
            'amount' => 25,
            'currency' => 'PHP',
        ],
        share_assets: [
            new CockpitDistributionWorkspaceItemData(
                key: 'copy-text',
                label: 'Copy text',
                status: 'preview',
                description: 'Operator-safe Pay Code copy text is available.',
                available: true,
                source: 'voucher-summary',
                metadata: ['copies_secret_claim_material' => false],
            ),
        ],
        channels: [
            new CockpitDistributionWorkspaceItemData(
                key: 'sms',
                label: 'SMS',
                status: 'not_wired',
                description: 'SMS dispatch remains owned by x-feedback.',
                source: 'feedback-read-model',
            ),
        ],
        print_templates: [
            new CockpitDistributionWorkspaceItemData(
                key: 'receipt-card',
                label: 'Receipt card',
                status: 'planned',
                description: 'Print artifact generation remains disabled.',
                source: 'distribution-policy',
            ),
        ],
        analytics: [
            new CockpitDistributionWorkspaceItemData(
                key: 'delivery-state',
                label: 'Delivery state',
                status: 'not_wired',
                description: 'Delivery truth must come from x-feedback.',
                source: 'feedback-read-model',
            ),
        ],
        actions: [
            new CockpitDistributionWorkspaceItemData(
                key: 'send-now',
                label: 'Send now',
                status: 'blocked',
                description: 'Distribution dispatch is not authorized from Cockpit.',
                source: 'mutation-boundary',
            ),
        ],
        redactions: [
            'payloads' => 'distribution-read-model-summary-only',
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
        ],
    );

    expect($readModel->schema)->toBe('x-change.cockpit.distribution-workspace.v1')
        ->and($readModel->status)->toBe('available')
        ->and($readModel->authorized)->toBeTrue()
        ->and($readModel->share_assets)->toHaveCount(1)
        ->and($readModel->actions[0]->read_only)->toBeTrue()
        ->and($readModel->actions[0]->available)->toBeFalse()
        ->and($readModel->toArray())->toBe([
            'schema' => 'x-change.cockpit.distribution-workspace.v1',
            'status' => 'available',
            'authorized' => true,
            'code' => 'PC-DIST-001',
            'summary' => [
                'display_status' => 'ready',
                'amount' => 25,
                'currency' => 'PHP',
            ],
            'share_assets' => [
                [
                    'key' => 'copy-text',
                    'label' => 'Copy text',
                    'status' => 'preview',
                    'description' => 'Operator-safe Pay Code copy text is available.',
                    'read_only' => true,
                    'available' => true,
                    'source' => 'voucher-summary',
                    'href' => null,
                    'metadata' => ['copies_secret_claim_material' => false],
                ],
            ],
            'channels' => [
                [
                    'key' => 'sms',
                    'label' => 'SMS',
                    'status' => 'not_wired',
                    'description' => 'SMS dispatch remains owned by x-feedback.',
                    'read_only' => true,
                    'available' => false,
                    'source' => 'feedback-read-model',
                    'href' => null,
                    'metadata' => [],
                ],
            ],
            'print_templates' => [
                [
                    'key' => 'receipt-card',
                    'label' => 'Receipt card',
                    'status' => 'planned',
                    'description' => 'Print artifact generation remains disabled.',
                    'read_only' => true,
                    'available' => false,
                    'source' => 'distribution-policy',
                    'href' => null,
                    'metadata' => [],
                ],
            ],
            'analytics' => [
                [
                    'key' => 'delivery-state',
                    'label' => 'Delivery state',
                    'status' => 'not_wired',
                    'description' => 'Delivery truth must come from x-feedback.',
                    'read_only' => true,
                    'available' => false,
                    'source' => 'feedback-read-model',
                    'href' => null,
                    'metadata' => [],
                ],
            ],
            'actions' => [
                [
                    'key' => 'send-now',
                    'label' => 'Send now',
                    'status' => 'blocked',
                    'description' => 'Distribution dispatch is not authorized from Cockpit.',
                    'read_only' => true,
                    'available' => false,
                    'source' => 'mutation-boundary',
                    'href' => null,
                    'metadata' => [],
                ],
            ],
            'distribution_links' => [],
            'redactions' => [
                'payloads' => 'distribution-read-model-summary-only',
                'raw_payloads_exposed' => false,
                'provider_payloads_exposed' => false,
            ],
        ]);
});
