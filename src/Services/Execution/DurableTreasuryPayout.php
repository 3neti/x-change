<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Contracts\PendingPayoutApproval;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\ClaimPayoutLocalityScope;
use RuntimeException;
use Throwable;

final readonly class DurableTreasuryPayout
{
    public function __construct(private TreasuryBackedPayCodeDisbursement $payouts) {}

    public function enqueue(Voucher $voucher, ?string $claimKey, string $payoutKind = 'treasury'): int
    {
        if (DB::transactionLevel() === 0 || $voucher->getKey() === null || ! in_array($payoutKind, ['treasury', 'legacy'], true)) {
            throw new RuntimeException('Treasury payout must be enqueued in the redemption transaction.');
        }
        DB::table('x_change_treasury_payout_outbox')->insertOrIgnore([
            'voucher_id' => $voucher->getKey(),
            'payout_kind' => $payoutKind,
            'claim_key_ciphertext' => $claimKey !== null ? Crypt::encryptString($claimKey) : null,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())->value('id');
    }

    public function deliverPending(int $limit): int
    {
        $limit = max(1, min($limit, 500));
        $interrupted = DB::table('x_change_treasury_payout_outbox as o')
            ->where('o.status', 'approval_required')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('x_change_payout_submission_claims as c')
                ->whereColumn('c.voucher_id', 'o.voucher_id')->where('c.claim_type', 'approval')
                ->where('c.created_at', '<=', now()->subMinutes(5)))
            ->orderBy('o.id')->limit($limit)->pluck('o.id');
        if ($interrupted->isNotEmpty()) {
            DB::table('x_change_treasury_payout_outbox')->whereIn('id', $interrupted)
                ->where('status', 'approval_required')->update(['status' => 'review', 'updated_at' => now()]);
        }
        $ids = DB::table('x_change_treasury_payout_outbox')
            ->where(fn ($query) => $query->where('status', 'pending')->orWhere(fn ($query) => $query->where('status', 'processing')->where('lease_until', '<', now())))
            ->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            $this->deliver((int) $id);
        }

        return $ids->count() + $interrupted->count();
    }

    public function deliver(int $id): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Treasury payout delivery requires a committed transaction.');
        }
        $token = (string) Str::uuid();
        $claimed = DB::table('x_change_treasury_payout_outbox')->where('id', $id)
            ->where(fn ($query) => $query->where('status', 'pending')->orWhere(fn ($query) => $query->where('status', 'processing')->where('lease_until', '<', now())))
            ->update(['status' => 'processing', 'lease_token' => $token, 'lease_until' => now()->addMinutes(5), 'updated_at' => now()]);
        if ($claimed !== 1) {
            return;
        }
        $row = DB::table('x_change_treasury_payout_outbox')->where('id', $id)->first();
        $scope = app(ClaimPayoutLocalityScope::class);
        $previous = $scope->get();
        $status = 'pending';
        $approval = null;
        try {
            $voucher = Voucher::query()->findOrFail($row->voucher_id);
            $scope->set(is_string($row->claim_key_ciphertext) ? Crypt::decryptString($row->claim_key_ciphertext) : null);
            if (! $this->hasExistingSubmission($voucher)) {
                if ($row->payout_kind === 'legacy') {
                    $voucher->redeemer = $voucher->redeemers()->first();
                    $provider = app()->makeWith(GuardedLegacyPayoutProvider::class, ['voucher' => $voucher]);
                    $previousApprovalOption = config('voucher.payout.bubble_pending_approval', false);
                    config()->set('voucher.payout.bubble_pending_approval', true);
                    try {
                        (new DisburseCash($provider))->handle($voucher, fn (Voucher $processed): Voucher => $processed);
                    } finally {
                        config()->set('voucher.payout.bubble_pending_approval', $previousApprovalOption);
                    }
                } else {
                    $this->payouts->handle($voucher);
                }
            }
            $status = $this->submissionStatus($voucher->fresh() ?? $voucher, $row->payout_kind);
        } catch (PendingPayoutApproval $exception) {
            $status = 'review';
            if (isset($voucher) && $row->payout_kind === 'legacy') {
                $record = DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())
                    ->where('claim_type', 'withdraw')->where('provider_reference', $exception->requestId())->firstOrFail();
                $metadata = (array) $voucher->metadata;
                data_set($metadata, 'disbursement', [
                    'gateway' => $exception->provider(), 'reference_id' => $exception->requestId(),
                    'status' => 'pending', 'amount' => $record->amount, 'currency' => $record->currency,
                    'settlement_rail' => $record->settlement_rail,
                    'recipient_identifier' => data_get($record->raw_request, 'account_number'),
                    'requires_reconciliation' => true, 'error' => 'Payout approval OTP pending.',
                    'metadata' => ['bank_code' => $record->bank_code],
                ]);
                $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
            }
            $approval = Crypt::encryptString(json_encode([
                'provider' => $exception->provider(), 'reference_id' => $exception->requestId(),
                'metadata' => $exception->toApprovalMetadata(),
            ], JSON_THROW_ON_ERROR));
            $status = 'approval_required';
        } catch (Throwable $exception) {
            Log::warning('Payout outbox delivery failed.', ['outbox_id' => $id, 'exception' => $exception::class]);
            $voucher = Voucher::query()->find($row->voucher_id);
            $status = ! $voucher instanceof Voucher || $this->hasExistingSubmission($voucher) ? 'review' : 'pending';
        } finally {
            $scope->set($previous);
            DB::table('x_change_treasury_payout_outbox')->where('id', $id)->where('lease_token', $token)
                ->update(['status' => $status, 'approval_ciphertext' => $approval, 'lease_token' => null, 'lease_until' => null, 'updated_at' => now()]);
        }
    }

    /** @return array<string, mixed>|null */
    public function approvalFor(Voucher $voucher, ?string $claimKey): ?array
    {
        if ($claimKey === null || $claimKey === '') {
            return null;
        }
        $row = DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())
            ->where('status', 'approval_required')->first();
        if ($row === null || ! is_string($row->claim_key_ciphertext) || ! is_string($row->approval_ciphertext)
            || ! hash_equals(Crypt::decryptString($row->claim_key_ciphertext), $claimKey)) {
            return null;
        }

        return json_decode(Crypt::decryptString($row->approval_ciphertext), true, flags: JSON_THROW_ON_ERROR);
    }

    public function isPendingFor(Voucher $voucher, string $claimKey): bool
    {
        $row = DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())
            ->whereIn('status', ['pending', 'processing'])->first();

        return $row !== null && is_string($row->claim_key_ciphertext)
            && hash_equals(Crypt::decryptString($row->claim_key_ciphertext), $claimKey);
    }

    public function assertApprovalSession(Voucher $voucher, ?string $claimKey): void
    {
        if (! config('x-change.payout_locality.submission_guard_enabled', false)) {
            return;
        }
        $pending = DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())
            ->where('status', 'approval_required')->exists();
        if ($pending && $this->approvalFor($voucher, $claimKey) === null) {
            abort(403);
        }
    }

    public function completeApproval(Voucher $voucher, string $claimKey, string $status): void
    {
        DB::transaction(function () use ($voucher, $claimKey, $status): void {
            $row = DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())->lockForUpdate()->first();
            if ($row === null || $row->status !== 'approval_required' || ! is_string($row->claim_key_ciphertext)
                || ! hash_equals(Crypt::decryptString($row->claim_key_ciphertext), $claimKey)) {
                throw new RuntimeException('The treasury approval is no longer available.');
            }
            DB::table('x_change_treasury_payout_outbox')->where('id', $row->id)->update([
                'status' => $status === 'unknown' ? 'review' : 'completed',
                'approval_ciphertext' => null, 'updated_at' => now(),
            ]);
        }, 5);
    }

    private function hasExistingSubmission(Voucher $voucher): bool
    {
        return DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())->exists()
            || DB::table('x_change_payout_submission_claims')->where('voucher_id', $voucher->getKey())->exists();
    }

    private function submissionStatus(Voucher $voucher, string $payoutKind): string
    {
        if ($payoutKind === 'legacy' && $this->hasExistingSubmission($voucher)
            && ! filled(data_get($voucher->metadata, 'disbursement.cash_withdrawal_uuid'))) {
            return 'review';
        }
        $payout = DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())->latest('id')->first();
        if (! $payout instanceof DisbursementReconciliation || in_array($payout->status, ['intent', 'unknown'], true)) {
            return $this->hasExistingSubmission($voucher) ? 'review' : 'pending';
        }

        return 'completed';
    }
}
