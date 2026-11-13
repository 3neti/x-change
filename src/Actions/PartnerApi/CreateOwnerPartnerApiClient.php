<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PartnerApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Passport\ClientRepository;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\PartnerApi\PartnerApiCredentialData;
use LBHurtado\XChange\Enums\PartnerApiClientStatus;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiGovernanceJournal;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiMandateValidator;

final readonly class CreateOwnerPartnerApiClient
{
    public function __construct(
        private ClientRepository $clients,
        private WalletAccessContract $wallets,
        private PartnerApiGovernanceJournal $journal,
        private PartnerApiMandateValidator $mandates,
        private SystemUserResolverContract $systemPrincipal,
    ) {}

    /** @param list<string> $scopes
     * @param  array<string, mixed>  $mandate
     */
    public function handle(Model $owner, string $name, array $scopes, array $mandate): PartnerApiCredentialData
    {
        $allowedScopes = array_keys((array) config('x-change.partner_api.scopes', []));
        if ($owner->is($this->systemPrincipal->resolve()) || trim($name) === '' || $scopes === [] || array_diff($scopes, $allowedScopes) !== []) {
            throw new InvalidArgumentException('A client name and valid scopes are required.');
        }

        $hasAccount = method_exists($owner, 'wallets')
            ? $owner->wallets()->where('slug', 'platform')->exists()
            : (method_exists($owner, 'wallet') && $owner->wallet()->where('slug', 'platform')->exists());
        if (! $hasAccount) {
            throw new PayCodeWalletNotResolved('An existing X-Change Account is required.');
        }
        $this->wallets->resolveForUser($owner);
        $resolvedScopes = array_values(array_unique($scopes));
        $defaults = (array) config('x-change.partner_api.default_mandate', []);
        $resolvedMandate = array_replace($defaults, $mandate);
        $resolvedMandate['stored_value_spend'] = array_replace(
            (array) ($defaults['stored_value_spend'] ?? []),
            (array) ($mandate['stored_value_spend'] ?? []),
        );
        $resolvedMandate['credential_origin'] = 'owner_self_service';
        $this->mandates->validate($resolvedScopes, $resolvedMandate);

        return DB::transaction(function () use ($owner, $name, $resolvedScopes, $resolvedMandate): PartnerApiCredentialData {
            $oauth = $this->clients->createClientCredentialsGrantClient(trim($name));
            $partner = PartnerApiClient::query()->create([
                'reference' => (string) Str::ulid(),
                'oauth_client_id' => (string) $oauth->getKey(),
                'name' => trim($name),
                'issuer_type' => $owner->getMorphClass(),
                'issuer_id' => (string) $owner->getKey(),
                'environment' => app()->environment('production') ? 'production' : 'sandbox',
                'status' => PartnerApiClientStatus::Active,
                'scopes' => $resolvedScopes,
                'mandate' => $resolvedMandate,
                'activated_at' => now(),
            ]);
            $this->journal->recordClient($partner, 'partner_api.client.activated', $owner);

            return new PartnerApiCredentialData(
                reference: $partner->reference,
                client_id: (string) $oauth->getKey(),
                client_secret: (string) $oauth->plainSecret,
                environment: $partner->environment,
                scopes: $resolvedScopes,
                mandate: $resolvedMandate,
            );
        });
    }
}
