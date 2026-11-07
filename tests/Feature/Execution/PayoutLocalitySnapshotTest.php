<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Contracts\PendingPayoutApproval;
use LBHurtado\EmiCore\Data\PayoutAddressData;
use LBHurtado\EmiCore\Data\PayoutPartyData;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\ClaimPayoutSubmission;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationStore;
use LBHurtado\XChange\Services\PayoutLocalitySnapshot;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;

it('encrypts a payout locality once and returns the same snapshot on replay', function (): void {
    $payout = DisbursementReconciliation::query()->create(['voucher_code' => 'LOCALITY-TEST', 'status' => 'intent']);
    $calls = 0;
    $snapshot = ['sender' => ['address' => ['address1' => 'Private issuer locality']], 'recipient' => ['address' => ['address1' => 'Private claimant locality']]];
    $service = app(PayoutLocalitySnapshot::class);
    $first = $service->remember($payout->id, function () use (&$calls, $snapshot): array {
        $calls++;

        return $snapshot;
    });
    $again = $service->remember($payout->id, function () use (&$calls): array {
        $calls++;

        return [];
    });
    $stored = DB::table('disbursement_reconciliations')->where('id', $payout->id)->value('payout_identity_snapshot');

    expect($first)->toBe($snapshot)->and($again)->toBe($snapshot)->and($calls)->toBe(1)
        ->and($stored)->not->toContain('Private issuer locality')->not->toContain('Private claimant locality')
        ->and($payout->fresh()->toArray())->not->toHaveKey('payout_identity_snapshot');
});

it('persists resolved party details encrypted before provider submission without recording them in the ordinary request', function (): void {
    $voucher = issueVoucher();
    $party = new PayoutPartyData('recipient', 'Private Recipient', new PayoutAddressData('Private Locality', 'Manila', 'PH', '1000', 'synthetic'));
    $request = new PayoutRequestData('locality-executor-test', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY', recipient: $party);
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldReceive('disburse')->once()->andReturnUsing(function (PayoutRequestData $submitted) use ($voucher): PayoutResultData {
        $row = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->sole();
        expect($row->payout_identity_snapshot['recipient']['address']['address1'])->toBe('Private Locality')
            ->and(json_encode($row->raw_request))->not->toContain('Private Locality')->not->toContain('Private Recipient')
            ->and($submitted->recipient?->address?->address1)->toBe('Private Locality');

        return new PayoutResultData('test-transaction', 'test-uuid', PayoutStatus::PENDING, 'netbank');
    });
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $status->shouldReceive('resolveFromGatewayResponse')->once()->andReturn('pending');
    $executor = new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status);
    $executor->execute($voucher, $request, 1);
    $row = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->sole();
    expect(DB::table('disbursement_reconciliations')->where('id', $row->id)->value('payout_identity_snapshot'))->not->toContain('Private Locality');
});

it('reuses a persisted snapshot even when the retry request contains no parties', function (): void {
    $voucher = issueVoucher();
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->id, 'voucher_code' => $voucher->code, 'claim_type' => 'withdraw',
        'provider_reference' => 'locality-retry-test', 'status' => 'failed',
        'payout_identity_snapshot' => [
            'recipient' => ['role' => 'recipient', 'name' => 'Recipient', 'address' => ['address1' => 'Original locality', 'city' => 'Manila', 'country' => 'PH', 'postal_code' => '1000', 'source' => 'synthetic']],
        ],
    ]);
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldReceive('disburse')->once()->andReturnUsing(function (PayoutRequestData $request): PayoutResultData {
        expect($request->recipient?->address?->address1)->toBe('Original locality');

        return new PayoutResultData('retry-transaction', 'retry-uuid', PayoutStatus::PENDING, 'netbank');
    });
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $status->shouldReceive('resolveFromGatewayResponse')->once()->andReturn('pending');
    $request = new PayoutRequestData('locality-retry-test', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    (new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status))->execute($voucher, $request, 1);
});

it('does not persist sensitive locality data from provider exception messages', function (): void {
    $voucher = issueVoucher();
    $party = new PayoutPartyData('recipient', 'Person', new PayoutAddressData('Private Barangay', 'Manila', 'PH', '1000', 'location'));
    $request = new PayoutRequestData('locality-error-test', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY', recipient: $party);
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldReceive('disburse')->once()->andThrow(new RuntimeException('Provider rejected Private Barangay'));
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $status->shouldReceive('resolveFromGatewayException')->once()->andReturn('unknown');
    $executor = new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status);
    expect(fn () => $executor->execute($voucher, $request, 1))->toThrow(RuntimeException::class);
    $row = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->sole();
    expect(json_encode($row->raw_response))->not->toContain('Private Barangay')
        ->and($row->error_message)->not->toContain('Private Barangay');
});

it('preserves a provider approval challenge without persisting its sensitive payload', function (): void {
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    $voucher = issueVoucher();
    $request = new PayoutRequestData('approval-challenge-test', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    $challenge = new class('Private OTP request') extends RuntimeException implements PendingPayoutApproval
    {
        public function provider(): string
        {
            return 'test-provider';
        }

        public function requestId(): ?string
        {
            return 'challenge-ref';
        }

        public function toApprovalMetadata(): array
        {
            return ['private' => 'Private OTP request'];
        }
    };
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldReceive('disburse')->once()->andThrow($challenge);
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $executor = new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status);
    expect(fn () => $executor->execute($voucher, $request, 1))->toThrow($challenge::class);
    $row = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->sole();
    expect($row->status)->toBe('approval_required')
        ->and(json_encode($row->raw_response))->not->toContain('Private OTP request');
});

it('allows only one approved continuation of a recorded provider challenge', function (): void {
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    $voucher = issueVoucher();
    $request = new PayoutRequestData('approval-continuation-test', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    $challenge = new class('OTP required') extends RuntimeException implements PendingPayoutApproval
    {
        public function provider(): string
        {
            return 'test-provider';
        }

        public function requestId(): ?string
        {
            return 'approval-continuation-test';
        }

        public function toApprovalMetadata(): array
        {
            return [];
        }
    };
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldReceive('disburse')->once()->ordered()->andThrow($challenge);
    $provider->shouldReceive('disburse')->once()->ordered()->andReturn(new PayoutResultData('approved-tx', 'uuid', PayoutStatus::PENDING, 'test-provider'));
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $status->shouldReceive('resolveFromGatewayResponse')->once()->andReturn('pending');
    $executor = new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status);
    expect(fn () => $executor->execute($voucher, $request, 1))->toThrow($challenge::class);
    $executor->execute($voucher, $request, 1, true);
    expect(fn () => $executor->execute($voucher, $request, 1, true))->toThrow(RuntimeException::class);
});

it('rejects an approved continuation with a changed destination', function (): void {
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    $voucher = issueVoucher();
    $request = new PayoutRequestData('approval-change-test', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    $challenge = new class('OTP required') extends RuntimeException implements PendingPayoutApproval
    {
        public function provider(): string
        {
            return 'test-provider';
        }

        public function requestId(): ?string
        {
            return 'approval-change-test';
        }

        public function toApprovalMetadata(): array
        {
            return [];
        }
    };
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldReceive('disburse')->once()->andThrow($challenge);
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $executor = new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status);
    expect(fn () => $executor->execute($voucher, $request, 1))->toThrow($challenge::class);
    $altered = new PayoutRequestData('approval-change-test', 100, '09171111111', 'GXCHPHM2XXX', 'INSTAPAY');
    expect(fn () => $executor->execute($voucher, $altered, 1, true))->toThrow(RuntimeException::class);
});

it('does not submit the same withdrawal payout twice', function (): void {
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    $voucher = issueVoucher();
    $party = new PayoutPartyData('recipient', 'Person', new PayoutAddressData('Barangay', 'Manila', 'PH', '1000', 'synthetic'));
    $request = new PayoutRequestData('locality-duplicate-test', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY', recipient: $party);
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldReceive('disburse')->once()->andReturn(new PayoutResultData('tx', 'uuid', PayoutStatus::PENDING, 'netbank'));
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $status->shouldReceive('resolveFromGatewayResponse')->once()->andReturn('pending');
    $executor = new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status);
    $executor->execute($voucher, $request, 1);
    expect(fn () => $executor->execute($voucher, $request, 1))->toThrow(RuntimeException::class, 'already been submitted');
});

it('does not submit a payout while its submission claim is inside an open transaction', function (): void {
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    $voucher = issueVoucher();
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldNotReceive('disburse');
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $executor = new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status);
    $request = new PayoutRequestData('locality-uncommitted-test', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    expect(fn () => DB::transaction(fn () => $executor->execute($voucher, $request, 1)))
        ->toThrow(RuntimeException::class, 'transaction');
});

it('does not resubmit an existing payout created before the submission guard', function (): void {
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    $voucher = issueVoucher();
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->id, 'voucher_code' => $voucher->code, 'claim_type' => 'withdraw',
        'provider_reference' => 'historical-payout', 'status' => 'pending', 'provider' => 'netbank',
    ]);
    $provider = Mockery::mock(PayoutProvider::class);
    $provider->shouldNotReceive('disburse');
    $status = Mockery::mock(DisbursementStatusResolverContract::class);
    $request = new PayoutRequestData('historical-payout', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    $executor = new WithdrawalDisbursementExecutor($provider, app(DefaultDisbursementReconciliationStore::class), $status);
    expect(fn () => $executor->execute($voucher, $request, 1))->toThrow(RuntimeException::class, 'already been submitted')
        ->and(DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->sole()->status)->toBe('pending');
});

it('serializes concurrent locality snapshot creation on a row-locking database', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }
    expect(DB::connection()->transactionLevel())->toBe(0);
    $payout = DisbursementReconciliation::query()->create(['voucher_code' => 'LOCALITY-RACE', 'status' => 'intent']);
    $children = [];
    $channels = [];
    $files = [];
    foreach (['first', 'second'] as $value) {
        $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $file = tempnam(sys_get_temp_dir(), 'locality-race-');
        if ($channel === false || $file === false) {
            throw new RuntimeException('Unable to prepare locality race.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to start locality race.');
        }
        if ($pid === 0) {
            fclose($channel[0]);
            DB::purge();
            fread($channel[1], 1);
            try {
                $snapshot = app(PayoutLocalitySnapshot::class)->remember($payout->id, fn (): array => ['value' => $value]);
                file_put_contents($file, $snapshot['value']);
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($file, $exception::class);
                exit(1);
            }
        }
        fclose($channel[1]);
        $children[] = $pid;
        $channels[] = $channel[0];
        $files[] = $file;
    }
    foreach ($channels as $channel) {
        fwrite($channel, '1');
        fclose($channel);
    }
    $codes = [];
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $codes[] = pcntl_wexitstatus($status);
    }
    $results = array_map(static fn (string $file): string => (string) file_get_contents($file), $files);
    foreach ($files as $file) {
        unlink($file);
    }
    DB::purge();
    expect($codes)->toBe([0, 0])->and($results[0])->toBe($results[1])
        ->and($payout->fresh()->payout_identity_snapshot['value'])->toBe($results[0]);
});

it('allows only one concurrent provider submission claim', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }
    $voucher = issueVoucher();
    $children = [];
    $channels = [];
    $files = [];
    for ($index = 0; $index < 2; $index++) {
        $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $file = tempnam(sys_get_temp_dir(), 'payout-claim-race-');
        if ($channel === false || $file === false) {
            throw new RuntimeException('Unable to prepare submission race.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to start submission race.');
        }
        if ($pid === 0) {
            fclose($channel[0]);
            DB::purge();
            fread($channel[1], 1);
            try {
                app(ClaimPayoutSubmission::class)->claim($voucher, 'race-reference');
                file_put_contents($file, 'claimed');
            } catch (RuntimeException $exception) {
                file_put_contents($file, str_contains($exception->getMessage(), 'already been submitted') ? 'blocked' : 'error');
            }
            exit(0);
        }
        fclose($channel[1]);
        $children[] = $pid;
        $channels[] = $channel[0];
        $files[] = $file;
    }
    foreach ($channels as $channel) {
        fwrite($channel, '1');
        fclose($channel);
    }
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        expect(pcntl_wexitstatus($status))->toBe(0);
    }
    $results = array_map(static fn (string $file): string => (string) file_get_contents($file), $files);
    foreach ($files as $file) {
        unlink($file);
    }
    DB::purge();
    sort($results);
    expect($results)->toBe(['blocked', 'claimed'])
        ->and(DB::table('x_change_payout_submission_claims')->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('allows only one concurrent continuation of an approved payout', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }
    $voucher = issueVoucher();
    $request = new PayoutRequestData('approval-race', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    $facts = $request->toAuditArray();
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->id, 'voucher_code' => $voucher->code, 'claim_type' => 'withdraw',
        'provider_reference' => $request->reference, 'status' => 'approval_required', 'raw_request' => $facts,
    ]);
    DB::table('x_change_payout_submission_claims')->insert([
        'voucher_id' => $voucher->id, 'reference_hash' => hash('sha256', $request->reference),
        'claim_type' => 'withdraw', 'created_at' => now(),
    ]);
    $children = $channels = $files = [];
    for ($index = 0; $index < 2; $index++) {
        $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $file = tempnam(sys_get_temp_dir(), 'approval-race-');
        if ($channel === false || $file === false) {
            throw new RuntimeException('Unable to prepare approval race.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to start approval race.');
        }
        if ($pid === 0) {
            fclose($channel[0]);
            DB::purge();
            fread($channel[1], 1);
            try {
                app(ClaimPayoutSubmission::class)->claim($voucher, $request->reference, true, $facts);
                file_put_contents($file, 'claimed');
            } catch (RuntimeException $exception) {
                file_put_contents($file, str_contains($exception->getMessage(), 'already been submitted') ? 'blocked' : 'error');
            }
            exit(0);
        }
        fclose($channel[1]);
        $children[] = $pid;
        $channels[] = $channel[0];
        $files[] = $file;
    }
    foreach ($channels as $channel) {
        fwrite($channel, '1');
        fclose($channel);
    }
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        expect(pcntl_wexitstatus($status))->toBe(0);
    }
    $results = array_map(static fn (string $file): string => (string) file_get_contents($file), $files);
    foreach ($files as $file) {
        unlink($file);
    }
    DB::purge();
    sort($results);
    expect($results)->toBe(['blocked', 'claimed']);
});
