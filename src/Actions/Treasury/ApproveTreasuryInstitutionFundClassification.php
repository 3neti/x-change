<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\TreasuryInstitutionFundClassificationStatus;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryInstitutionFundClassification;
use LBHurtado\XChange\Services\Treasury\TreasuryInstitutionFundClassificationJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final readonly class ApproveTreasuryInstitutionFundClassification
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private TreasuryInstitutionFundClassificationJournal $journal,
    ) {}

    public function handle(
        TreasuryInstitutionFundClassification $classification,
        Model $checker,
    ): TreasuryInstitutionFundClassification {
        $this->authority->assertAllows($checker, TreasuryOperatorCapability::ApproveInstitutionFunds);

        return DB::transaction(function () use ($classification, $checker): TreasuryInstitutionFundClassification {
            $locked = TreasuryInstitutionFundClassification::query()
                ->lockForUpdate()
                ->findOrFail($classification->getKey());

            if (in_array($locked->status, [
                TreasuryInstitutionFundClassificationStatus::Approved,
                TreasuryInstitutionFundClassificationStatus::Executed,
            ], true)) {
                return $locked;
            }

            if ($locked->status !== TreasuryInstitutionFundClassificationStatus::AwaitingApproval) {
                throw new DomainException('Only a pending Institution-Owned Funds classification may be approved.');
            }

            if ($locked->maker_type === $checker->getMorphClass()
                && (string) $locked->maker_id === (string) $checker->getKey()) {
                throw new DomainException('The classification checker must be independent from its maker.');
            }

            $locked->forceFill([
                'status' => TreasuryInstitutionFundClassificationStatus::Approved,
                'checker_type' => $checker->getMorphClass(),
                'checker_id' => (string) $checker->getKey(),
                'approved_at' => now(),
            ])->save();
            $this->journal->record($locked, 'treasury.institution_funds.approved', $checker);

            return $locked->refresh();
        }, attempts: 3);
    }
}
