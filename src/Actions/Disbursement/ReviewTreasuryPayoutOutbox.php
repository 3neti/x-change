<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Disbursement;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use RuntimeException;

final readonly class ReviewTreasuryPayoutOutbox
{
    public function __construct(private DisbursementReconciliationContract $reconciliation) {}

    public function recordInvestigation(Authenticatable $actor, int $id, string $reason): void
    {
        Gate::forUser($actor)->authorize('x-change.recovery.manage');
        if (mb_strlen(trim($reason)) < 8 || mb_strlen($reason) > 2000) {
            throw new RuntimeException('A review reason is required.');
        }
        DB::transaction(function () use ($actor, $id, $reason): void {
            $outbox = DB::table('x_change_treasury_payout_outbox')->where('id', $id)->lockForUpdate()->first();
            if ($outbox === null || $outbox->status !== 'review') {
                throw new RuntimeException('The treasury payout is not awaiting review.');
            }
            $reconciliationId = DisbursementReconciliation::query()->where('voucher_id', $outbox->voucher_id)
                ->where('claim_type', 'withdraw')->latest('id')->value('id');
            DB::table('x_change_treasury_payout_review_audits')->insert([
                'outbox_id' => $id, 'reconciliation_id' => $reconciliationId,
                'actor_type' => $actor::class, 'actor_id' => (string) $actor->getAuthIdentifier(),
                'outcome' => 'investigating', 'reason_ciphertext' => Crypt::encryptString(trim($reason)), 'created_at' => now(),
            ]);
        }, 5);
    }

    public function handle(Authenticatable $actor, int $id, string $reason): void
    {
        Gate::forUser($actor)->authorize('x-change.recovery.manage');
        if (mb_strlen(trim($reason)) < 8 || mb_strlen($reason) > 2000) {
            throw new RuntimeException('A review reason is required.');
        }
        Cache::lock('x-change:treasury-outbox-review:'.$id, 120)->block(5, function () use ($actor, $id, $reason): void {
            $outbox = DB::table('x_change_treasury_payout_outbox')->where('id', $id)->where('status', 'review')->first();
            if ($outbox === null) {
                throw new RuntimeException('The treasury payout is not awaiting review.');
            }
            $payout = DisbursementReconciliation::query()->where('voucher_id', $outbox->voucher_id)
                ->where('claim_type', 'withdraw')->latest('id')->first();
            if (! $payout instanceof DisbursementReconciliation || blank($payout->provider_transaction_id)) {
                throw new RuntimeException('Provider transaction evidence is required before checking this payout.');
            }
            $this->reconciliation->reconcile($payout);
            DB::transaction(function () use ($actor, $id, $reason, $payout): void {
                $row = DB::table('x_change_treasury_payout_outbox')->where('id', $id)->lockForUpdate()->first();
                if ($row === null || $row->status !== 'review') {
                    throw new RuntimeException('The treasury payout review has changed.');
                }
                $fresh = DisbursementReconciliation::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
                $verified = in_array($fresh->status, ['succeeded', 'failed'], true)
                    && ! $fresh->needs_review && filled($fresh->provider_transaction_id);
                DB::table('x_change_treasury_payout_review_audits')->insert([
                    'outbox_id' => $id, 'reconciliation_id' => $fresh->id,
                    'actor_type' => $actor::class, 'actor_id' => (string) $actor->getAuthIdentifier(),
                    'outcome' => $verified ? $fresh->status : 'unknown',
                    'reason_ciphertext' => Crypt::encryptString(trim($reason)), 'created_at' => now(),
                ]);
                if ($verified) {
                    DB::table('x_change_treasury_payout_outbox')->where('id', $id)->update(['status' => 'completed', 'updated_at' => now()]);
                }
            }, 5);
        });
    }
}
