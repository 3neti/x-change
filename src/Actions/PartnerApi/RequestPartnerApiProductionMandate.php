<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PartnerApi;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Enums\PartnerApiProductionMandateStatus;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiGovernanceJournal;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiMandateValidator;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiOperatorAuthority;

final readonly class RequestPartnerApiProductionMandate
{
    public function __construct(
        private PartnerApiOperatorAuthority $authority,
        private PartnerApiGovernanceJournal $journal,
        private SystemUserResolverContract $systemPrincipal,
        private PartnerApiMandateValidator $mandates,
    ) {}

    /** @param list<string> $scopes @param array<string, mixed> $mandate */
    public function handle(string $name, Model $issuer, array $scopes, array $mandate, Model $maker): PartnerApiProductionMandate
    {
        if ($maker->is($this->systemPrincipal->resolve())
            || ! $this->authority->allows($maker, PartnerApiOperatorCapability::RequestProductionClients)) {
            throw new AuthorizationException('Production Partner API maker authority is required.');
        }

        $resolvedScopes = array_values(array_unique($scopes));
        $resolvedMandate = array_replace_recursive(
            (array) config('x-change.partner_api.default_mandate', []),
            $mandate,
        );
        $this->mandates->validate($resolvedScopes, $resolvedMandate);

        $snapshot = [
            'name' => trim($name),
            'issuer_type' => $issuer->getMorphClass(),
            'issuer_id' => (string) $issuer->getKey(),
            'scopes' => $resolvedScopes,
            'mandate' => $resolvedMandate,
        ];
        $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use ($snapshot, $hash, $issuer, $maker): PartnerApiProductionMandate {
            $record = PartnerApiProductionMandate::query()->create([
                'reference' => (string) Str::ulid(),
                'name' => $snapshot['name'],
                'issuer_type' => $issuer->getMorphClass(),
                'issuer_id' => (string) $issuer->getKey(),
                'status' => PartnerApiProductionMandateStatus::AwaitingApproval,
                'scopes' => $snapshot['scopes'],
                'mandate' => $snapshot['mandate'],
                'snapshot_hash' => $hash,
                'maker_type' => $maker->getMorphClass(),
                'maker_id' => (string) $maker->getKey(),
                'submitted_at' => now(),
            ]);
            $this->journal->recordMandate($record, 'partner_api.production_mandate.submitted', $maker);

            return $record;
        }, 3);
    }
}
