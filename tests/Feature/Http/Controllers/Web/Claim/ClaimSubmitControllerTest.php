<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;
use LBHurtado\XChange\Support\Claim\ClaimEvidenceSynchronizer;
use LBHurtado\XChange\Support\Claim\FormFlowClaimPayloadNormalizer;

beforeEach(function (): void {
    $this->withoutMiddleware(ShareXChangeBranding::class);

    $this->formFlowService = Mockery::mock(FormFlowService::class);
    $this->submitAction = Mockery::mock(SubmitPayCodeClaim::class);
    $this->payloadNormalizer = Mockery::mock(FormFlowClaimPayloadNormalizer::class);
    $this->evidenceSynchronizer = Mockery::mock(ClaimEvidenceSynchronizer::class);

    $this->app->instance(FormFlowService::class, $this->formFlowService);
    $this->app->instance(SubmitPayCodeClaim::class, $this->submitAction);
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

    $this->evidenceSynchronizer
        ->shouldReceive('sync')
        ->once()
        ->with(Mockery::on(fn (array $actual): bool => $actual['mobile'] === '+639173011987'
        ));

    $this->submitAction
        ->shouldReceive('handle')
        ->once()
        ->with(Mockery::type(Voucher::class), Mockery::type('array'));

    $this->formFlowService
        ->shouldReceive('clearFlow')
        ->once()
        ->with('flow-test');

    $response = $this->post(route('x-change.claim.submit', ['code' => $voucher->code]), [
        'reference_id' => "claim-{$voucher->code}-123",
    ]);

    $response->assertRedirect(route('x-change.claim.success', ['code' => $voucher->code]));
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
        ->once();

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
        ->andReturnUsing(function () use (&$order): void {
            $order[] = 'submit';
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

    $response
        ->assertRedirect(route('x-change.claim.start', ['code' => $voucher->code]))
        ->assertSessionHasErrors(['error']);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => $message === '[ClaimSubmitController] Claim failed'
            && ($context['voucher_code'] ?? null) === $voucher->code
            && ($context['error'] ?? null) === 'Claim failed for test'
        );
});
