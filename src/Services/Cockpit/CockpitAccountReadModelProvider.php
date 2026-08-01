<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;

class CockpitAccountReadModelProvider
{
    public function __construct(
        private readonly FundingDestinationResolverContract $destinations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forOwner(Model $owner, string $accountReference): array
    {
        return [
            'schema' => 'x-change.cockpit.account-management.v1',
            'status' => 'available',
            'account' => [
                'reference' => $this->maskedAccountReference($accountReference),
                'currency' => (string) config('x-change.product.default_currency', 'PHP'),
                'ledger_authority' => 'internal-account-ledger',
                'funding_credit_policy' => 'verified-provider-settlement-only',
            ],
            'providers' => [
                $this->provider($owner, 'netbank', $accountReference),
                $this->provider($owner, 'paynamics_constellation', $accountReference),
            ],
            'connection_history' => $this->connectionHistory($owner),
            'controls' => [
                'shared_is_default' => true,
                'dedicated_fallback_enabled' => false,
                'pin_confirmation_required' => true,
                'manual_balance_adjustment_enabled' => false,
                'provider_webhook_settlement_required' => true,
            ],
            'redactions' => [
                'account_numbers' => 'masked',
                'wallet_ids' => 'masked',
                'routing_tokens' => 'ephemeral-per-vca',
                'credentials_exposed' => false,
            ],
        ];
    }

    /**
     * @return array{
     *     schema: string,
     *     status: string,
     *     account: array{reference: string, currency: string},
     *     funding_destinations: array<int, array{
     *         code: string,
     *         label: string,
     *         mode: string,
     *         status: string,
     *         display_reference: string|null
     *     }>
     * }
     */
    public function forDepositor(Model $owner, string $accountReference): array
    {
        $providers = [
            $this->provider($owner, 'netbank', $accountReference),
            $this->provider($owner, 'paynamics_constellation', $accountReference),
        ];

        return [
            'schema' => 'x-change.cockpit.depositor-account.v1',
            'status' => 'available',
            'account' => [
                'reference' => $this->maskedAccountReference($accountReference),
                'currency' => (string) config('x-change.product.default_currency', 'PHP'),
            ],
            'funding_destinations' => array_map(
                function (array $provider): array {
                    $mode = (string) $provider['mode'];
                    $destination = $mode === 'dedicated'
                        ? $provider['dedicated']
                        : $provider['shared'];

                    return [
                        'code' => (string) $provider['code'],
                        'label' => (string) $provider['label'],
                        'mode' => $mode,
                        'status' => (string) $destination['status'],
                        'display_reference' => is_string($destination['display_reference'] ?? null)
                            ? $destination['display_reference']
                            : null,
                    ];
                },
                $providers,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function provider(Model $owner, string $provider, string $accountReference): array
    {
        $preference = FundingDestinationPreference::query()
            ->with('providerAccountLink')
            ->whereMorphedTo('owner', $owner)
            ->where('provider_code', $provider)
            ->first();
        $link = $preference?->providerAccountLink;

        try {
            $shared = $this->destinations->shared($provider, $accountReference);
            $sharedStatus = 'ready';
            $sharedReference = $shared->displayReference;
        } catch (FundingDestinationUnavailable) {
            $sharedStatus = 'not_configured';
            $sharedReference = null;
        }

        return [
            'code' => $provider,
            'label' => $provider === 'netbank' ? 'NetBank' : 'Paynamics',
            'mode' => $preference?->mode ?? 'shared',
            'shared' => [
                'status' => $sharedStatus,
                'display_reference' => $sharedReference,
                'managed_by' => 'platform configuration',
            ],
            'dedicated' => [
                'configured' => $link instanceof ProviderAccountLink,
                'display_reference' => $link?->display_reference,
                'status' => $link?->status ?? 'not_configured',
                'verification_status' => $link?->verification_status ?? 'not_configured',
                'verified_at' => $link?->verified_at?->toIso8601String(),
                'last_synced_at' => $link?->last_synced_at?->toIso8601String(),
                'can_activate' => $link?->isReady() === true
                    && ($provider === 'netbank'
                        ? in_array($link->verification_status, [
                            'verified',
                            'credential_supplied',
                            'routing_configured',
                        ], true)
                        : $link->verification_status === 'ownership_verified'),
                'can_rotate_token' => false,
                'ownership_verification_required' => $provider === 'paynamics_constellation'
                    && $link?->verification_status !== 'ownership_verified',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function connectionHistory(Model $owner): array
    {
        return ProviderAccountLink::query()
            ->whereMorphedTo('owner', $owner)
            ->where('purpose', 'funding')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ProviderAccountLink $link): array => [
                'id' => (string) $link->getKey(),
                'provider' => $link->provider,
                'display_reference' => $link->display_reference,
                'status' => $link->status,
                'verification_status' => $link->verification_status,
                'created_at' => $link->created_at?->toIso8601String(),
                'disabled_at' => $link->disabled_at?->toIso8601String(),
            ])
            ->all();
    }

    private function maskedAccountReference(string $accountReference): string
    {
        $suffix = str($accountReference)->afterLast(':')->substr(-8)->toString();

        return 'Account •••• '.$suffix;
    }
}
