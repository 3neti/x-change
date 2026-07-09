<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityPresenterContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityPresentationData;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitOperatorIssuanceActivityPresenter;

it('defines a presentation-only operator issuance activity view model', function () {
    $presentation = new CockpitOperatorIssuanceActivityPresentationData(
        id: 'activity-1',
        code: 'PC-1234',
        title: 'Pay Code PC-1234 issued',
        subtitle: 'PHP 25 issued through Quick Generate',
        status: 'issued',
        detail_href: '/x/cockpit/pay-codes/PC-1234',
        correlation_id: 'corr-1',
    );

    expect($presentation->toArray())->toBe([
        'schema' => 'x-change.cockpit.operator-issuance-activity-presentation.v1',
        'id' => 'activity-1',
        'code' => 'PC-1234',
        'title' => 'Pay Code PC-1234 issued',
        'subtitle' => 'PHP 25 issued through Quick Generate',
        'status' => 'issued',
        'detail_href' => '/x/cockpit/pay-codes/PC-1234',
        'correlation_id' => 'corr-1',
        'handoffs' => [
            'journal' => 'not_wired',
            'action' => 'not_wired',
            'feedback' => 'not_wired',
        ],
        'safety' => [
            'presentation_only' => true,
            'writes_journal' => false,
            'executes_actions' => false,
            'sends_feedback' => false,
            'moves_money' => false,
            'owns_lifecycle_truth' => false,
        ],
        'metadata' => [],
    ]);
});

it('binds the default operator issuance activity presenter', function () {
    expect(app(CockpitOperatorIssuanceActivityPresenterContract::class))
        ->toBeInstanceOf(DefaultCockpitOperatorIssuanceActivityPresenter::class);
});

it('presents operator issuance activity without creating side effects', function () {
    $presenter = app(CockpitOperatorIssuanceActivityPresenterContract::class);

    $presentation = $presenter->present(
        activity: new CockpitOperatorIssuanceActivityItemData(
            id: 'activity-1',
            code: 'PC-1234',
            amount: '25',
            currency: 'PHP',
            status: 'issued',
            issued_at: '2026-07-10T09:00:00+00:00',
            route: 'x-change.cockpit.quick-generate.store',
            correlation_id: 'corr-1',
            idempotency_key: 'idem-1',
            operator_id: 'operator-1',
            detail_href: '/x/cockpit/pay-codes/PC-1234',
        ),
        journal: new CockpitOperatorIssuanceActivityJournalHandoffResultData(activity_id: 'activity-1', correlation_id: 'corr-1'),
        action: new CockpitOperatorIssuanceActivityActionHandoffResultData(activity_id: 'activity-1', correlation_id: 'corr-1'),
        feedback: new CockpitOperatorIssuanceActivityFeedbackHandoffResultData(activity_id: 'activity-1', correlation_id: 'corr-1'),
    );

    expect($presentation)
        ->toBeInstanceOf(CockpitOperatorIssuanceActivityPresentationData::class)
        ->and($presentation->title)->toBe('Pay Code PC-1234 issued')
        ->and($presentation->subtitle)->toBe('PHP 25 issued through Quick Generate')
        ->and($presentation->handoffs)->toBe([
            'journal' => 'not_wired',
            'action' => 'not_wired',
            'feedback' => 'not_wired',
        ])
        ->and($presentation->safety['presentation_only'])->toBeTrue()
        ->and($presentation->safety['writes_journal'])->toBeFalse()
        ->and($presentation->safety['executes_actions'])->toBeFalse()
        ->and($presentation->safety['sends_feedback'])->toBeFalse()
        ->and($presentation->safety['moves_money'])->toBeFalse();
});
