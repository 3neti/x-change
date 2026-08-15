<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiOperatorAuthority;

final readonly class PartnerApiClientReadModel
{
    public function __construct(private PartnerApiOperatorAuthority $authority) {}

    /** @return array<string, mixed> */
    public function build(Model $operator): array
    {
        $modelClass = (string) config('auth.providers.users.model');
        $issuers = is_subclass_of($modelClass, Model::class)
            ? $modelClass::query()->latest()->limit(100)->get()
            : collect();

        return [
            'schema' => 'x-change.cockpit.partner-api-clients.v1',
            'api_enabled' => (bool) config('x-change.partner_api.enabled', false),
            'production_governance' => 'maker_checker_required',
            'can_create_sandbox' => $this->authority->allows($operator, PartnerApiOperatorCapability::ManageSandboxClients),
            'can_suspend' => $this->authority->allows($operator, PartnerApiOperatorCapability::SuspendClients),
            'can_revoke' => $this->authority->allows($operator, PartnerApiOperatorCapability::RevokeClients),
            'can_request_production' => $this->authority->allows($operator, PartnerApiOperatorCapability::RequestProductionClients),
            'can_approve_production' => $this->authority->allows($operator, PartnerApiOperatorCapability::ApproveProductionClients),
            'can_activate_production' => $this->authority->allows($operator, PartnerApiOperatorCapability::ActivateProductionClients),
            'scopes' => collect((array) config('x-change.partner_api.scopes', []))
                ->map(fn (string $description, string $scope): array => compact('scope', 'description'))
                ->values()->all(),
            'rails' => ['automatic', 'INSTAPAY', 'PESONET'],
            'issuers' => $issuers->map(fn (Model $issuer): array => [
                'id' => (string) $issuer->getKey(),
                'name' => (string) ($issuer->getAttribute('name') ?: 'Account holder'),
                'identity' => $this->maskedIdentity($issuer),
            ])->values()->all(),
            'clients' => PartnerApiClient::query()
                ->with(['issuer'])
                ->latest()
                ->get()
                ->map(fn (PartnerApiClient $client): array => $this->client($client))
                ->values()->all(),
            'production_mandates' => PartnerApiProductionMandate::query()
                ->with(['issuer'])
                ->latest('submitted_at')
                ->get()
                ->map(fn (PartnerApiProductionMandate $mandate): array => [
                    'reference' => $mandate->reference,
                    'name' => $mandate->name,
                    'status' => $mandate->status->value,
                    'snapshot_hash' => $mandate->snapshot_hash,
                    'scopes' => $mandate->scopes,
                    'issuer' => [
                        'name' => (string) ($mandate->issuer?->getAttribute('name') ?: 'Account holder'),
                        'identity' => $mandate->issuer instanceof Model ? $this->maskedIdentity($mandate->issuer) : 'Unavailable',
                    ],
                    'submitted_at' => $mandate->submitted_at?->toIso8601String(),
                    'approved_at' => $mandate->approved_at?->toIso8601String(),
                    'activated_at' => $mandate->activated_at?->toIso8601String(),
                    'actions' => [
                        'approve' => route('x-change.cockpit.api-partners.production-mandates.approvals.store', $mandate),
                        'activate' => route('x-change.cockpit.api-partners.production-mandates.activations.store', $mandate),
                    ],
                ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function client(PartnerApiClient $client): array
    {
        return [
            'reference' => $client->reference,
            'client_id' => $client->oauth_client_id,
            'name' => $client->name,
            'environment' => $client->environment,
            'status' => $client->status->value,
            'scopes' => $client->scopes,
            'issuer' => [
                'name' => (string) ($client->issuer?->getAttribute('name') ?: 'Account holder'),
                'identity' => $client->issuer instanceof Model ? $this->maskedIdentity($client->issuer) : 'Unavailable',
            ],
            'mandate' => [
                'currencies' => (array) data_get($client->mandate, 'currencies', []),
                'settlement_rails' => (array) data_get($client->mandate, 'settlement_rails', []),
                'unbound_pay_codes' => (bool) data_get($client->mandate, 'unbound_pay_codes', false),
                'maximum_amount' => $this->money((int) data_get($client->mandate, 'maximum_amount_minor', 0)),
                'daily_principal_limit' => $this->money((int) data_get($client->mandate, 'daily_principal_limit_minor', 0)),
            ],
            'created_at' => $client->created_at?->toIso8601String(),
            'suspended_at' => $client->suspended_at?->toIso8601String(),
            'revoked_at' => $client->revoked_at?->toIso8601String(),
            'actions' => [
                'check' => route('x-change.cockpit.api-partners.clients.checks.store', $client),
                'suspend' => route('x-change.cockpit.api-partners.clients.suspensions.store', $client),
                'revoke' => route('x-change.cockpit.api-partners.clients.revocations.store', $client),
            ],
        ];
    }

    private function maskedIdentity(Model $issuer): string
    {
        $mobile = preg_replace('/\D+/', '', (string) $issuer->getAttribute('mobile'));

        if (is_string($mobile) && $mobile !== '') {
            return 'Mobile ending '.substr($mobile, -4);
        }

        $email = (string) $issuer->getAttribute('email');

        if (str_contains($email, '@')) {
            [$local, $domain] = explode('@', $email, 2);

            return substr($local, 0, 1).'•••@'.$domain;
        }

        return 'Account #'.$issuer->getKey();
    }

    private function money(int $minor): string
    {
        return '₱'.Number::format($minor / 100, 2, 2);
    }
}
