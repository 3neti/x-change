<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use RuntimeException;

final class ClaimPayoutSubmission
{
    /** @param array<string, mixed> $facts */
    public function claim(Voucher $voucher, string $reference, bool $approvedContinuation = false, array $facts = []): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Payout submission requires a committed transaction.');
        }
        if ($voucher->getKey() === null || trim($reference) === '') {
            throw new RuntimeException('A durable voucher and payout reference are required.');
        }

        $existing = DB::table('disbursement_reconciliations')
            ->where('voucher_id', $voucher->getKey())
            ->where('provider_reference', $reference)
            ->where('claim_type', 'withdraw')
            ->first();
        $initialClaimed = DB::table('x_change_payout_submission_claims')
            ->where('voucher_id', $voucher->getKey())->where('reference_hash', hash('sha256', $reference))
            ->where('claim_type', 'withdraw')->exists();
        if (($approvedContinuation && (! $existing || $existing->status !== 'approval_required' || ! $initialClaimed))
            || (! $approvedContinuation && $existing)) {
            throw new RuntimeException('This payout has already been submitted or requires review.');
        }
        if ($approvedContinuation) {
            $original = json_decode((string) $existing->raw_request, true);
            if (! is_array($original) || ! is_numeric($facts['amount'] ?? null) || ! is_numeric($original['amount'] ?? null)
                || (int) round((float) $facts['amount'] * 100) !== (int) round((float) $original['amount'] * 100)) {
                throw new RuntimeException('The approved payout no longer matches the original request.');
            }
            foreach (['reference', 'account_number', 'bank_code', 'settlement_rail', 'currency'] as $field) {
                if (! is_string($facts[$field] ?? null) || ! is_string($original[$field] ?? null)
                    || ! hash_equals($original[$field], $facts[$field])) {
                    throw new RuntimeException('The approved payout no longer matches the original request.');
                }
            }
        }

        $inserted = DB::table('x_change_payout_submission_claims')->insertOrIgnore([
            'voucher_id' => $voucher->getKey(),
            'reference_hash' => hash('sha256', $reference),
            'claim_type' => $approvedContinuation ? 'approval' : 'withdraw',
            'created_at' => now(),
        ]);
        if ($inserted !== 1) {
            throw new RuntimeException('This payout has already been submitted or requires review.');
        }
    }
}
