<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PartnerApi;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Enums\PartnerApiProductionMandateStatus;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiGovernanceJournal;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiOperatorAuthority;

final readonly class ApprovePartnerApiProductionMandate
{
    public function __construct(
        private PartnerApiOperatorAuthority $authority,
        private PartnerApiGovernanceJournal $journal,
        private SystemUserResolverContract $systemPrincipal,
    ) {}

    public function handle(PartnerApiProductionMandate $mandate, Model $checker): PartnerApiProductionMandate
    {
        if ($checker->is($this->systemPrincipal->resolve())
            || ! $this->authority->allows($checker, PartnerApiOperatorCapability::ApproveProductionClients)) {
            throw new AuthorizationException('Production Partner API checker authority is required.');
        }

        return DB::transaction(function () use ($mandate, $checker): PartnerApiProductionMandate {
            $locked = PartnerApiProductionMandate::query()->lockForUpdate()->findOrFail($mandate->getKey());
            if ($locked->status === PartnerApiProductionMandateStatus::Approved || $locked->status === PartnerApiProductionMandateStatus::Activated) {
                return $locked;
            }
            if ($locked->status !== PartnerApiProductionMandateStatus::AwaitingApproval) {
                throw new DomainException('Only a pending production mandate may be approved.');
            }
            if ($locked->maker_type === $checker->getMorphClass() && (string) $locked->maker_id === (string) $checker->getKey()) {
                throw new DomainException('The production mandate checker must be independent from the maker.');
            }
            $locked->forceFill([
                'status' => PartnerApiProductionMandateStatus::Approved,
                'checker_type' => $checker->getMorphClass(),
                'checker_id' => (string) $checker->getKey(),
                'approved_at' => now(),
            ])->save();
            $this->journal->recordMandate($locked, 'partner_api.production_mandate.approved', $checker);

            return $locked->refresh();
        }, 3);
    }
}
