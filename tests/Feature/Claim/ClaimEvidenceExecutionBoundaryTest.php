<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimEvidence;

it('durably captures required evidence before invoking the execution driver', function (): void {
    Storage::fake('local');
    $voucher = evidenceBoundaryVoucher('EVID-BEFORE');
    $png = evidenceBoundaryPng();
    $payload = [
        'mobile' => '09173011987',
        'inputs' => [
            'name' => 'Amelia Hurtado',
            'selfie' => 'data:image/png;base64,'.base64_encode($png),
        ],
        '_meta' => ['idempotency_key' => 'claim-evidence-before-execution'],
    ];
    $executor = Mockery::mock(ClaimExecutorContract::class);
    $executor->shouldReceive('handle')
        ->once()
        ->withArgs(function (Voucher $receivedVoucher, array $receivedPayload) use ($voucher): bool {
            $preparedClaimId = data_get($receivedPayload, '_meta.prepared_claim_id');

            expect($receivedVoucher->is($voucher))->toBeTrue()
                ->and($preparedClaimId)->toBeInt()
                ->and(VoucherClaim::query()->findOrFail($preparedClaimId)->status)->toBe('prepared')
                ->and(VoucherClaimEvidence::query()->where('voucher_claim_id', $preparedClaimId)->count())->toBe(2);

            return true;
        })
        ->andReturn(new RedeemPayCodeResultData(
            voucher_code: $voucher->code,
            redeemed: true,
            status: 'redeemed',
            redeemer: ['mobile' => '09173011987', 'country' => 'PH'],
            bank_account: [],
            inputs: $payload['inputs'],
            disbursement: ['status' => 'requested'],
            messages: ['Claim completed.'],
        ));
    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')->once()->andReturn($executor);

    $result = (new SubmitPayCodeClaim(
        $factory,
        app(RecordVoucherClaim::class),
    ))->handle($voucher, $payload);

    $claim = VoucherClaim::query()->where('voucher_id', $voucher->getKey())->sole();

    expect($result->claimed)->toBeTrue()
        ->and($claim->status)->toBe('redeemed')
        ->and(data_get($claim->meta, 'evidence.execution_status'))->toBe('finalized')
        ->and($claim->evidence()->count())->toBe(2);
});

it('preserves captured evidence when the execution driver throws', function (): void {
    Storage::fake('local');
    $voucher = evidenceBoundaryVoucher('EVID-FAIL');
    $executor = Mockery::mock(ClaimExecutorContract::class);
    $executor->shouldReceive('handle')->once()->andThrow(new RuntimeException('Provider unavailable.'));
    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')->once()->andReturn($executor);

    expect(fn () => (new SubmitPayCodeClaim(
        $factory,
        app(RecordVoucherClaim::class),
    ))->handle($voucher, [
        'inputs' => [
            'name' => 'Amelia Hurtado',
            'selfie' => 'data:image/png;base64,'.base64_encode(evidenceBoundaryPng()),
        ],
        '_meta' => ['idempotency_key' => 'claim-evidence-provider-failure'],
    ]))->toThrow(RuntimeException::class, 'Provider unavailable.');

    $claim = VoucherClaim::query()->where('voucher_id', $voucher->getKey())->sole();

    expect($claim->status)->toBe('execution_failed')
        ->and($claim->failure_message)->toBe('Claim execution failed after evidence capture.')
        ->and($claim->evidence()->count())->toBe(2)
        ->and(data_get($claim->meta, 'evidence.execution_status'))->toBe('failed_before_finalization');
});

function evidenceBoundaryVoucher(string $code): Voucher
{
    return Voucher::query()->create([
        'code' => $code,
        'metadata' => [
            'instructions' => validVoucherInstructions(20, overrides: [
                'inputs' => ['fields' => ['name', 'selfie']],
                'metadata' => [
                    'claim_evidence' => [
                        'manifest_version' => 1,
                        'requirements' => ['name', 'selfie'],
                    ],
                ],
            ]),
        ],
        'state' => 'active',
    ]);
}

function evidenceBoundaryPng(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );
}
