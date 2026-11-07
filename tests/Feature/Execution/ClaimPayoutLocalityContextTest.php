<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Services\ClaimPayoutLocalityContext;

it('persists coarse claim locality encrypted and reuses it for the same claim', function (): void {
    $voucher = issueVoucher();
    $service = app(ClaimPayoutLocalityContext::class);
    $first = ['ip_locality' => ['city' => 'Private City', 'region' => 'Private Region'], 'captured' => null];
    $service->remember($voucher, 'claim-key', $first);
    $service->remember($voucher, 'claim-key', ['ip_locality' => ['city' => 'Other City', 'region' => null], 'captured' => null]);

    $row = DB::table('x_change_claim_payout_locality_contexts')->sole();
    expect($row->evidence)->not->toContain('Private City')->not->toContain('Private Region')
        ->and($row->claim_key_hash)->not->toBe('claim-key')
        ->and($service->find($voucher, 'claim-key'))->toBe($first)
        ->and($service->find($voucher, 'another-claim'))->toBeNull();
});

it('does not accept raw IP addresses or arbitrary claim payload fields in its evidence', function (): void {
    $voucher = issueVoucher();
    expect(fn () => app(ClaimPayoutLocalityContext::class)->remember($voucher, 'claim-key', [
        'ip_locality' => ['city' => 'Manila', 'region' => null],
        'captured' => null,
        'ip' => '8.8.8.8',
    ]))->toThrow(InvalidArgumentException::class);
});
