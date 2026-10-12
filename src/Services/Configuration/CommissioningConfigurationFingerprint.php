<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Support\Arr;
use JsonException;
use RuntimeException;

final readonly class CommissioningConfigurationFingerprint
{
    public function __construct(
        private DeploymentConfigurationInspector $deployment,
        private DeploymentEnvironmentCatalog $environment,
        private DeploymentConnectionCatalog $connections,
    ) {}

    /**
     * @throws JsonException
     */
    public function current(): string
    {
        $deployment = $this->deployment->inspect();
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('A stable application key is required for commissioning.');
        }

        $templates = $this->connections->templates();
        $connections = [];

        foreach ($deployment['active_connections'] as $reference) {
            $template = $templates[$reference];
            $capabilities = array_map(
                static fn ($capability): string => $capability->value,
                $template->requiredCapabilities,
            );
            sort($capabilities);

            $connections[$reference] = [
                'provider' => $template->provider,
                'currency' => $template->currency,
                'inventory_reference' => $template->inventoryReference,
                'settlement_resource_reference' => $template->settlementResourceReference,
                'settlement_resource_type' => $template->settlementResourceType,
                'custody_mode' => $template->custodyMode,
                'required_capabilities' => $capabilities,
                'decimal_places' => $template->decimalPlaces,
                'treasury_mode' => config("x-change.treasury.connections.{$reference}.mode"),
                'settlement_accounts' => match ($template->provider) {
                    'netbank' => [
                        'source_account_number' => config('disbursement.source.account_number'),
                        'sender_customer_id' => config('omnipay.gateways.netbank.options.senderCustomerId'),
                        'vca_alias' => config('disbursement.client.alias'),
                        'funding_corporate_account_number' => config('payment-gateway.netbank.funding.corporate_account_number'),
                        'funding_vca_alias' => config('payment-gateway.netbank.funding.vca_alias'),
                    ],
                    'paynamics_constellation' => [
                        'settlement_wallet_id' => config('constellation.settlement_wallet_id'),
                        'revenue_wallet_id' => config('constellation.revenue_wallet_id'),
                        'funding_wallet_id' => config('constellation.funding.wallet_id'),
                    ],
                    default => [],
                },
            ];
        }

        ksort($connections);

        $payload = json_encode([
            'schema' => 2,
            'profile' => $deployment['profile'],
            'runtime_tier' => $deployment['runtime_tier'],
            'connections' => $connections,
            'identity' => [
                'instance_id' => config('x-change.instance.id'),
                'system_user_column' => config('x-change.payout.system_user_column'),
                'system_user_id' => config('x-change.payout.system_user_id'),
                'system_wallet_slug' => config('x-change.payout.system_wallet_slug', 'platform'),
                'affiliation_identity_pepper' => config('x-affiliation.identity_pepper'),
                'treasury_legal_entity_reference' => config('x-change.treasury.legal_entity_reference'),
                'treasury_legal_profile_version' => config('x-change.treasury.legal_profile_version'),
                'commercial_billing_mode' => config('x-change.commercial.billing.mode'),
                'commercial_governance_mode' => config('x-change.commercial.offerings.governance_mode', 'bootstrap_immutable'),
                'commercial_principal_reference' => config('x-change.commercial.principal.reference'),
                'commercial_principal_legal_name' => config('x-change.commercial.principal.legal_name'),
                'commercial_principal_authorization_reference' => config('x-change.commercial.principal.authorization_reference'),
                'commercial_revenue_account_slug' => config('x-change.commercial.principal.revenue_account_slug'),
            ],
        ], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $payload, $key);
    }

    /**
     * Match the version 2 manifest exactly during a guarded one-time upgrade.
     *
     * @throws JsonException
     */
    public function legacy(): string
    {
        $deployment = $this->deployment->inspect();
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('A stable application key is required for commissioning.');
        }

        $configuration = [];

        foreach ($this->environment->variables() as $variable) {
            if ($variable->configPath === null) {
                continue;
            }

            $configuration[$variable->key] = config($variable->configPath);
        }

        ksort($configuration);

        $payload = json_encode([
            'schema' => 1,
            'profile' => $deployment['profile'],
            'connections' => Arr::sort($deployment['active_connections']),
            'commercial_governance_mode' => config(
                'x-change.commercial.offerings.governance_mode',
                'bootstrap_immutable',
            ),
            'configuration' => $configuration,
        ], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $payload, $key);
    }
}
