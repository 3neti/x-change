<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\PartnerCommissionPayout;

final readonly class ApprovePartnerCommissionPayout
{
    public function execute(
        PartnerCommissionPayout $payout,
        string $checkerReference,
        string $approvalReference,
    ): PartnerCommissionPayout {
        $checkerReference = trim($checkerReference);
        $approvalReference = trim($approvalReference);

        if ($checkerReference === '' || $approvalReference === '') {
            throw new CommercialSaleConflict(
                'Partner commission approval is incomplete.',
            );
        }

        return DB::transaction(function () use (
            $approvalReference,
            $checkerReference,
            $payout,
        ): PartnerCommissionPayout {
            $locked = PartnerCommissionPayout::query()
                ->whereKey($payout->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'approved' || $locked->status === 'settled') {
                if ($locked->checker_reference !== $checkerReference
                    || $locked->approval_reference !== $approvalReference) {
                    throw new CommercialSaleConflict(
                        'Partner commission approval was replayed with different control evidence.',
                    );
                }

                return $locked;
            }

            if ($locked->status !== 'awaiting_approval') {
                throw new CommercialSaleConflict(
                    'Partner commission payout is not awaiting approval.',
                );
            }

            if (hash_equals($locked->maker_reference, $checkerReference)) {
                throw new CommercialSaleConflict(
                    'Partner commission maker and checker must be different.',
                );
            }

            PartnerCommissionPayout::query()
                ->whereKey($locked->getKey())
                ->update([
                    'status' => 'approved',
                    'checker_reference' => $checkerReference,
                    'approval_reference' => $approvalReference,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);

            return $locked->fresh();
        }, attempts: 5);
    }
}
