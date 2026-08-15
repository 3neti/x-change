<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PartnerApi;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Passport\ClientRepository;
use LBHurtado\XChange\Data\PartnerApi\PartnerApiCredentialData;
use LBHurtado\XChange\Enums\PartnerApiClientStatus;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiGovernanceJournal;

class CreatePartnerApiClient
{
    public function __construct(
        protected Application $app,
        protected ClientRepository $clients,
        protected PartnerApiGovernanceJournal $journal,
    ) {}

    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $mandate
     */
    public function handle(
        string $name,
        Model $issuer,
        string $environment = 'sandbox',
        array $scopes = [],
        array $mandate = [],
        Model|string $operator = 'commissioning_authority',
    ): PartnerApiCredentialData {
        $allowedScopes = array_keys((array) config('x-change.partner_api.scopes', []));
        $resolvedScopes = $scopes === []
            ? array_values((array) config('x-change.partner_api.default_scopes', []))
            : array_values(array_unique($scopes));
        $unknownScopes = array_values(array_diff($resolvedScopes, $allowedScopes));

        if ($unknownScopes !== []) {
            throw new InvalidArgumentException('Unknown Partner API scopes: '.implode(', ', $unknownScopes));
        }

        if ($environment !== 'sandbox') {
            throw new InvalidArgumentException(
                'Production Partner API clients require governed maker-checker activation.',
            );
        }

        if ($this->app->environment('production')) {
            throw new InvalidArgumentException(
                'Sandbox Partner API clients cannot be activated in a production application.',
            );
        }

        return DB::transaction(function () use ($name, $issuer, $environment, $resolvedScopes, $mandate, $operator): PartnerApiCredentialData {
            $oauthClient = $this->clients->createClientCredentialsGrantClient(trim($name));
            $resolvedMandate = array_replace_recursive(
                (array) config('x-change.partner_api.default_mandate', []),
                $mandate,
            );

            $partner = PartnerApiClient::query()->create([
                'reference' => (string) Str::ulid(),
                'oauth_client_id' => (string) $oauthClient->getKey(),
                'name' => trim($name),
                'issuer_type' => $issuer->getMorphClass(),
                'issuer_id' => (string) $issuer->getKey(),
                'environment' => $environment,
                'status' => PartnerApiClientStatus::Active,
                'scopes' => $resolvedScopes,
                'mandate' => $resolvedMandate,
                'activated_at' => now(),
            ]);
            $this->journal->recordClient($partner, 'partner_api.client.activated', $operator);

            return new PartnerApiCredentialData(
                reference: $partner->reference,
                client_id: (string) $oauthClient->getKey(),
                client_secret: (string) $oauthClient->plainSecret,
                environment: $environment,
                scopes: $resolvedScopes,
                mandate: $resolvedMandate,
            );
        });
    }
}
