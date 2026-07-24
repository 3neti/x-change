<?php

declare(strict_types=1);

use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleIntegrationReportBuilder;

it('enriches lifecycle payloads with read-only integration reports', function () {
    config()->set('x-change.cockpit.integrations.journal.reader', 'fake.lifecycle.journal');
    config()->set('x-change.cockpit.integrations.action.composer', 'fake.lifecycle.actions');
    config()->set('x-change.cockpit.integrations.feedback.console', 'fake.lifecycle.feedback');
    config()->set('x-change.cockpit.integrations.campaign.cockpit', 'fake.lifecycle.campaigns');

    app()->instance('fake.lifecycle.journal', new class
    {
        public function read(mixed $query): array
        {
            return [
                'entries' => [
                    ['event' => 'voucher.generated', 'payload' => ['secret' => 'hidden']],
                ],
                'metadata' => ['pagination' => ['visible_total' => 1]],
            ];
        }
    });

    app()->instance('fake.lifecycle.actions', new class
    {
        public function compose(...$arguments): array
        {
            return [
                'actions' => [
                    ['key' => 'voucher.inspect', 'run' => ['secret' => 'hidden']],
                ],
                'meta' => [
                    'safe_diagnostics' => [
                        ['action_key' => 'voucher.inspect', 'status' => 'available'],
                    ],
                ],
            ];
        }
    });

    app()->instance('fake.lifecycle.feedback', new class
    {
        public function history(array $filters): array
        {
            return [
                'records' => [
                    ['channel' => 'sms', 'provider_response' => 'hidden'],
                ],
            ];
        }
    });

    app()->instance('fake.lifecycle.campaigns', new class
    {
        public function summary(...$arguments): array
        {
            return [
                'planning_key' => 'TEST-LIFE',
                'execution_id' => 'lifecycle-123',
                'operator_id' => '42',
                'cards' => ['total_recipients' => 1],
                'effects' => ['read_only' => true],
            ];
        }
    });

    $payload = app(LifecycleIntegrationReportBuilder::class)->enrich([
        'scenario' => 'basic_cash',
        'issuer' => ['id' => 42],
        'generated' => ['code' => 'TEST-LIFE'],
        'wallet_transactions' => [
            ['idempotency_key' => 'lifecycle-123'],
        ],
    ]);

    expect($payload['integrations']['context'])->toMatchArray([
        'code' => 'TEST-LIFE',
        'operator_id' => '42',
        'correlation_id' => 'lifecycle-123',
    ])
        ->and($payload['integrations']['summary'])->toMatchArray([
            'available' => 4,
            'unavailable' => 0,
            'total' => 4,
            'read_only' => true,
            'mutates_state' => false,
        ])
        ->and($payload['integrations']['journal']['status'])->toBe('available')
        ->and($payload['integrations']['journal']['redactions']['writes_journal_entries'])->toBeFalse()
        ->and($payload['integrations']['actions']['status'])->toBe('available')
        ->and($payload['integrations']['actions']['redactions']['executes_action'])->toBeFalse()
        ->and($payload['integrations']['feedback']['status'])->toBe('available')
        ->and($payload['integrations']['feedback']['redactions']['sends_feedback'])->toBeFalse()
        ->and($payload['integrations']['campaigns']['status'])->toBe('available')
        ->and($payload['integrations']['campaigns']['mutation']['enabled'])->toBeFalse();
});

it('reports an available campaign package without selecting a campaign', function () {
    $payload = app(LifecycleIntegrationReportBuilder::class)->enrich([
        'scenario' => 'unknown_scenario_key',
        'success' => false,
        'message' => 'Unknown lifecycle scenario',
    ]);

    expect($payload['success'])->toBeFalse()
        ->and($payload['integrations']['summary']['read_only'])->toBeTrue()
        ->and($payload['integrations']['summary']['mutates_state'])->toBeFalse()
        ->and($payload['integrations']['campaigns']['status'])->toBe('available')
        ->and($payload['integrations']['campaigns']['authorized'])->toBeTrue()
        ->and($payload['integrations']['campaigns']['facts']['context_status'])->toBe('no-campaign-selected')
        ->and($payload['integrations']['campaigns']['facts']['selected'])->toBeFalse()
        ->and($payload['integrations']['campaigns']['facts']['actions'])->toBe([])
        ->and($payload['integrations']['campaigns']['mutation']['enabled'])->toBeFalse()
        ->and($payload['integrations']['campaigns']['redactions']['reason'])->toBe('no-campaign-selected');
});
it('honors an explicit integration-report opt out and removes the control marker', function () {
    $payload = app(LifecycleIntegrationReportBuilder::class)->enrich([
        'scenario' => 'treasury_live_basic_cash',
        '_include_integrations' => false,
        'accounting' => ['status' => 'reconciled'],
    ]);

    expect($payload)->toBe([
        'scenario' => 'treasury_live_basic_cash',
        'accounting' => ['status' => 'reconciled'],
    ]);
});
