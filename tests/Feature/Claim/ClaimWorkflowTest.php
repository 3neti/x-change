<?php

declare(strict_types=1);

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetAuthorizationExecutionService;
use LBHurtado\XChange\Services\Claim\ClaimExperienceCompiler;
use LBHurtado\XChange\Services\Claim\DefaultClaimWorkflowResolver;
use LBHurtado\XChange\Services\Claim\FormFlowClaimWorkflowMutator;
use Tests\TestCase;

uses(TestCase::class);

it('binds the shared claim workflow resolver', function () {
    expect(app(ClaimWorkflowResolverContract::class))->toBeInstanceOf(DefaultClaimWorkflowResolver::class);
});

it('requires an authenticated officer before campaign authorization can execute', function () {
    $voucher = Mockery::mock(Voucher::class);

    expect(fn () => app(CampaignWorksheetAuthorizationExecutionService::class)->execute($voucher, [
        'mobile' => '09173011987',
    ]))->toThrow('An authenticated officer is required to approve a campaign worksheet.');
});

it('suppresses host default rider introductions for campaign officer authorization', function () {
    $voucher = (new Voucher)->forceFill([
        'metadata' => [
            'instructions' => [
                'cash' => ['amount' => 0, 'currency' => 'PHP'],
                'rider' => [],
                'execution' => ['driver' => 'campaign_worksheet_authorization'],
            ],
        ],
    ]);

    $experience = app(ClaimExperienceCompiler::class)->compile($voucher)->toArray();

    expect($experience['entry']['mode'])->toBe('form_first')
        ->and($experience['options']['suppress_legacy_pre_claim_stages'])->toBeTrue();
});

it('removes destination collection from a campaign officer authorization workflow', function () {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'instructions' => [
            'execution' => [
                'driver' => 'campaign_worksheet_authorization',
                'metadata' => [
                    'authorization_reference' => 'authorization-01',
                    'worksheet_reference' => 'worksheet-01',
                    'beneficiary_count' => 2,
                    'principal_minor' => 12_500,
                    'currency' => 'PHP',
                ],
            ],
        ],
    ]);

    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);
    $instructions = (new FormFlowClaimWorkflowMutator)->apply(
        FormFlowInstructionsData::from([
            'reference_id' => 'claim-workflow-01',
            'steps' => [[
                'handler' => 'form',
                'config' => [
                    'step_name' => 'wallet_info',
                    'title' => 'Disbursement Details',
                    'description' => 'Original',
                    'auto_sync' => ['enabled' => true],
                    'fields' => [
                        ['name' => 'amount'],
                        ['name' => 'settlement_rail'],
                        ['name' => 'mobile'],
                        ['name' => 'bank_code'],
                        ['name' => 'account_number'],
                    ],
                ],
            ]],
        ]),
        $workflow,
        '09173011987',
    );

    $walletStep = $instructions->toArray()['steps'][0]['config'];
    $claimWorkflow = $instructions->toArray()['metadata']['claim_workflow'];
    $fieldNames = array_column($walletStep['fields'], 'name');

    expect($workflow->key)->toBe('campaign.officer-authorization.v1')
        ->and($workflow->requires_mobile)->toBeTrue()
        ->and($workflow->requires_destination)->toBeFalse()
        ->and($workflow->requires_authenticated_officer)->toBeTrue()
        ->and($workflow->skip_form_flow_splash)->toBeTrue()
        ->and($walletStep['title'])->toBe('Campaign Officer Authorization')
        ->and($walletStep['claim_workflow']['key'])->toBe('campaign.officer-authorization.v1')
        ->and($walletStep['claim_workflow']['title'])->toBe('Campaign Officer Authorization')
        ->and($walletStep['claim_workflow']['description'])->toBe('Review the frozen worksheet for 2 beneficiaries totaling 125.00 PHP. No payout will be sent by this approval.')
        ->and($walletStep['claim_workflow']['confirmation_label'])->toBe('Authorize Campaign')
        ->and($claimWorkflow['title'])->toBe('Campaign Officer Authorization')
        ->and($claimWorkflow['description'])->toBe('Review the frozen worksheet for 2 beneficiaries totaling 125.00 PHP. No payout will be sent by this approval.')
        ->and($claimWorkflow['confirmation_label'])->toBe('Authorize Campaign')
        ->and($claimWorkflow['skip_form_flow_splash'])->toBeTrue()
        ->and($walletStep['auto_sync']['enabled'])->toBeFalse()
        ->and($fieldNames)->toBe(['mobile'])
        ->and($walletStep['fields'][0]['default'])->toBe('09173011987')
        ->and($walletStep['fields'][0]['readonly'])->toBeTrue();
});

it('keeps destination collection for an ordinary disbursement workflow', function () {
    config()->set('x-change.claim.experience_ui.variant', 'immersive');

    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn(['instructions' => []]);

    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);
    $instructions = (new FormFlowClaimWorkflowMutator)->apply(
        FormFlowInstructionsData::from([
            'reference_id' => 'claim-workflow-02',
            'steps' => [[
                'handler' => 'form',
                'config' => [
                    'step_name' => 'wallet_info',
                    'fields' => [
                        ['name' => 'amount'],
                        ['name' => 'settlement_rail'],
                        ['name' => 'mobile'],
                        ['name' => 'bank_code'],
                        ['name' => 'account_number'],
                    ],
                ],
            ]],
        ]),
        $workflow,
    );

    $walletStep = $instructions->toArray()['steps'][0]['config'];
    $fieldNames = array_column($walletStep['fields'], 'name');

    expect($workflow->key)->toBe('disbursement.v1')
        ->and($workflow->requires_destination)->toBeTrue()
        ->and($walletStep['claim_workflow']['key'])->toBe('disbursement.v1')
        ->and($walletStep['claim_workflow']['confirmation_label'])->toBe('Confirm Redemption')
        ->and($walletStep['ui_variant'])->toBe('immersive')
        ->and($walletStep['ui_layout']['density'])->toBe('compact')
        ->and($walletStep['ui_layout']['capture_surface'])->toBe('edge_to_edge')
        ->and($walletStep['ui_layout']['minimize_scroll'])->toBeTrue()
        ->and($fieldNames)->toBe(['amount', 'settlement_rail', 'mobile', 'bank_code', 'account_number']);
});
