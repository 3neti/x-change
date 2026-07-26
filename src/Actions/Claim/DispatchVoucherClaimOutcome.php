<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Exceptions\VoucherClaimOutcomeConflict;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimOutcomeSelection;
use LBHurtado\XChange\Services\Claim\VoucherClaimantReference;
use LBHurtado\XChange\Services\Claim\VoucherClaimOutcomeRegistry;
use LBHurtado\XChange\Services\Claim\VoucherClaimPolicyResolver;
use LBHurtado\XChange\Services\Funding\AccountFundingPayCodeJournal;

final readonly class DispatchVoucherClaimOutcome
{
    public function __construct(
        private VoucherClaimPolicyResolver $policies,
        private VoucherClaimOutcomeRegistry $registry,
        private VoucherClaimantReference $claimantReferences,
        private AccountFundingPayCodeJournal $journal,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        Voucher $voucher,
        ?string $requestedOutcome,
        array $payload,
        ?Authenticatable $claimant = null,
    ): mixed {
        $result = DB::transaction(function () use (
            $voucher,
            $requestedOutcome,
            $payload,
            $claimant,
        ): mixed {
            $locked = Voucher::query()
                ->lockForUpdate()
                ->findOrFail($voucher->getKey());
            $policy = $this->policies->resolve($locked);
            $outcome = $requestedOutcome ?? $policy->defaultOutcome;

            if ($outcome === null || ! $policy->permits($outcome)) {
                throw new VoucherClaimOutcomeConflict(
                    'The selected Voucher claim outcome is unavailable.',
                );
            }

            if (
                $policy->selection === 'server'
                && $policy->defaultOutcome !== null
                && $outcome !== $policy->defaultOutcome
            ) {
                throw new VoucherClaimOutcomeConflict(
                    'This Voucher has a server-selected claim outcome.',
                );
            }

            $claimantReference = $this->claimantReferences->for($claimant);
            $this->assertClaimantBinding(
                $policy->claimantBinding,
                $claimantReference,
            );
            $selection = VoucherClaimOutcomeSelection::query()
                ->where('voucher_id', $locked->getKey())
                ->lockForUpdate()
                ->first();

            if ($selection instanceof VoucherClaimOutcomeSelection) {
                $this->assertReplayMatches(
                    $selection,
                    $outcome,
                    $claimantReference,
                );
            } else {
                VoucherClaimOutcomeSelection::query()->create([
                    'voucher_id' => $locked->getKey(),
                    'outcome_key' => $outcome,
                    'policy_profile' => $policy->profile,
                    'selection_mode' => $policy->selection,
                    'claimant_type' => $claimant instanceof Model
                        ? $claimant::class
                        : null,
                    'claimant_id' => $claimant instanceof Model
                        ? (string) $claimant->getKey()
                        : null,
                    'claimant_reference' => $claimantReference,
                    'selected_at' => now(),
                    'metadata' => [
                        'consumption' => $policy->consumption,
                        'legacy_policy' => $policy->legacy,
                    ],
                ]);
            }

            return $this->registry
                ->handler($outcome)
                ->execute($locked, $payload, $claimant);
        }, attempts: 5);

        if (
            $result instanceof VoucherClaim
            && $result->settlement_mode === 'account_funding'
        ) {
            $selection = VoucherClaimOutcomeSelection::query()
                ->where('voucher_id', $voucher->getKey())
                ->firstOrFail();

            DB::afterCommit(function () use ($result, $selection): void {
                $this->journal->recordOutcomeSelected($selection);
                $this->journal->recordApplied($result);
            });
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $binding
     */
    private function assertClaimantBinding(
        ?array $binding,
        ?string $claimantReference,
    ): void {
        if (($binding['mode'] ?? 'unbound') !== 'recipient') {
            return;
        }

        if (
            $claimantReference === null
            || ! hash_equals(
                (string) ($binding['reference'] ?? ''),
                $claimantReference,
            )
        ) {
            throw new VoucherClaimOutcomeConflict(
                'This Voucher belongs to another recipient.',
            );
        }
    }

    private function assertReplayMatches(
        VoucherClaimOutcomeSelection $selection,
        string $outcome,
        ?string $claimantReference,
    ): void {
        if ($selection->outcome_key !== $outcome) {
            throw new VoucherClaimOutcomeConflict(
                'This Voucher has already selected a different claim outcome.',
            );
        }

        if (
            $selection->claimant_reference !== null
            && (
                $claimantReference === null
                || ! hash_equals(
                    $selection->claimant_reference,
                    $claimantReference,
                )
            )
        ) {
            throw new VoucherClaimOutcomeConflict(
                'This Voucher claim outcome belongs to another recipient.',
            );
        }
    }
}
