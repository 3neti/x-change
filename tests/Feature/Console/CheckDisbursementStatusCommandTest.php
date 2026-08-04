<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\DefaultDisbursementStatusFetcherService;
use LBHurtado\XChange\Services\DefaultDisbursementStatusResolverService;

it('reports operator guidance for trusted provider rejections', function () {
    $voucher = issueVoucher(validVoucherInstructions(20));

    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => (string) $voucher->code,
        'claim_type' => 'redeem',
        'provider' => 'netbank',
        'provider_reference' => $voucher->code.'-09173011987',
        'provider_transaction_id' => '407906626',
        'transaction_uuid' => null,
        'status' => 'pending',
        'internal_status' => 'recorded',
        'amount' => 20.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '******1987',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
        'needs_review' => false,
    ]);

    $metadata = [
        'operation_id' => '407906626',
        'status' => 'Rejected',
        'settlement_rail' => 'INSTAPAY',
        'rejection_reason' => 'AC06 (Blocked account)',
        'status_details' => [
            ['status' => 'Rejected', 'message' => 'AC06 (Blocked account)', 'updated' => '2026-07-30T03:19:03Z'],
        ],
    ];

    $fetcher = Mockery::mock(DefaultDisbursementStatusFetcherService::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->andReturn([
            'status' => 'failed',
            'metadata' => $metadata,
        ]);

    $resolver = Mockery::mock(DefaultDisbursementStatusResolverService::class);
    $resolver->shouldReceive('resolveFromFetchedStatus')
        ->once()
        ->with('failed', $metadata)
        ->andReturn('failed');

    $this->app->instance(DefaultDisbursementStatusFetcherService::class, $fetcher);
    $this->app->instance(DefaultDisbursementStatusResolverService::class, $resolver);

    $exitCode = Artisan::call('xchange:disbursement:check', [
        'code' => $voucher->code,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['resolved_status'])->toBe('failed')
        ->and($payload['rejection_reason'])->toBe('AC06 (Blocked account)')
        ->and($payload['status_details'][0]['message'])->toBe('AC06 (Blocked account)')
        ->and($payload['operator_guidance']['action'])->toBe('correct_destination_and_retry')
        ->and($payload['operator_guidance']['message'])->toBe('Provider rejected the payout: AC06 (Blocked account). Correct the destination and retry the payout under the same Pay Code.');
});
