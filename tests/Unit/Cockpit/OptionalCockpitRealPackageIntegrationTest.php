<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\OptionalCockpitIntegrationReadModels;

it('wires real read-only cockpit integration packages from x-change', function () {
    $expectedClasses = [
        'LBHurtado\\XJournal\\Services\\CockpitJournalReader',
        'LBHurtado\\XAction\\Contracts\\ActionHostComposerContract',
        'LBHurtado\\XFeedback\\Contracts\\FeedbackDeliveryConsoleContract',
        'LBHurtado\\XCampaign\\Contracts\\CampaignCockpitWorkspace',
    ];

    foreach ($expectedClasses as $expectedClass) {
        expect(class_exists($expectedClass) || interface_exists($expectedClass))
            ->toBeTrue("Expected {$expectedClass} to be installed by x-change.");
    }

    $planningKey = 'CAMPAIGN-READY-001';
    $planningInputClass = 'LBHurtado\\XCampaign\\Data\\CampaignPlanningInputData';
    $audienceInputClass = 'LBHurtado\\XCampaign\\Data\\CampaignAudiencePlanningInputData';
    $executionInputClass = 'LBHurtado\\XCampaign\\Data\\CampaignExecutionPlanningInputData';
    $createCampaignPlanContract = 'LBHurtado\\XCampaign\\Contracts\\CreatesCampaignPlans';
    $addAudienceContract = 'LBHurtado\\XCampaign\\Contracts\\AddsAudiencesToCampaignPlans';
    $planExecutionContract = 'LBHurtado\\XCampaign\\Contracts\\PlansCampaignExecutions';
    $campaignPlanRepositoryContract = 'LBHurtado\\XCampaign\\Contracts\\CampaignPlanRepository';

    $plan = app($createCampaignPlanContract)->handle(new $planningInputClass(
        name: 'Read-only Cockpit Adoption',
        description: 'Package-owned real adapter fixture',
        owner: 'operator-1',
        issuer: 'x-change',
        metadata: [
            'source' => 'x-change-package-test',
            'read_only' => true,
        ],
    ));

    $plan = app($addAudienceContract)->handle($plan, new $audienceInputClass(
        id: 'audience-1',
        name: 'Read-only Recipients',
    ));

    $plan = app($planExecutionContract)->handle($plan, new $executionInputClass(
        id: 'execution-1',
        audienceId: 'audience-1',
        correlationId: 'execution-1',
    ));

    app($campaignPlanRepositoryContract)->put($planningKey, $plan);

    $integrations = app(OptionalCockpitIntegrationReadModels::class);
    $query = new CockpitReadModelQueryData(
        code: $planningKey,
        operatorId: 'operator-1',
        include: ['journal', 'actions', 'feedback', 'campaigns'],
        correlationId: 'execution-1',
    );

    $journal = $integrations->journal($query)->toArray();
    $actions = $integrations->actions($query)->toArray();
    $feedback = $integrations->feedback($query)->toArray();
    $campaign = $integrations->campaignAdoption($query)->toArray();

    expect($journal['status'])->toBe('available')
        ->and($journal['authorized'])->toBeTrue()
        ->and($journal['redactions']['source'])->toBe('x-journal')
        ->and($journal['redactions']['evidence_only'])->toBeTrue()
        ->and($journal['redactions']['writes_journal_entries'])->toBeFalse()
        ->and($actions['status'])->toBe('available')
        ->and($actions['authorized'])->toBeTrue()
        ->and($actions['redactions']['source'])->toBe('x-action')
        ->and($actions['redactions']['presentation_only'])->toBeTrue()
        ->and($actions['redactions']['executes_action'])->toBeFalse()
        ->and($actions['redactions']['records_lifecycle'])->toBeFalse()
        ->and($feedback['status'])->toBe('available')
        ->and($feedback['authorized'])->toBeTrue()
        ->and($feedback['redactions']['source'])->toBe('x-feedback')
        ->and($feedback['redactions']['sends_feedback'])->toBeFalse()
        ->and($feedback['redactions']['calls_providers'])->toBeFalse()
        ->and($campaign['status'])->toBe('available')
        ->and($campaign['authorized'])->toBeTrue()
        ->and($campaign['source'])->toBe('x-campaign')
        ->and($campaign['mutation'])->toBe([
            'enabled' => false,
            'status' => 'blocked',
            'reason' => 'campaign-mutations-not-authorized',
        ])
        ->and($campaign['redactions']['source'])->toBe('x-campaign')
        ->and($campaign['redactions']['read_only'])->toBeTrue()
        ->and($campaign['redactions']['mutates_campaigns'])->toBeFalse()
        ->and($campaign['redactions']['issues_pay_codes'])->toBeFalse()
        ->and($campaign['redactions']['sends_feedback'])->toBeFalse()
        ->and($campaign['redactions']['writes_journal'])->toBeFalse()
        ->and($campaign['redactions']['moves_money'])->toBeFalse();
});
