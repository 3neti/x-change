<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityActionHandoff;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityActionHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityJournalHandoff;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\XActionCockpitOperatorIssuanceActivityActionHandoff;
use LBHurtado\XChange\Services\Cockpit\XJournalCockpitOperatorIssuanceActivityJournalHandoff;

it('keeps null cockpit operator issuance activity runtime services by default', function () {
    expect(app(CockpitOperatorIssuanceActivityRepositoryContract::class))->toBeInstanceOf(NullCockpitOperatorIssuanceActivityRepository::class)
        ->and(app(CockpitOperatorIssuanceActivityRecorderContract::class))->toBeInstanceOf(NullCockpitOperatorIssuanceActivityRecorder::class)
        ->and(app(CockpitOperatorIssuanceActivityActionHandoffContract::class))->toBeInstanceOf(NullCockpitOperatorIssuanceActivityActionHandoff::class)
        ->and(app(CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract::class))->toBeInstanceOf(NullCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class)
        ->and(app(CockpitOperatorIssuanceActivityJournalHandoffContract::class))->toBeInstanceOf(NullCockpitOperatorIssuanceActivityJournalHandoff::class)
        ->and(app(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class))->toBeInstanceOf(NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class);
});

it('resolves named local journal runtime profile keys from config', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.action_handoff', 'x-action');
    config()->set('x-change.cockpit.operator_issuance_activity.action_handoff_status_projector', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff', 'x-journal');
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff_status_projector', 'database');

    forgetCockpitOperatorIssuanceActivityRuntimeContracts();

    expect(app(CockpitOperatorIssuanceActivityRepositoryContract::class))->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityRepository::class)
        ->and(app(CockpitOperatorIssuanceActivityRecorderContract::class))->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityRecorder::class)
        ->and(app(CockpitOperatorIssuanceActivityActionHandoffContract::class))->toBeInstanceOf(XActionCockpitOperatorIssuanceActivityActionHandoff::class)
        ->and(app(CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract::class))->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class)
        ->and(app(CockpitOperatorIssuanceActivityJournalHandoffContract::class))->toBeInstanceOf(XJournalCockpitOperatorIssuanceActivityJournalHandoff::class)
        ->and(app(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class))->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class);
});

it('continues to resolve direct class names for cockpit operator issuance activity runtime services', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', DatabaseCockpitOperatorIssuanceActivityRecorder::class);
    config()->set('x-change.cockpit.operator_issuance_activity.action_handoff', XActionCockpitOperatorIssuanceActivityActionHandoff::class);
    config()->set('x-change.cockpit.operator_issuance_activity.action_handoff_status_projector', DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class);
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff', XJournalCockpitOperatorIssuanceActivityJournalHandoff::class);
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff_status_projector', DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class);

    forgetCockpitOperatorIssuanceActivityRuntimeContracts();

    expect(app(CockpitOperatorIssuanceActivityRepositoryContract::class))->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityRepository::class)
        ->and(app(CockpitOperatorIssuanceActivityRecorderContract::class))->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityRecorder::class)
        ->and(app(CockpitOperatorIssuanceActivityActionHandoffContract::class))->toBeInstanceOf(XActionCockpitOperatorIssuanceActivityActionHandoff::class)
        ->and(app(CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract::class))->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class)
        ->and(app(CockpitOperatorIssuanceActivityJournalHandoffContract::class))->toBeInstanceOf(XJournalCockpitOperatorIssuanceActivityJournalHandoff::class)
        ->and(app(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class))->toBeInstanceOf(DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class);
});

function forgetCockpitOperatorIssuanceActivityRuntimeContracts(): void
{
    app()->forgetInstance(CockpitOperatorIssuanceActivityRepositoryContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityRecorderContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityActionHandoffContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityJournalHandoffContract::class);
    app()->forgetInstance(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class);
}
