<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Models\CompletionPayCodeIssuance;
use LBHurtado\XChange\Models\PolicyCompletionOutcome;

final class CompletionClaimReceipt
{
    public const KEY = 'x-change.completion-claim-receipts';

    public function pendingIssuanceId(Voucher $voucher): ?int
    {
        if (app(ClaimWorkflowResolverContract::class)->resolve($voucher)->key !== 'campaign.coverage-completion.v1') {
            return null;
        }

        return CompletionPayCodeIssuance::query()->where('voucher_id', $voucher->getKey())
            ->whereDoesntHave('evidenceProjection')->value('id');
    }

    public function remember(Voucher $voucher, mixed $result, ?int $pendingIssuanceId, string $idempotencyKey): void
    {
        if ($pendingIssuanceId === null || $idempotencyKey === '' || ! $result instanceof SubmitPayCodeClaimResultData
            || ! $result->claimed || ! in_array($result->status, ['redeemed', 'succeeded'], true)
            || $result->voucher_code !== (string) $voucher->code) {
            return;
        }

        $issuance = CompletionPayCodeIssuance::query()->with('evidenceProjection.claim')
            ->whereKey($pendingIssuanceId)->where('voucher_id', $voucher->getKey())->first();
        $projection = $issuance?->evidenceProjection;
        $claim = $projection?->claim;
        if ($projection === null || $claim === null || $claim->status !== 'redeemed'
            || $projection->projected_at === null || $claim->completed_at === null
            || ! hash_equals($idempotencyKey, (string) $claim->idempotency_key)
            || (string) $claim->voucher_id !== (string) $voucher->getKey()
            || (string) $projection->envelope_id !== (string) $issuance->envelope_id) {
            return;
        }

        $receipts = array_filter((array) session()->get(self::KEY, []),
            static fn (mixed $receipt): bool => is_array($receipt) && ($receipt['expires_at'] ?? 0) > now()->timestamp);
        unset($receipts[(string) $voucher->getKey()]);
        $receipts[(string) $voucher->getKey()] = [
            'issuance_id' => $issuance->getKey(),
            'projection_id' => $projection->getKey(),
            'claim_id' => $claim->getKey(),
            'expires_at' => now()->addMinutes(30)->timestamp,
        ];
        session()->put(self::KEY, array_slice($receipts, -10, null, true));
    }

    public function outcome(Voucher $voucher): ?PolicyCompletionOutcome
    {
        $receipt = ((array) session()->get(self::KEY, []))[(string) $voucher->getKey()] ?? null;
        if (! is_array($receipt) || ($receipt['expires_at'] ?? 0) <= now()->timestamp) {
            return null;
        }

        $issuance = CompletionPayCodeIssuance::query()->with(['evidenceProjection.claim', 'evidenceProjection.policyCompletionRequest.outcome'])
            ->whereKey($receipt['issuance_id'] ?? null)->where('voucher_id', $voucher->getKey())->first();
        $projection = $issuance?->evidenceProjection;
        if ($projection === null || (string) $projection->getKey() !== (string) ($receipt['projection_id'] ?? '')
            || (string) $projection->voucher_claim_id !== (string) ($receipt['claim_id'] ?? '')
            || (string) $projection->claim?->voucher_id !== (string) $voucher->getKey()
            || $projection->claim?->status !== 'redeemed'
            || (string) $projection->envelope_id !== (string) $issuance->envelope_id) {
            return null;
        }

        return $projection->policyCompletionRequest?->outcome;
    }
}
