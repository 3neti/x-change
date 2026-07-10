<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRecorder;

it('keeps the default activity recorder binding null until explicitly wired', function () {
    expect(app(CockpitOperatorIssuanceActivityRecorderContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityRecorder::class);
});

it('records quick generate activity through the repository when explicitly opted in', function () {
    app()->instance(
        CockpitOperatorIssuanceActivityRepositoryContract::class,
        app(DatabaseCockpitOperatorIssuanceActivityRepository::class),
    );

    $recorder = app(DatabaseCockpitOperatorIssuanceActivityRecorder::class);

    $recorder->record(new CockpitOperatorIssuanceActivityItemData(
        id: 'activity-1',
        code: 'PC-1001',
        amount: '100.00',
        currency: 'PHP',
        status: 'issued',
        issued_at: '2026-07-10T09:00:00+08:00',
        route: 'x-change.cockpit.quick-generate.store',
        correlation_id: 'corr-1',
        idempotency_key: 'raw-idempotency-key',
        operator_id: 'operator-1',
        detail_href: '/x/cockpit/pay-codes/PC-1001',
        metadata: [
            'source' => 'x-change.cockpit',
            'presentation_only' => true,
            'raw_payload' => ['secret' => 'value'],
        ],
    ));

    $activity = CockpitOperatorIssuanceActivity::query()->first();

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(1)
        ->and($activity?->activity_id)->toBe('activity-1')
        ->and($activity?->subject_reference)->toBe('PC-1001')
        ->and($activity?->actor_id)->toBe('operator-1')
        ->and($activity?->idempotency_key_hash)->toBe(hash('sha256', 'raw-idempotency-key'))
        ->and($activity?->safe_context)->toBe([
            'code' => 'PC-1001',
            'amount' => '100.00',
            'currency' => 'PHP',
            'route' => 'x-change.cockpit.quick-generate.store',
            'detail_href' => '/x/cockpit/pay-codes/PC-1001',
        ])
        ->and($activity?->metadata)->toBe([
            'source' => 'x-change.cockpit',
            'presentation_only' => true,
            'raw_payload' => '[redacted]',
        ]);
});
