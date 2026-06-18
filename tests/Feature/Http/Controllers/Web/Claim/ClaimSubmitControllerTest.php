<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Exceptions\ProviderProvisioningRequired;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;
use LBHurtado\XChange\Support\Claim\ClaimEvidenceSynchronizer;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;
use LBHurtado\XChange\Support\Claim\FormFlowClaimPayloadNormalizer;

beforeEach(function (): void {
    $this->withoutMiddleware(ShareXChangeBranding::class);

    $this->formFlowService = Mockery::mock(FormFlowService::class);
    $this->submitAction = Mockery::mock(SubmitWebPayCodeClaim::class);
    $this->payloadNormalizer = Mockery::mock(FormFlowClaimPayloadNormalizer::class);
    $this->evidenceSynchronizer = Mockery::mock(ClaimEvidenceSynchronizer::class);

    $this->app->instance(FormFlowService::class, $this->formFlowService);
    $this->app->instance(SubmitWebPayCodeClaim::class, $this->submitAction);
    $this->app->instance(FormFlowClaimPayloadNormalizer::class, $this->payloadNormalizer);
    $this->app->instance(ClaimEvidenceSynchronizer::class, $this->evidenceSynchronizer);
});

function claimSubmitTestVoucher(): Voucher
{
    return issueVoucher(validVoucherInstructions(
        amount: 10.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'prefix' => 'TEST',
            'mask' => '****',
            'inputs' => [
                'fields' => [],
            ],
        ],
    ));
}

function successfulClaimSubmitResult(Voucher $voucher): SubmitPayCodeClaimResultData
{
    return new SubmitPayCodeClaimResultData(
        voucher_code: (string) $voucher->code,
        claim_type: 'withdraw',
        claimed: true,
        status: 'withdrawn',
        requested_amount: 10.00,
        disbursed_amount: 10.00,
        currency: 'PHP',
        remaining_balance: 0,
        fully_claimed: true,
        disbursement: [
            'status' => 'requested',
        ],
        messages: [
            'Voucher withdrawal successful.',
        ],
    );
}

it('redirects to claim start when neither flow id nor reference id is provided', function (): void {
    $voucher = claimSubmitTestVoucher();

    $response = $this->post(route('x-change.claim.submit', ['code' => $voucher->code]));

    $response
        ->assertRedirect(route('x-change.claim.start', ['code' => $voucher->code]))
        ->assertSessionHasErrors(['error']);
});

it('loads form-flow state by reference id when reference id is provided', function (): void {
    $voucher = claimSubmitTestVoucher();

    $state = [
        'flow_id' => 'flow-test',
        'collected_data' => [
            'wallet_info' => [
                'mobile' => '+639173011987',
            ],
        ],
    ];

    $payload = [
        'mobile' => '+639173011987',
        'country' => 'PH',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09173011987',
        'inputs' => [
            'mobile' => '+639173011987',
        ],
    ];

    $this->formFlowService
        ->shouldReceive('getFlowStateByReference')
        ->once()
        ->with("claim-{$voucher->code}-123")
        ->andReturn($state);

    $this->payloadNormalizer
        ->shouldReceive('normalize')
        ->once()
        ->with($state['collected_data'])
        ->andReturn($payload);

    $order = [];

    $this->evidenceSynchronizer
        ->shouldReceive('sync')
        ->once()
        ->with(Mockery::on(
            fn (array $actual): bool => $actual['mobile'] === '+639173011987'
        ))
        ->andReturnUsing(function () use (&$order): void {
            $order[] = 'sync';
        });

    $this->submitAction
        ->shouldReceive('handle')
        ->once()
        ->andReturnUsing(function () use (&$order, $voucher): SubmitPayCodeClaimResultData {
            $order[] = 'submit';

            return successfulClaimSubmitResult($voucher);
        });

    $this->formFlowService
        ->shouldReceive('clearFlow')
        ->once()
        ->with('flow-test');

    $response = $this->post(route('x-change.claim.submit', ['code' => $voucher->code]), [
        'reference_id' => "claim-{$voucher->code}-123",
    ]);

    $response->assertRedirect(route('x-change.claim.success', ['code' => $voucher->code]));

    expect($order)->toBe(['sync', 'submit']);
});

it('loads form-flow state by flow id when reference id is missing', function (): void {
    $voucher = claimSubmitTestVoucher();

    $state = [
        'flow_id' => 'flow-test',
        'collected_data' => [
            'wallet_info' => [
                'mobile' => '+639173011987',
            ],
        ],
    ];

    $payload = [
        'mobile' => '+639173011987',
        'country' => 'PH',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09173011987',
        'inputs' => [
            'mobile' => '+639173011987',
        ],
    ];

    $this->formFlowService
        ->shouldReceive('getFlowState')
        ->once()
        ->with('flow-test')
        ->andReturn($state);

    $this->payloadNormalizer
        ->shouldReceive('normalize')
        ->once()
        ->with($state['collected_data'])
        ->andReturn($payload);

    $this->evidenceSynchronizer
        ->shouldReceive('sync')
        ->once();

    $this->submitAction
        ->shouldReceive('handle')
        ->once()
        ->andReturn(successfulClaimSubmitResult($voucher));

    $this->formFlowService
        ->shouldReceive('clearFlow')
        ->once()
        ->with('flow-test');

    $response = $this->post(route('x-change.claim.submit', ['code' => $voucher->code]), [
        'flow_id' => 'flow-test',
    ]);

    $response->assertRedirect(route('x-change.claim.success', ['code' => $voucher->code]));
});

it('redirects to claim start when form-flow state is missing', function (): void {
    $voucher = claimSubmitTestVoucher();

    $this->formFlowService
        ->shouldReceive('getFlowState')
        ->once()
        ->with('missing-flow')
        ->andReturn(null);

    $response = $this->post(route('x-change.claim.submit', ['code' => $voucher->code]), [
        'flow_id' => 'missing-flow',
    ]);

    $response
        ->assertRedirect(route('x-change.claim.start', ['code' => $voucher->code]))
        ->assertSessionHasErrors(['error']);
});

it('redirects to claim start when mobile is missing from normalized payload', function (): void {
    $voucher = claimSubmitTestVoucher();

    $state = [
        'flow_id' => 'flow-test',
        'collected_data' => [],
    ];

    $this->formFlowService
        ->shouldReceive('getFlowState')
        ->once()
        ->with('flow-test')
        ->andReturn($state);

    $this->payloadNormalizer
        ->shouldReceive('normalize')
        ->once()
        ->with([])
        ->andReturn([
            'country' => 'PH',
            'inputs' => [],
        ]);

    $response = $this->post(route('x-change.claim.submit', ['code' => $voucher->code]), [
        'flow_id' => 'flow-test',
    ]);

    $response
        ->assertRedirect(route('x-change.claim.start', ['code' => $voucher->code]))
        ->assertSessionHasErrors(['error']);
});

it('syncs evidence before submitting the claim', function (): void {
    $voucher = claimSubmitTestVoucher();

    $state = [
        'flow_id' => 'flow-test',
        'collected_data' => [
            'wallet_info' => [
                'mobile' => '+639173011987',
            ],
        ],
    ];

    $payload = [
        'mobile' => '+639173011987',
        'country' => 'PH',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09173011987',
        'inputs' => [
            'mobile' => '+639173011987',
            'kyc' => [
                'status' => 'approved',
                'transaction_id' => 'formflow-test',
            ],
        ],
    ];

    $order = [];

    $this->formFlowService
        ->shouldReceive('getFlowState')
        ->once()
        ->with('flow-test')
        ->andReturn($state);

    $this->payloadNormalizer
        ->shouldReceive('normalize')
        ->once()
        ->with($state['collected_data'])
        ->andReturn($payload);

    $this->evidenceSynchronizer
        ->shouldReceive('sync')
        ->once()
        ->andReturnUsing(function () use (&$order): void {
            $order[] = 'sync';
        });

    $this->submitAction
        ->shouldReceive('handle')
        ->once()
        ->andReturnUsing(function () use (&$order, $voucher): SubmitPayCodeClaimResultData {
            $order[] = 'submit';

            return successfulClaimSubmitResult($voucher);
        });

    $this->formFlowService
        ->shouldIgnoreMissing();

    $this->post(route('x-change.claim.submit', ['code' => $voucher->code]), [
        'flow_id' => 'flow-test',
    ]);

    expect($order)->toBe(['sync', 'submit']);
});

it('redirects back to claim start when claim submission fails', function (): void {
    Log::spy();

    $voucher = claimSubmitTestVoucher();

    $state = [
        'flow_id' => 'flow-test',
        'collected_data' => [],
    ];

    $payload = [
        'mobile' => '+639173011987',
        'country' => 'PH',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09173011987',
        'inputs' => [
            'mobile' => '+639173011987',
        ],
    ];

    $this->formFlowService
        ->shouldReceive('getFlowState')
        ->once()
        ->with('flow-test')
        ->andReturn($state);

    $this->payloadNormalizer
        ->shouldReceive('normalize')
        ->once()
        ->with($state['collected_data'])
        ->andReturn($payload);

    $this->evidenceSynchronizer
        ->shouldReceive('sync')
        ->once();

    $this->submitAction
        ->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Claim failed for test'));

    $this->formFlowService
        ->shouldNotReceive('clearFlow');

    $response = $this->post(route('x-change.claim.submit', ['code' => $voucher->code]), [
        'flow_id' => 'flow-test',
    ]);

    $response->assertRedirect(
        route('x-change.claim.start', [
            'code' => $voucher->code,
            'failed' => 1,
        ])
    );

    $errors = session('errors')?->getBag('default')->all() ?? [];

    expect($errors)->toContain('Claim failed for test');

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => $message === '[ClaimSubmitController] Claim failed'
            && ($context['voucher_code'] ?? null) === $voucher->code
            && ($context['error'] ?? null) === 'Claim failed for test'
        );
});

it('flashes descriptor-backed provisioning guidance when claim submission requires provider onboarding', function (): void {
    $voucher = claimSubmitTestVoucher();

    $state = [
        'flow_id' => 'flow-test',
        'collected_data' => [
            'wallet_info' => [
                'mobile' => '+639173011987',
            ],
        ],
    ];

    $payload = [
        'mobile' => '+639173011987',
        'country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'inputs' => [
            'mobile' => '+639173011987',
        ],
    ];

    $this->formFlowService
        ->shouldReceive('getFlowState')
        ->once()
        ->with('flow-test')
        ->andReturn($state);

    $this->payloadNormalizer
        ->shouldReceive('normalize')
        ->once()
        ->with($state['collected_data'])
        ->andReturn($payload);

    $this->evidenceSynchronizer
        ->shouldReceive('sync')
        ->once();

    $this->submitAction
        ->shouldReceive('handle')
        ->once()
        ->andThrow(new ProviderProvisioningRequired(
            'Claim requires provider bank-account provisioning before payout can continue.',
            [
                'provider' => 'netbank',
                'mode' => 'bank_account_link',
                'reason' => 'Bank account readiness is missing.',
                'readiness' => [
                    'topology' => 'ledger_pooled',
                ],
                'onboarding' => [
                    'reference' => 'onb-claim-123',
                ],
            ],
        ));

    $response = $this->post(route('x-change.claim.submit', ['code' => $voucher->code]), [
        'flow_id' => 'flow-test',
    ]);

    $response
        ->assertRedirect(route('x-change.claim.start', ['code' => $voucher->code, 'failed' => 1]))
        ->assertSessionHasErrors(['code'])
        ->assertSessionHas(CompiledClaimSessionKeys::PROVISIONING_REQUIREMENT, function (?array $requirement): bool {
            return data_get($requirement, 'provider') === 'netbank'
                && data_get($requirement, 'mode') === 'bank_account_link'
                && data_get($requirement, 'descriptor.title') === 'Add payout destination';
        });
});

it('passes onboarding reference from flow metadata into the resumed claim payload', function (): void {
    $voucher = claimSubmitTestVoucher();

    $state = [
        'flow_id' => 'flow-test',
        'instructions' => [
            'metadata' => [
                'onboarding_reference' => 'onb-claim-789',
            ],
        ],
        'collected_data' => [
            'wallet_info' => [
                'mobile' => '+639173011987',
            ],
        ],
    ];

    $payload = [
        'mobile' => '+639173011987',
        'country' => 'PH',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09173011987',
        'inputs' => [
            'mobile' => '+639173011987',
        ],
    ];

    $this->formFlowService
        ->shouldReceive('getFlowState')
        ->once()
        ->with('flow-test')
        ->andReturn($state);

    $this->payloadNormalizer
        ->shouldReceive('normalize')
        ->once()
        ->with($state['collected_data'])
        ->andReturn($payload);

    $this->evidenceSynchronizer
        ->shouldReceive('sync')
        ->once()
        ->with(Mockery::on(fn (array $actual): bool => data_get($actual, 'metadata.onboarding_reference') === 'onb-claim-789'
            && data_get($actual, 'onboarding.reference') === 'onb-claim-789'
        ));

    $this->submitAction
        ->shouldReceive('handle')
        ->once()
        ->withArgs(fn ($receivedVoucher, array $actual): bool => $receivedVoucher->is($voucher)
            && data_get($actual, 'metadata.onboarding_reference') === 'onb-claim-789'
            && data_get($actual, 'onboarding.reference') === 'onb-claim-789'
        )
        ->andReturn(successfulClaimSubmitResult($voucher));

    $this->formFlowService
        ->shouldReceive('clearFlow')
        ->once()
        ->with('flow-test');

    $response = $this->post(route('x-change.claim.submit', ['code' => $voucher->code]), [
        'flow_id' => 'flow-test',
    ]);

    $response->assertRedirect(route('x-change.claim.success', ['code' => $voucher->code]));
});

it('redirects to approval page when claim submission requires OTP approval', function (): void {
    $voucher = claimSubmitTestVoucher();

    $state = [
        'flow_id' => 'flow-test',
        'collected_data' => [
            'wallet_info' => [
                'mobile' => '+639173011987',
            ],
        ],
    ];

    $payload = [
        'mobile' => '+639173011987',
        'country' => 'PH',
        'bank_code' => 'GXI',
        'account_number' => '09173011987',
        'inputs' => [
            'mobile' => '+639173011987',
        ],
    ];

    $this->formFlowService
        ->shouldReceive('getFlowState')
        ->once()
        ->with('flow-test')
        ->andReturn($state);

    $this->payloadNormalizer
        ->shouldReceive('normalize')
        ->once()
        ->with($state['collected_data'])
        ->andReturn($payload);

    $this->evidenceSynchronizer
        ->shouldReceive('sync')
        ->once();

    $this->submitAction
        ->shouldReceive('handle')
        ->once()
        ->andReturn(new ClaimApprovalInitiationResultData(
            voucher_code: (string) $voucher->code,
            status: 'approval_required',
            requirements: ['otp'],
            actions: ['otp'],
            meta: [
                'provider' => 'paynamics',
                'authorization_type' => 'otp',
                'reference_id' => $voucher->code.'-09173011987',
                'otp_required' => true,
            ],
            messages: [
                'Payout OTP approval required.',
            ],
        ));

    $this->formFlowService
        ->shouldNotReceive('clearFlow');

    $response = $this->post(route('x-change.claim.submit', ['code' => $voucher->code]), [
        'flow_id' => 'flow-test',
    ]);

    $response->assertRedirect(route('x-change.claim.approval', [
        'code' => $voucher->code,
    ]));

    $compiled = session('compiled_claim_result');

    expect($compiled)->toMatchArray([
        'status' => 'approval_required',
        'voucher_code' => (string) $voucher->code,
        'messages' => [
            'Payout OTP approval required.',
        ],
    ])
        ->and($compiled['approval_metadata'])->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => $voucher->code.'-09173011987',
            'otp_required' => true,
        ]);
});
