<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PartnerApi;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Data\PartnerApi\PartnerApiCredentialData;
use LBHurtado\XChange\Enums\PartnerApiClientStatus;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Enums\PartnerApiProductionMandateStatus;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiGovernanceJournal;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiMandateValidator;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiOperatorAuthority;

final readonly class ActivatePartnerApiProductionMandate
{
    public function __construct(
        private PartnerApiOperatorAuthority $authority,
        private ClientRepository $clients,
        private PartnerApiGovernanceJournal $journal,
        private SystemUserResolverContract $systemPrincipal,
        private PartnerApiMandateValidator $mandates,
    ) {}

    public function handle(PartnerApiProductionMandate $mandate, Model $operator): PartnerApiCredentialData
    {
        if ($operator->is($this->systemPrincipal->resolve())
            || ! $this->authority->allows($operator, PartnerApiOperatorCapability::ActivateProductionClients)) {
            throw new AuthorizationException('Production Partner API credential activation authority is required.');
        }

        return DB::transaction(function () use ($mandate, $operator): PartnerApiCredentialData {
            $locked = PartnerApiProductionMandate::query()->with('issuer')->lockForUpdate()->findOrFail($mandate->getKey());
            if ($locked->status !== PartnerApiProductionMandateStatus::Approved || ! $locked->issuer instanceof Model) {
                throw new DomainException('Only an approved production mandate may issue credentials.');
            }
            if ($locked->maker_type === $operator->getMorphClass() && (string) $locked->maker_id === (string) $operator->getKey()) {
                throw new DomainException('The maker cannot activate production credentials.');
            }

            $this->mandates->validate($locked->scopes, $locked->mandate);

            $oauthClient = $this->clients->createClientCredentialsGrantClient($locked->name);
            $client = PartnerApiClient::query()->create([
                'reference' => (string) Str::ulid(),
                'oauth_client_id' => (string) $oauthClient->getKey(),
                'name' => $locked->name,
                'issuer_type' => $locked->issuer_type,
                'issuer_id' => (string) $locked->issuer_id,
                'environment' => 'production',
                'status' => PartnerApiClientStatus::Active,
                'scopes' => $locked->scopes,
                'mandate' => $locked->mandate,
                'activated_at' => now(),
            ]);
            $locked->forceFill([
                'status' => PartnerApiProductionMandateStatus::Activated,
                'partner_api_client_id' => $client->getKey(),
                'activated_at' => now(),
            ])->save();
            $this->journal->recordClient($client, 'partner_api.client.activated', $operator);
            $this->journal->recordMandate($locked, 'partner_api.production_mandate.activated', $operator);

            return new PartnerApiCredentialData(
                reference: $client->reference,
                client_id: (string) $oauthClient->getKey(),
                client_secret: (string) $oauthClient->plainSecret,
                environment: 'production',
                scopes: $client->scopes,
                mandate: $client->mandate,
            );
        }, 3);
    }
}
