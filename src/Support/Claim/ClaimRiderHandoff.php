<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Symfony\Component\HttpFoundation\Response;

final class ClaimRiderHandoff
{
    private const SESSION_KEY = 'x-change.claim-rider-handoff';

    public function __construct(private readonly ExecutionJournalRecorder $journal) {}

    /** Payout settlement is reconciled separately after the claim is accepted. */
    public function prepare(Voucher $voucher, mixed $result, string $idempotencyKey): bool
    {
        if (! $result instanceof SubmitPayCodeClaimResultData
            || ! $result->claimed
            || ! in_array($result->status, ['succeeded', 'redeemed', 'withdrawn', 'pending_review'], true)
            || $result->voucher_code !== (string) $voucher->code
            || $idempotencyKey === ''
            || blank(data_get($voucher->instructions?->toArray(), 'rider.url'))
            || app(ClaimWorkflowResolverContract::class)->resolve($voucher)->key !== 'disbursement.v1') {
            return false;
        }

        $claim = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->whereIn('status', ['succeeded', 'redeemed', 'withdrawn', 'pending_review'])
            ->latest('id')
            ->first();

        if (! $claim instanceof VoucherClaim || $claim->completed_at === null) {
            return false;
        }

        session()->put(self::SESSION_KEY, [
            'voucher_id' => $voucher->getKey(),
            'claim_id' => $claim->getKey(),
            'session_id' => session()->getId(),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        try {
            $this->record($voucher, $claim, 'rider.handoff.prepared');
        } catch (\Throwable $exception) {
            Log::warning('Claim rider handoff preparation could not be journaled', [
                'voucher_id' => $voucher->getKey(),
                'claim_id' => $claim->getKey(),
                'exception' => $exception::class,
            ]);
            session()->flash('claim_rider_handoff_failed', true);

            return false;
        }

        return true;
    }

    public function claim(Voucher $voucher): ?VoucherClaim
    {
        $receipt = session()->get(self::SESSION_KEY);

        if (! is_array($receipt)
            || (string) ($receipt['voucher_id'] ?? '') !== (string) $voucher->getKey()
            || ! hash_equals((string) ($receipt['session_id'] ?? ''), session()->getId())
            || (int) ($receipt['expires_at'] ?? 0) <= now()->timestamp) {
            return null;
        }

        return VoucherClaim::query()
            ->whereKey($receipt['claim_id'] ?? null)
            ->where('voucher_id', $voucher->getKey())
            ->whereNotNull('completed_at')
            ->first();
    }

    /** A later provider outcome does not revoke a completed claim's rider handoff. */
    public function recordRedirect(Voucher $voucher, VoucherClaim $claim): bool
    {
        if ($claim->completed_at === null) {
            return false;
        }

        try {
            $this->record($voucher, $claim, 'rider.redirect.started', (string) Str::uuid());

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Claim rider redirect could not be journaled', [
                'voucher_id' => $voucher->getKey(),
                'claim_id' => $claim->getKey(),
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    public function navigate(Request $request, Voucher $voucher): Response|RedirectResponse
    {
        $url = route('x-change.claim.redirect', ['code' => $voucher->code]);

        return $request->header('X-Inertia') === 'true'
            ? Inertia::location($url)
            : redirect()->to($url);
    }

    private function record(Voucher $voucher, VoucherClaim $claim, string $event, ?string $attempt = null): void
    {
        $this->journal->record(new ExecutionJournalEntryData(
            eventType: $event,
            occurredAt: $attempt === null ? CarbonImmutable::parse($claim->completed_at) : CarbonImmutable::now(),
            actor: new ExecutionActorData(id: (string) $claim->getKey(), type: 'voucher_claimant_session'),
            subject: new ExecutionSubjectData(
                id: (string) $voucher->getKey(),
                type: 'voucher',
                display: (string) $voucher->code,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'voucher-claim:'.$claim->getKey(),
                executionId: (string) $claim->getKey(),
                externalReference: $attempt,
            ),
            idempotencyKey: 'x-change:claim-rider:'.$event.':'.$claim->getKey().($attempt === null ? '' : ':'.$attempt),
            payload: [
                'claim_id' => $claim->getKey(),
                'voucher_code' => (string) $voucher->code,
                'status' => $claim->status,
                'destination_fingerprint' => hash('sha256', (string) data_get($voucher->instructions?->toArray(), 'rider.url')),
                'attempt_reference' => $attempt,
            ],
            metadata: ['schema' => 'x-change.claim-rider-handoff.v1'],
        ));
    }
}
