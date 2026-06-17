<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XRider\Contracts\RiderExperienceResolverContract;
use LBHurtado\XRider\Data\RiderContentData;
use LBHurtado\XRider\Data\RiderExperienceData;
use LBHurtado\XRider\Data\RiderStageCollectionData;
use LBHurtado\XRider\Data\RiderStageData;
use LBHurtado\XRider\Data\RiderSubjectData;
use LBHurtado\XRider\Enums\RiderContentType;
use LBHurtado\XRider\Enums\RiderOutcomeState;
use LBHurtado\XRider\Enums\RiderStageType;

it('shows a voucher by code through the lifecycle route surface', function () {
    $result = (object) [
        'id' => 1,
        'voucher_id' => 99,
        'code' => 'TEST-1234',
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'issued',
        'display_status' => 'awaiting_approval',
        'issuer_id' => 1,
        'claimed' => false,
        'fully_claimed' => false,
        'approval' => [
            'required' => true,
            'type' => 'otp',
            'provider' => 'paynamics',
            'reference_id' => 'TEST-1234-09173011987',
            'message' => 'Paynamics payout OTP is pending.',
            'action_url' => '/x/pay-codes/TEST-1234/approval',
        ],
    ];

    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('TEST-1234')
        ->andReturn($result);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->getJson(xchangeApi('vouchers/code/TEST-1234'));

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.voucher.code', 'TEST-1234')
        ->assertJsonPath('data.voucher.display_status', 'awaiting_approval')
        ->assertJsonPath('data.voucher.approval.required', true);
});

it('exposes resolved rider pre claim content in voucher preview', function (): void {
    $resolver = Mockery::mock(RiderExperienceResolverContract::class);

    $resolver->shouldReceive('resolve')
        ->once()
        ->andReturn(new RiderExperienceData(
            state: RiderOutcomeState::AcceptedSuccess,
            subject: new RiderSubjectData(
                type: 'voucher',
                id: 99,
                code: 'TEST-PRECLAIM',
                meta: [],
            ),
            preClaim: new RiderContentData(
                enabled: true,
                type: RiderContentType::Markdown,
                content: 'Pre-claim splash content.',
                meta: [
                    'stage_key' => 'pre-claim-test',
                    'timeout' => 3,
                ],
            ),
        ));

    $this->app->instance(RiderExperienceResolverContract::class, $resolver);

    $result = (object) [
        'id' => 1,
        'voucher_id' => 99,
        'code' => 'TEST-PRECLAIM',
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'issued',
        'issuer_id' => 1,
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [
            'rider' => [
                'stages' => [
                    [
                        'type' => 'splash',
                        'key' => 'pre-claim-test',
                        'presentation' => 'inline',
                        'content' => 'Pre-claim splash content.',
                        'content_type' => 'markdown',
                        'timeout' => 3,
                    ],
                ],
            ],
        ],
    ];

    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('TEST-PRECLAIM')
        ->andReturn($result);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->getJson(xchangeApi('vouchers/code/TEST-PRECLAIM'));

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.voucher.code', 'TEST-PRECLAIM')
        ->assertJsonPath('data.voucher.rider.preClaim.content', 'Pre-claim splash content.')
        ->assertJsonPath('data.voucher.rider.preClaim.type', 'markdown')
        ->assertJsonPath('data.voucher.rider.preClaim.meta.stage_key', 'pre-claim-test')
        ->assertJsonPath('data.voucher.rider.preClaim.meta.timeout', 3);
});

it('exposes resolved rider visual stages in voucher preview', function (): void {
    $resolver = Mockery::mock(RiderExperienceResolverContract::class);

    $resolver->shouldReceive('resolve')
        ->once()
        ->andReturn(new RiderExperienceData(
            state: RiderOutcomeState::AcceptedSuccess,
            subject: new RiderSubjectData(
                type: 'voucher',
                id: 99,
                code: 'TEST-STAGES',
                meta: [],
            ),
            stages: new RiderStageCollectionData(
                stages: [
                    new RiderStageData(
                        type: RiderStageType::Splash,
                        enabled: true,
                        key: 'preview-splash',
                        payload: [
                            'content' => 'Preview splash.',
                            'presentation' => 'inline',
                        ],
                    ),
                    new RiderStageData(
                        type: RiderStageType::Link,
                        enabled: true,
                        key: 'preview-link',
                        payload: [
                            'label' => 'Learn more',
                            'url' => 'https://example.com',
                            'presentation' => 'inline',
                        ],
                    ),
                    new RiderStageData(
                        type: RiderStageType::Image,
                        enabled: true,
                        key: 'preview-image',
                        payload: [
                            'src' => 'https://placehold.co/600x240',
                            'alt' => 'Preview image',
                            'presentation' => 'inline',
                        ],
                    ),
                ],
            ),
        ));

    $this->app->instance(RiderExperienceResolverContract::class, $resolver);

    $result = (object) [
        'id' => 1,
        'voucher_id' => 99,
        'code' => 'TEST-STAGES',
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'issued',
        'issuer_id' => 1,
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [
            'rider' => [
                'stages' => [
                    [
                        'type' => 'splash',
                        'key' => 'preview-splash',
                        'presentation' => 'inline',
                        'content' => 'Preview splash.',
                    ],
                    [
                        'type' => 'link',
                        'key' => 'preview-link',
                        'payload' => [
                            'label' => 'Learn more',
                            'url' => 'https://example.com',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'key' => 'preview-image',
                        'src' => 'https://placehold.co/600x240',
                        'alt' => 'Preview image',
                    ],
                ],
            ],
        ],
    ];

    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('TEST-STAGES')
        ->andReturn($result);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->getJson(xchangeApi('vouchers/code/TEST-STAGES'));

    $response
        ->assertOk()
        ->assertJsonPath('data.voucher.rider.stages.stages.0.type', 'splash')
        ->assertJsonPath('data.voucher.rider.stages.stages.0.key', 'preview-splash')
        ->assertJsonPath('data.voucher.rider.stages.stages.1.type', 'link')
        ->assertJsonPath('data.voucher.rider.stages.stages.1.key', 'preview-link')
        ->assertJsonPath('data.voucher.rider.stages.stages.1.payload.label', 'Learn more')
        ->assertJsonPath('data.voucher.rider.stages.stages.1.payload.url', 'https://example.com')
        ->assertJsonPath('data.voucher.rider.stages.stages.2.type', 'image')
        ->assertJsonPath('data.voucher.rider.stages.stages.2.key', 'preview-image')
        ->assertJsonPath('data.voucher.rider.stages.stages.2.payload.alt', 'Preview image');
});

it('exposes voucher instructions in voucher preview', function (): void {
    $result = (object) [
        'id' => 1,
        'voucher_id' => 99,
        'code' => 'TEST-INSTRUCTIONS',
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'issued',
        'issuer_id' => 1,
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
            ],
            'rider' => [
                'message' => 'Instruction rider message.',
            ],
        ],
    ];

    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('TEST-INSTRUCTIONS')
        ->andReturn($result);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->getJson(xchangeApi('vouchers/code/TEST-INSTRUCTIONS'));

    $response
        ->assertOk()
        ->assertJsonPath('data.voucher.instructions.cash.amount', 100)
        ->assertJsonPath('data.voucher.instructions.cash.currency', 'PHP')
        ->assertJsonPath('data.voucher.instructions.rider.message', 'Instruction rider message.');
});

it('returns voucher preview even without rider instructions', function (): void {
    $result = (object) [
        'id' => 1,
        'voucher_id' => 99,
        'code' => 'TEST-NO-RIDER',
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'issued',
        'issuer_id' => 1,
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
            ],
        ],
    ];

    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('TEST-NO-RIDER')
        ->andReturn($result);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->getJson(xchangeApi('vouchers/code/TEST-NO-RIDER'));

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.voucher.code', 'TEST-NO-RIDER')
        ->assertJsonPath('data.voucher.instructions.cash.amount', 100);
});

it('preserves sanitized rider splash metadata in voucher preview instructions', function (): void {
    $result = (object) [
        'id' => 1,
        'voucher_id' => 99,
        'code' => 'TEST-SANITIZED-SPLASH',
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'issued',
        'issuer_id' => 1,
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
            ],
            'rider' => [
                'splash' => '<div class="text-center"><strong>Hello</strong></div>',
                'splash_timeout' => 3,
                'splash_meta' => [
                    'sanitized' => true,
                    'html_profile' => 'rider_splash',
                ],
            ],
        ],
    ];

    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('TEST-SANITIZED-SPLASH')
        ->andReturn($result);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->getJson(xchangeApi('vouchers/code/TEST-SANITIZED-SPLASH'));

    $response
        ->assertOk()
        ->assertJsonPath('data.voucher.instructions.rider.splash', '<div class="text-center"><strong>Hello</strong></div>')
        ->assertJsonPath('data.voucher.instructions.rider.splash_timeout', 3)
        ->assertJsonPath('data.voucher.instructions.rider.splash_meta.sanitized', true)
        ->assertJsonPath('data.voucher.instructions.rider.splash_meta.html_profile', 'rider_splash');
});
