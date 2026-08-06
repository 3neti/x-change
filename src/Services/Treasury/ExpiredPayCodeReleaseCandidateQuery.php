<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;

final readonly class ExpiredPayCodeReleaseCandidateQuery
{
    /** @return Builder<Voucher> */
    public function build(int $limit): Builder
    {
        $query = Voucher::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNull('redeemed_at')
            ->whereNotIn('state', [
                VoucherState::CLOSED->value,
                VoucherState::CANCELLED->value,
            ]);

        $this->applyReservationConstraints($query);

        return $query
            ->whereNotIn('id', VoucherClaim::query()->select('voucher_id'))
            ->whereNotIn('id', DisbursementReconciliation::query()->select('voucher_id'))
            ->oldest('expires_at')
            ->oldest('id')
            ->limit(max(1, $limit));
    }

    /** @param Builder<Voucher> $query */
    private function applyReservationConstraints(Builder $query): void
    {
        if ($query->getQuery()->getGrammar() instanceof PostgresGrammar) {
            $query
                ->whereRaw(
                    "(metadata::jsonb #>> '{treasury,pay_code_reservation,status}') = ?",
                    ['reserved'],
                )
                ->whereRaw(
                    "COALESCE(metadata::jsonb #>> '{treasury,pay_code_reservation,source_position_purpose}', ?) = ?",
                    [
                        TreasuryPositionPurpose::ClientFunds->value,
                        TreasuryPositionPurpose::ClientFunds->value,
                    ],
                );

            return;
        }

        $query
            ->where('metadata->treasury->pay_code_reservation->status', 'reserved')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('metadata->treasury->pay_code_reservation->source_position_purpose')
                    ->orWhere(
                        'metadata->treasury->pay_code_reservation->source_position_purpose',
                        TreasuryPositionPurpose::ClientFunds->value,
                    );
            });
    }
}
