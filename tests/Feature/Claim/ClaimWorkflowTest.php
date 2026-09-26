<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Illuminate\Validation\ValidationException;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;
use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use LBHurtado\XChange\Enums\ClaimWorkflowInterpretationState;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetAuthorizationExecutionService;
use LBHurtado\XChange\Services\Claim\ClaimExperienceCompiler;
use LBHurtado\XChange\Services\Claim\ClaimWorkflowReadModelProjector;
use LBHurtado\XChange\Services\Claim\DefaultClaimWorkflowResolver;
use LBHurtado\XChange\Services\Claim\FormFlowClaimWorkflowMutator;
use LBHurtado\XChange\Services\Claim\VoucherClaimFlowCompiler;
use LBHurtado\XChange\Services\Execution\CampaignCoverageCompletionExecutionDriver;
use Symfony\Component\Yaml\Yaml;

it('binds the shared claim workflow resolver', function () {
    expect(app(ClaimWorkflowResolverContract::class))->toBeInstanceOf(DefaultClaimWorkflowResolver::class);
});

it('scopes intermediate completion copy without changing the final confirmation or other workflows', function (string $key, bool $completion): void {
    $workflow = new ClaimWorkflowDescriptorData(
        key: $key,
        requires_mobile: true,
        requires_destination: false,
        requires_amount: false,
        title: 'Complete Your Details',
        description: 'Original description',
        confirmation_label: 'Submit Details',
        confirmation_title: 'Review your details',
    );
    $steps = array_map(fn (string $name): array => [
        'handler' => $name === 'otp_verification' ? 'otp' : 'form',
        'config' => [
            'step_name' => $name,
            'fields' => [['name' => 'mobile', 'group' => 'redeemer']],
        ],
    ], ['wallet_info', 'bio_fields', 'otp_verification']);
    $result = app(FormFlowClaimWorkflowMutator::class)->mutate(['steps' => $steps], $workflow);

    foreach (array_slice($result['steps'], 0, 2) as $step) {
        expect($step['config']['claim_workflow']['title'])->toBe($completion ? 'Payment received' : $workflow->title)
            ->and($step['config']['claim_workflow']['confirmation_label'])->toBe($completion ? 'Continue' : 'Submit Details')
            ->and($step['config']['fields'][0]['group'] ?? null)->toBe($completion ? null : 'redeemer');
    }
    expect($result['steps'][2]['config']['claim_workflow']['confirmation_label'])->toBe('Submit Details')
        ->and($result['metadata']['claim_workflow']['confirmation_label'])->toBe('Submit Details')
        ->and($result['metadata']['claim_workflow']['confirmation_title'])->toBe('Review your details')
        ->and($workflow->confirmation_label)->toBe('Submit Details');
})->with([
    'completion only' => ['campaign.coverage-completion.v1', true],
    'unrelated workflow unchanged' => ['custom.claim.v1', false],
]);

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
    config()->set('app.name', 'x-PayOut');

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
                            ['name' => 'mobile', 'group' => 'redeemer', 'required' => false],
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
        ->and($workflow->title)->toBe('Accept Invitation')
        ->and($workflow->description)->toBe('Enter your details to create your account and continue to the workspace.')
        ->and($workflow->requires_mobile)->toBeTrue()
        ->and($workflow->requires_destination)->toBeFalse()
        ->and($workflow->requires_amount)->toBeFalse()
        ->and($workflow->requires_authenticated_officer)->toBeFalse()
        ->and($workflow->authentication_mode)->toBe(ClaimAuthenticationMode::ClaimantHandoff)
        ->and($workflow->required_claim_fields)->toBe(['full_name', 'email', 'mobile'])
        ->and($workflow->review['mobile_verification_required'])->toBeFalse()
        ->and(array_column($walletStep['fields'], 'name'))->toBe(['mobile'])
        ->and($walletStep['fields'][0]['required'])->toBeTrue()
        ->and($walletStep['fields'][0]['group'])->toBe('account')
        ->and($walletStep['claim_workflow']['authentication_mode'])->toBe('claimant_handoff')
        ->and($walletStep['app_name'])->toBe('x-PayOut')
        ->and($bioStep['fields'][0]['required'])->toBeTrue()
        ->and($bioStep['fields'][1]['required'])->toBeTrue()
        ->and($bioStep['claim_workflow']['key'])->toBe('onboarding.account-provisioning.v1')
        ->and($bioStep['app_name'])->toBe('x-PayOut')
        ->and($otpStep['purpose'])->toBe('onboarding.account')
        ->and($otpStep['app_name'])->toBe('x-PayOut')
        ->and($payload['metadata']['claim_workflow']['confirmation_label'])
        ->toBe('Create my account')
        ->and($payload['metadata']['claim_workflow']['confirmation_title'])
        ->toBe('Review your details');
});

it('compiles account funding without collecting a payout destination', function () {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'instructions' => [
            'claim' => [
                'outcomes' => [['key' => 'account_funding']],
                'default_outcome' => 'account_funding',
            ],
        ],
    ]);

    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);
    $instructions = app(FormFlowClaimWorkflowMutator::class)->apply(
        FormFlowInstructionsData::from([
            'reference_id' => 'claim-workflow-account-funding-01',
            'callbacks' => ['on_complete' => 'https://example.test/claim-workflow-account-funding-01'],
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
        '09285243656',
    );

    $payload = $instructions->toArray();
    $walletStep = $payload['steps'][0]['config'];

    expect($workflow->key)->toBe('account-funding.v1')
        ->and($workflow->requires_mobile)->toBeTrue()
        ->and($workflow->requires_destination)->toBeFalse()
        ->and($workflow->requires_amount)->toBeFalse()
        ->and($workflow->authentication_mode)->toBe(ClaimAuthenticationMode::ClaimantHandoff)
        ->and($workflow->required_claim_fields)->toBe(['mobile'])
        ->and(array_column($walletStep['fields'], 'name'))->toBe(['mobile'])
        ->and($walletStep['fields'][0]['required'])->toBeTrue()
        ->and($payload['metadata']['claim_workflow']['confirmation_label'])->toBe('Add to My Account');
});

it('compiles lead intake without collecting payout destination or amount', function () {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'instructions' => [
            'claim' => [
                'default_outcome' => 'lead_intake',
            ],
        ],
    ]);

    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);
    $instructions = app(FormFlowClaimWorkflowMutator::class)->apply(
        FormFlowInstructionsData::from([
            'reference_id' => 'claim-workflow-lead-intake-01',
            'callbacks' => ['on_complete' => 'https://example.test/claim-workflow-lead-intake-01'],
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
                            ['name' => 'name', 'required' => false],
                            ['name' => 'email', 'required' => false],
                            ['name' => 'address', 'required' => false],
                            ['name' => 'birth_date', 'required' => false],
                            ['name' => 'reference_code', 'required' => false],
                        ],
                    ],
                ],
            ],
        ]),
        $workflow,
    );

    $payload = $instructions->toArray();
    $walletStep = $payload['steps'][0]['config'];
    $bioStep = $payload['steps'][1]['config'];

    expect($workflow->key)->toBe('lead-intake.v1')
        ->and($workflow->title)->toBe('Submit Application')
        ->and($workflow->requires_mobile)->toBeTrue()
        ->and($workflow->requires_destination)->toBeFalse()
        ->and($workflow->requires_amount)->toBeFalse()
        ->and($workflow->authentication_mode)->toBe(ClaimAuthenticationMode::ClaimantHandoff)
        ->and($workflow->required_claim_fields)->toBe(['name', 'mobile', 'email'])
        ->and(array_column($walletStep['fields'], 'name'))->toBe(['mobile'])
        ->and($walletStep['fields'][0]['required'])->toBeTrue()
        ->and($walletStep['claim_workflow']['key'])->toBe('lead-intake.v1')
        ->and($walletStep['claim_workflow']['confirmation_label'])->toBe('Submit Application')
        ->and($bioStep['fields'][0]['required'])->toBeTrue()
        ->and($bioStep['fields'][1]['required'])->toBeTrue()
        ->and($bioStep['fields'][2]['required'])->toBeFalse()
        ->and($payload['metadata']['claim_workflow']['confirmation_title'])->toBe('Review your application');
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

it('keeps the campaign payout recovery otp action visible inline', function (): void {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'treasury' => [
            'pay_code_reservation' => ['status' => 'recovery_pending'],
        ],
        'instructions' => [
            'metadata' => [
                'custom' => [
                    'campaign' => [
                        'claim_activation' => 'provider_rejection',
                        'beneficiary_mobile' => '09175180722',
                    ],
                ],
            ],
        ],
    ]);

    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);
    $instructions = app(FormFlowClaimWorkflowMutator::class)->apply(
        FormFlowInstructionsData::from([
            'reference_id' => 'claim-workflow-campaign-recovery-01',
            'callbacks' => ['on_complete' => 'https://example.test/claim-workflow-campaign-recovery-01'],
            'steps' => [[
                'handler' => 'form',
                'config' => [
                    'step_name' => 'wallet_info',
                    'fields' => [
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

    expect($workflow->key)->toBe('campaign.payout-recovery.v1')
        ->and($payload['steps'][0]['config']['action_placement'])->toBe('viewport_bottom')
        ->and($payload['steps'][1]['config']['action_placement'])->toBe('inline');
});

it('requires authenticated mobile and otp activation for reusable balance', function (): void {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'instructions' => ['execution' => ['driver' => 'stored_value']],
    ]);

    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);

    expect($workflow->key)->toBe('stored-value.activation.v1')
        ->and($workflow->authentication_mode)->toBe(ClaimAuthenticationMode::ClaimantHandoff)
        ->and($workflow->requires_destination)->toBeFalse()
        ->and($workflow->requires_amount)->toBeFalse()
        ->and($workflow->required_claim_fields)->toBe(['mobile', 'otp']);
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

it('characterizes declared journey precedence without using entry point or amount', function (?string $driver, ?string $outcome, string $key): void {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'instructions' => [
            'execution' => ['driver' => $driver],
            'claim' => ['default_outcome' => $outcome],
            'cash' => ['amount' => 100],
            'metadata' => ['custom' => ['campaign' => ['endpoint' => '/x/o/demo/test']]],
        ],
    ]);
    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);
    expect($workflow)->toBeInstanceOf(ClaimWorkflowDescriptorData::class)
        ->and($workflow->key)->toBe($key);
})->with([
    'legacy absent' => [null, null, 'disbursement.v1'],
    'explicit ordinary' => ['default', 'provider_disbursement', 'disbursement.v1'],
    'live cash' => ['x_change_live_cash', 'provider_disbursement', 'disbursement.v1'],
    'envelope legacy' => ['settlement_envelope', null, 'disbursement.v1'],
    'collection legacy' => ['payable_collection', null, 'disbursement.v1'],
    'provider funding payment record' => ['x_change_provider_funding', null, 'disbursement.v1'],
    'account funding payment record' => ['x_change_account_funding', null, 'disbursement.v1'],
    'funding' => [null, 'account_funding', 'account-funding.v1'],
    'intake' => ['default', 'lead_intake', 'lead-intake.v1'],
    'settlement intake' => ['settlement_envelope', 'lead_intake', 'lead-intake.v1'],
    'onboarding' => ['onboarding_account_provisioning', null, 'onboarding.account-provisioning.v1'],
    'funded onboarding' => ['onboarding_account_provisioning', 'account_funding', 'onboarding.account-provisioning.v1'],
    'legacy commissioning' => ['onboarding_account_provisioning', 'provider_disbursement', 'onboarding.account-provisioning.v1'],
    'officer' => ['campaign_worksheet_authorization', 'authorize_campaign', 'campaign.officer-authorization.v1'],
    'stored value' => ['stored_value', null, 'stored-value.activation.v1'],
    'stored value legacy outcome' => ['stored_value', 'provider_disbursement', 'stored-value.activation.v1'],
]);

it('classifies a valid coverage completion intent without collecting a payout destination', function (): void {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'instructions' => [
            'execution' => ['driver' => CampaignCoverageCompletionExecutionDriver::Key],
            'claim' => ['default_outcome' => 'envelope_completion'],
        ],
    ]);
    $voucher->shouldReceive('getKey')->andReturn(-1);

    $workflow = (new DefaultClaimWorkflowResolver)->resolve($voucher);

    expect($workflow->key)->toBe('campaign.coverage-completion.v1')
        ->and($workflow->requires_mobile)->toBeTrue()
        ->and($workflow->requires_destination)->toBeFalse()
        ->and($workflow->authentication_mode)->toBe(ClaimAuthenticationMode::ClaimantHandoff);
});

it('rejects unsupported or conflicting explicit intent before payout fallback', function (mixed $driver, mixed $outcome): void {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'instructions' => ['execution' => ['driver' => $driver], 'claim' => ['default_outcome' => $outcome]],
    ]);
    expect(fn () => (new DefaultClaimWorkflowResolver)->resolve($voucher))
        ->toThrow(ValidationException::class);
})->with([
    ['unknown', null], ['', null], [[], null], [false, null],
    [null, 'unknown'], [null, ''], [null, []], [null, false],
    [null, 'envelope_completion'], [null, 'authorize_campaign'],
    ['campaign_worksheet_authorization', 'provider_disbursement'],
    ['stored_value', 'account_funding'],
    ['onboarding_account_provisioning', 'lead_intake'],
    ['x_change_provider_funding', 'provider_disbursement'],
    ['x_change_account_funding', 'account_funding'],
    [CampaignCoverageCompletionExecutionDriver::Key, 'lead_intake'],
]);

it('projects supported and conflicting claim journeys for read-only consumers', function (mixed $driver, ClaimWorkflowInterpretationState $state): void {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'instructions' => ['execution' => ['driver' => $driver]],
    ]);

    $interpretation = app(ClaimWorkflowReadModelProjector::class)->project($voucher);

    expect($interpretation->state)->toBe($state)
        ->and($interpretation->workflow?->key)->toBe(
            $state === ClaimWorkflowInterpretationState::Resolved ? 'disbursement.v1' : null,
        )
        ->and($interpretation->requiresAttention())->toBe(
            $state === ClaimWorkflowInterpretationState::NeedsAttention,
        );
})->with([
    'resolved' => [null, ClaimWorkflowInterpretationState::Resolved],
    'needs attention' => ['unsupported', ClaimWorkflowInterpretationState::NeedsAttention],
]);

it('does not hide unexpected resolver failures in the read-only workflow projector', function (): void {
    $resolver = Mockery::mock(ClaimWorkflowResolverContract::class);
    $resolver->shouldReceive('resolve')->andThrow(new RuntimeException('unexpected resolver failure'));
    $voucher = Mockery::mock(Voucher::class);

    expect(fn () => (new ClaimWorkflowReadModelProjector($resolver))->project($voucher))
        ->toThrow(RuntimeException::class, 'unexpected resolver failure');
});

it('keeps mutable claim compilation fail closed for unsupported intent', function (): void {
    $voucher = issueVoucher();
    $metadata = $voucher->getAttribute('metadata');
    data_set($metadata, 'instructions.execution.driver', 'unsupported_runtime_driver');
    $voucher->forceFill(['metadata' => $metadata])->save();

    expect(fn () => app(VoucherClaimFlowCompiler::class)->compile($voucher->refresh()))
        ->toThrow(ValidationException::class);
});

it('rejects conflicting recovery instructions but preserves ordinary recovery', function (?string $driver, ?string $outcome, bool $valid): void {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn([
        'treasury' => ['pay_code_reservation' => ['status' => 'recovery_pending']],
        'instructions' => [
            'execution' => ['driver' => $driver], 'claim' => ['default_outcome' => $outcome],
            'metadata' => ['custom' => ['campaign' => ['claim_activation' => 'provider_rejection']]],
        ],
    ]);
    if ($valid) {
        expect((new DefaultClaimWorkflowResolver)->resolve($voucher)->key)->toBe('campaign.payout-recovery.v1');
    } else {
        expect(fn () => (new DefaultClaimWorkflowResolver)->resolve($voucher))
            ->toThrow(ValidationException::class);
    }
})->with([
    [null, null, true], ['x_change_live_cash', 'provider_disbursement', true],
    ['onboarding_account_provisioning', null, false], ['default', 'lead_intake', false],
    ['campaign_worksheet_authorization', 'authorize_campaign', false],
]);

it('does not infer payout recovery from only one recovery marker', function (array $metadata): void {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getAttribute')->with('metadata')->andReturn($metadata);

    expect((new DefaultClaimWorkflowResolver)->resolve($voucher)->key)->toBe('disbursement.v1');
})->with([
    'provider rejection without pending reservation' => [[
        'instructions' => [
            'metadata' => ['custom' => ['campaign' => ['claim_activation' => 'provider_rejection']]],
        ],
    ]],
    'pending reservation without provider rejection' => [[
        'treasury' => ['pay_code_reservation' => ['status' => 'recovery_pending']],
        'instructions' => [],
    ]],
]);

it('resolves an automatic claim rail from the actual payout amount', function (): void {
    app()->instance(DriverService::class, new class extends DriverService
    {
        public function __construct()
        {
            $this->config = Yaml::parseFile(__DIR__.'/../../../config/form-flow-drivers/voucher-redemption.yaml');
        }
    });
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
