<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryInstitutionFundClassification;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final readonly class TreasuryInstitutionFundClassificationReadModel
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private SystemUserResolverContract $systemUsers,
    ) {}

    /** @return array<string, mixed> */
    public function build(Model $operator): array
    {
        $system = $this->systemUsers->resolve();

        if (! $system instanceof Model
            || ! $this->authority->allows($operator, TreasuryOperatorCapability::ViewInstitutionFunds)) {
            return [
                'schema' => 'x-change.cockpit.treasury-institution-funds.v1',
                'can_view' => false,
                'can_request' => false,
                'can_approve' => false,
                'can_execute' => false,
                'balance' => $this->money(0, 'PHP'),
                'candidates' => [],
                'classifications' => [],
            ];
        }

        $classifications = TreasuryInstitutionFundClassification::query()
            ->with(['maker', 'checker'])
            ->latest()
            ->limit(100)
            ->get();
        $usedEvidence = $classifications->pluck('evidence_operation_reference');
        $candidates = TreasuryPositionOperation::query()
            ->with('destinationPosition')
            ->where('operation_type', TreasuryPositionOperationType::Recognition->value)
            ->where('status', 'committed')
            ->where('metadata->source', 'provider_balance_reconciliation')
            ->whereHas('destinationPosition', fn ($query) => $query
                ->where('purpose', TreasuryPositionPurpose::LegacyUnattributed->value)
                ->where('principal_type', $system->getMorphClass())
                ->where('principal_id', $system->getKey()))
            ->when($usedEvidence->isNotEmpty(), fn ($query) => $query
                ->whereNotIn('operation_reference', $usedEvidence))
            ->latest()
            ->limit(100)
            ->get();
        $ledgerBalances = Wallet::query()
            ->whereIn('id', $candidates->pluck('destinationPosition.internal_ledger_id')->filter())
            ->get()
            ->keyBy('id');

        return [
            'schema' => 'x-change.cockpit.treasury-institution-funds.v1',
            'can_view' => true,
            'can_request' => $this->authority->allows($operator, TreasuryOperatorCapability::RequestInstitutionFunds),
            'can_approve' => $this->authority->allows($operator, TreasuryOperatorCapability::ApproveInstitutionFunds),
            'can_execute' => $this->authority->allows($operator, TreasuryOperatorCapability::ExecuteInstitutionFunds),
            'balance' => $this->institutionOwnedBalance($system),
            'candidates' => $candidates->map(function (TreasuryPositionOperation $evidence) use ($ledgerBalances): array {
                $position = $evidence->destinationPosition;
                $availableMinor = $position instanceof TreasuryPosition
                    ? (int) ($ledgerBalances->get($position->internal_ledger_id)?->getBalanceIntAttribute() ?? 0)
                    : 0;

                return [
                    'operation_reference' => $evidence->operation_reference,
                    'evidence_reference' => (string) $evidence->external_reference,
                    'amount_minor' => $evidence->amount_minor,
                    'amount' => $this->money($evidence->amount_minor, $evidence->currency),
                    'currency' => $evidence->currency,
                    'connection_reference' => (string) $position?->connection_reference,
                    'available' => $availableMinor >= $evidence->amount_minor,
                    'observed_at' => $evidence->created_at?->toIso8601String(),
                ];
            })->values()->all(),
            'classifications' => $classifications->map(fn (TreasuryInstitutionFundClassification $classification): array => [
                'reference' => $classification->reference,
                'status' => $classification->status->value,
                'amount' => $this->money($classification->amount_minor, $classification->currency),
                'ownership_basis' => $classification->ownership_basis,
                'evidence_reference' => $classification->evidence_reference,
                'maker' => (string) ($classification->maker?->getAttribute('name') ?: 'Named operator'),
                'checker' => $classification->checker instanceof Model
                    ? (string) ($classification->checker->getAttribute('name') ?: 'Named operator')
                    : null,
                'updated_at' => $classification->updated_at?->toIso8601String(),
                'actions' => [
                    'approve' => route(
                        'x-change.cockpit.treasury.institution-funds.approvals.store',
                        $classification,
                    ),
                    'execute' => route(
                        'x-change.cockpit.treasury.institution-funds.executions.store',
                        $classification,
                    ),
                ],
            ])->all(),
        ];
    }

    private function institutionOwnedBalance(Model $system): string
    {
        $ledgerIds = TreasuryPosition::query()
            ->where('purpose', TreasuryPositionPurpose::InstitutionOwnedFunds->value)
            ->where('status', 'active')
            ->where('principal_type', $system->getMorphClass())
            ->where('principal_id', $system->getKey())
            ->pluck('internal_ledger_id');
        $balanceMinor = Wallet::query()->whereIn('id', $ledgerIds)->get()
            ->sum(fn (Wallet $wallet): int => $wallet->getBalanceIntAttribute());

        return $this->money($balanceMinor, 'PHP');
    }

    private function money(int $amountMinor, string $currency): string
    {
        $prefix = mb_strtoupper($currency) === 'PHP' ? '₱' : mb_strtoupper($currency).' ';

        return $prefix.number_format($amountMinor / 100, 2);
    }
}
