<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetAuthorizationExecutionService;
use LBHurtado\XChange\Services\Claim\ClaimExperienceCompiler;
use LBHurtado\XChange\Services\Claim\DefaultClaimWorkflowResolver;
use LBHurtado\XChange\Services\Claim\FormFlowClaimWorkflowMutator;
use LBHurtado\XChange\Services\Claim\VoucherClaimFlowCompiler;

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
    $instructions = app(FormFlowClaimWorkflowMutator::class)->apply(
        FormFlowInstructionsData::from([
            'reference_id' => 'claim-workflow-01',
            'callbacks' => ['on_complete' => 'https://example.test/claim-workflow-01'],
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
        ->and($workflow->authentication_mode)->toBe(ClaimAuthenticationMode::AuthenticatedOfficer)
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

it('compiles onboarding account provisioning without payout fields or route guessing', function () {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'instructions' => [
            'onboarding' => true,
            'execution' => [
                'driver' => 'onboarding_account_provisioning',
                'metadata' => [
                    'onboarding' => [
                        'workflow_key' => 'onboarding.account-provisioning.v1',
                        'mobile_verification_required' => false,
                    ],
                ],
            ],
        ],
    ]);

    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);
    $instructions = app(FormFlowClaimWorkflowMutator::class)->apply(
        FormFlowInstructionsData::from([
            'reference_id' => 'claim-workflow-onboarding-01',
            'callbacks' => ['on_complete' => 'https://example.test/claim-workflow-onboarding-01'],
            'steps' => [
                [
                    'handler' => 'form',
                    'config' => [
                        'step_name' => 'wallet_info',
                        'fields' => [
                            ['name' => 'amount'],
                            ['name' => 'settlement_rail'],
                            ['name' => 'mobile', 'required' => false],
                            ['name' => 'bank_code'],
                            ['name' => 'account_number'],
                        ],
                    ],
                ],
                [
                    'handler' => 'form',
                    'config' => [
                        'step_name' => 'bio_fields',
                        'fields' => [
                            ['name' => 'full_name', 'required' => false],
                            ['name' => 'email', 'required' => false],
                        ],
                    ],
                ],
                [
                    'handler' => 'otp',
                    'config' => ['step_name' => 'otp_verification'],
                ],
            ],
        ]),
        $workflow,
    );

    $payload = $instructions->toArray();
    $walletStep = $payload['steps'][0]['config'];
    $bioStep = $payload['steps'][1]['config'];
    $otpStep = $payload['steps'][2]['config'];

    expect($workflow->key)->toBe('onboarding.account-provisioning.v1')
        ->and($workflow->requires_mobile)->toBeTrue()
        ->and($workflow->requires_destination)->toBeFalse()
        ->and($workflow->requires_amount)->toBeFalse()
        ->and($workflow->requires_authenticated_officer)->toBeFalse()
        ->and($workflow->authentication_mode)->toBe(ClaimAuthenticationMode::ClaimantHandoff)
        ->and($workflow->required_claim_fields)->toBe(['full_name', 'email', 'mobile'])
        ->and($workflow->review['mobile_verification_required'])->toBeFalse()
        ->and(array_column($walletStep['fields'], 'name'))->toBe(['mobile'])
        ->and($walletStep['fields'][0]['required'])->toBeTrue()
        ->and($walletStep['claim_workflow']['authentication_mode'])->toBe('claimant_handoff')
        ->and($bioStep['fields'][0]['required'])->toBeTrue()
        ->and($bioStep['fields'][1]['required'])->toBeTrue()
        ->and($bioStep['claim_workflow']['key'])->toBe('onboarding.account-provisioning.v1')
        ->and($otpStep['purpose'])->toBe('onboarding.account')
        ->and($payload['metadata']['claim_workflow']['confirmation_label'])
        ->toBe('Create My Account');
});

it('keeps destination collection for an ordinary disbursement workflow', function () {
    config()->set('x-change.claim.experience_ui.variant', 'immersive');

    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn(['instructions' => []]);

    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);
    $instructions = app(FormFlowClaimWorkflowMutator::class)->apply(
        FormFlowInstructionsData::from([
            'reference_id' => 'claim-workflow-02',
            'callbacks' => ['on_complete' => 'https://example.test/claim-workflow-02'],
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
            ], [
                'handler' => 'otp',
                'config' => [
                    'step_name' => 'otp_verification',
                    'fields' => [],
                ],
            ]],
        ]),
        $workflow,
    );

    $payload = $instructions->toArray();
    $walletStep = $payload['steps'][0]['config'];
    $otpStep = $payload['steps'][1]['config'];
    $fieldNames = array_column($walletStep['fields'], 'name');
    $bankField = collect($walletStep['fields'])->firstWhere('name', 'bank_code');
    $pnb = collect($bankField['institution_options'])->firstWhere('key', 'pnb');

    expect($workflow->key)->toBe('disbursement.v1')
        ->and($workflow->requires_destination)->toBeTrue()
        ->and($workflow->authentication_mode)->toBe(ClaimAuthenticationMode::None)
        ->and($walletStep['claim_workflow']['key'])->toBe('disbursement.v1')
        ->and($walletStep['claim_workflow']['confirmation_label'])->toBe('Confirm Redemption')
        ->and($walletStep['ui_variant'])->toBe('immersive')
        ->and($walletStep['action_placement'])->toBe('viewport_bottom')
        ->and($walletStep['ui_layout']['density'])->toBe('compact')
        ->and($walletStep['ui_layout']['capture_surface'])->toBe('edge_to_edge')
        ->and($walletStep['ui_layout']['minimize_scroll'])->toBeTrue()
        ->and($walletStep['app_name'])->toBe('Pay Code')
        ->and($walletStep['app_logo'])->toBe('/vendor/x-change/images/pay-code/pay-code-logo.svg')
        ->and($walletStep['package_versions'])->toContain([
            'name' => '3neti/x-change',
            'version' => InstalledVersions::getPrettyVersion('3neti/x-change'),
        ])
        ->and($walletStep['show_package_versions'])->toBeBool()
        ->and($otpStep['ui_variant'])->toBe('immersive')
        ->and($otpStep['action_placement'])->toBe('viewport_bottom')
        ->and($otpStep['app_name'])->toBe('Pay Code')
        ->and($otpStep['app_logo'])->toBe('/vendor/x-change/images/pay-code/pay-code-logo.svg')
        ->and($otpStep['package_versions'])->toContain([
            'name' => '3neti/form-flow',
            'version' => InstalledVersions::getPrettyVersion('3neti/form-flow'),
        ])
        ->and($walletStep['auto_sync']['enabled'])->toBeFalse()
        ->and($fieldNames)->toBe(['amount', 'settlement_rail', 'mobile', 'bank_code', 'account_number'])
        ->and($bankField['help_text'])->toBe('Choose the receiving bank or wallet by name.')
        ->and($pnb['name'])->toBe('Philippine National Bank')
        ->and($pnb['value'])->toBe('PNBMPHMMTOD')
        ->and($pnb)->not->toHaveKey('code');
});

it('uses the issuer-authoritative rail to filter claim destinations', function (string $rail, string $supportedBank, ?string $excludedBank): void {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn(['instructions' => []]);

    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);
    $instructions = app(FormFlowClaimWorkflowMutator::class)->apply(
        FormFlowInstructionsData::from([
            'reference_id' => 'claim-workflow-rail-01',
            'callbacks' => ['on_complete' => 'https://example.test/claim-workflow-rail-01'],
            'steps' => [[
                'handler' => 'form',
                'config' => [
                    'step_name' => 'wallet_info',
                    'fields' => [
                        ['name' => 'amount'],
                        ['name' => 'settlement_rail', 'default' => 'INSTAPAY'],
                        ['name' => 'mobile'],
                        ['name' => 'bank_code'],
                        ['name' => 'account_number'],
                    ],
                ],
            ]],
        ]),
        $workflow,
        settlementRail: $rail,
    );

    $fields = $instructions->toArray()['steps'][0]['config']['fields'];
    $railField = collect($fields)->firstWhere('name', 'settlement_rail');
    $bankField = collect($fields)->firstWhere('name', 'bank_code');
    $optionValues = collect($bankField['institution_options'])->pluck('value');

    expect($railField['default'])->toBe($rail)
        ->and($railField['readonly'])->toBeTrue()
        ->and($railField['persist'])->toBeFalse()
        ->and($optionValues)->toContain($supportedBank);

    if ($excludedBank !== null) {
        expect($optionValues)->not->toContain($excludedBank);
    }
})->with([
    'InstaPay' => ['INSTAPAY', 'GXCHPHM2XXX', null],
    'PESONet' => ['PESONET', 'BNORPHMMXXX', 'GXCHPHM2XXX'],
]);

it('resolves an automatic claim rail from the actual payout amount', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 750,
        settlementRail: null,
    ));
    $compiler = app(VoucherClaimFlowCompiler::class);

    $smallFields = collect(data_get(
        $compiler->compile($voucher, payoutAmount: 49_999.99)->instructions->toArray(),
        'steps.1.config.fields',
        [],
    ));
    $largeFields = collect(data_get(
        $compiler->compile($voucher, payoutAmount: 50_000)->instructions->toArray(),
        'steps.1.config.fields',
        [],
    ));

    expect($smallFields->firstWhere('name', 'settlement_rail')['default'])->toBe('INSTAPAY')
        ->and($largeFields->firstWhere('name', 'settlement_rail')['default'])->toBe('PESONET');
});
