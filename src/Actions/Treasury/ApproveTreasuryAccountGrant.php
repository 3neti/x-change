<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\TreasuryAccountGrantStatus;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryAccountGrant;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountGrantJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final readonly class ApproveTreasuryAccountGrant
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private TreasuryAccountGrantJournal $journal,
    ) {}

    public function handle(TreasuryAccountGrant $grant, Model $checker): TreasuryAccountGrant
    {
        $this->authority->assertAllows($checker, TreasuryOperatorCapability::ApproveAccountGrants);

        return DB::transaction(function () use ($grant, $checker): TreasuryAccountGrant {
            $locked = TreasuryAccountGrant::query()->lockForUpdate()->findOrFail($grant->getKey());

            if ($locked->status === TreasuryAccountGrantStatus::Approved
                || $locked->status === TreasuryAccountGrantStatus::Executed) {
                return $locked;
            }

            if ($locked->status !== TreasuryAccountGrantStatus::AwaitingApproval) {
                throw new DomainException('Only a pending Account Grant may be approved.');
            }

            if ($locked->maker_type === $checker->getMorphClass()
                && (string) $locked->maker_id === (string) $checker->getKey()) {
                throw new DomainException('The Account Grant checker must be independent from its maker.');
            }

            $locked->forceFill([
                'status' => TreasuryAccountGrantStatus::Approved,
                'checker_type' => $checker->getMorphClass(),
                'checker_id' => (string) $checker->getKey(),
                'approved_at' => now(),
            ])->save();
            $this->journal->record($locked, 'treasury.account_grant.approved', $checker);

            return $locked->refresh();
        }, attempts: 3);
    }
}
