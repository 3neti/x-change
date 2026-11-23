<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use RuntimeException;

final readonly class AssignedFundingTreasuryConnection
{
    public function __construct(private AssignedBankTreasuryConnection $connections) {}

    public function resolveForOrder(Model $owner, PayCodeIssuanceFundingOrder $order): ?TreasuryProviderConnectionData
    {
        $intent = $order->fundingIntent()->first();
        $snapshot = $intent?->destination_snapshot_ciphertext;
        $isDedicated = data_get($snapshot, 'mode') === 'dedicated';
        if (! $isDedicated) {
            if (data_get($order->metadata, 'funding_source.mode') === 'dedicated') {
                throw FundingSettlementDenied::because('the order has no frozen dedicated funding destination');
            }

            return null;
        }

        if ($intent->purpose !== FundingIntentPurpose::OnDemandIssuance
            || $intent->status !== FundingIntentStatus::Settled
            || $intent->account_reference !== $order->account_reference
            || $intent->provider_code !== $order->provider
            || $intent->currency !== $order->currency
            || data_get($intent->metadata, 'funding_order_reference') !== $order->reference) {
            throw FundingSettlementDenied::because('the funding destination is not bound to this issuance order');
        }
        $source = data_get($order->metadata, 'funding_source');
        if (! is_array($source) || ($source['mode'] ?? null) !== 'dedicated'
            || ($source['account_link_id'] ?? null) !== data_get($snapshot, 'metadata.assigned_bank_account_link_id')
            || ($source['connection_reference'] ?? null) !== $order->connection_reference
            || ! is_string($source['destination_fingerprint'] ?? null)
            || ! hash_equals($source['destination_fingerprint'], (string) data_get($snapshot, 'fingerprint'))) {
            throw FundingSettlementDenied::because('the order does not match its frozen funding destination');
        }
        $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
            ->where('provider_account_link_id', $source['account_link_id'])->first();
        $customerId = data_get($snapshot, 'metadata.on_demand_customer_id');
        $currentCustomerId = data_get($assignment?->details_ciphertext, 'customer_id');
        if (! is_string($customerId) || $customerId === '' || ! is_string($currentCustomerId)
            || ! hash_equals($customerId, $currentCustomerId)) {
            throw FundingSettlementDenied::because('the frozen funding account customer ID has changed');
        }
        $connection = $this->resolve($owner, $order->provider, $order->currency, $snapshot);
        if ($connection->reference !== $order->connection_reference) {
            throw FundingSettlementDenied::because('the issuance order Treasury connection does not match its funding destination');
        }

        return $connection;
    }

    /** @param array<string, mixed> $snapshot */
    public function resolve(Model $owner, string $provider, string $currency, array $snapshot): TreasuryProviderConnectionData
    {
        $metadata = $snapshot['metadata'] ?? null;
        $linkId = is_array($metadata) ? ($metadata['assigned_bank_account_link_id'] ?? null) : null;
        if ($provider !== 'netbank' || $currency !== 'PHP' || ($snapshot['provider'] ?? null) !== $provider
            || ($snapshot['mode'] ?? null) !== 'dedicated' || ! is_int($linkId) || $linkId <= 0) {
            throw FundingSettlementDenied::because('the dedicated funding destination has no valid account binding');
        }
        $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
            ->where('provider', $provider)->where('provider_account_link_id', $linkId)->first();
        $number = preg_replace('/\\D+/', '', (string) ($snapshot['bankAccountNumber'] ?? ''));
        $stored = $assignment?->details_ciphertext;
        if (! is_array($stored) || $number === '' || ! hash_equals((string) ($stored['account_number'] ?? ''), $number)) {
            throw FundingSettlementDenied::because('the funding destination does not match the assigned account');
        }
        $alias = $snapshot['routingAlias'] ?? null;
        $assignedAlias = $stored['vca_alias'] ?? null;
        if (! is_string($alias) || ! is_string($assignedAlias)
            || ! preg_match('/\A[0-9]{5}\z/', $alias) || ! hash_equals($assignedAlias, $alias)) {
            throw FundingSettlementDenied::because('the funding destination alias does not match the assigned account');
        }
        try {
            $connection = $this->connections->resolve($owner, $linkId);
        } catch (RuntimeException) {
            throw FundingSettlementDenied::because('the assigned funding Treasury connection is unavailable');
        }
        if (($metadata['treasury_connection_reference'] ?? null) !== $connection->reference
            || ($metadata['treasury_inventory_reference'] ?? null) !== $connection->inventoryReference
            || ($metadata['treasury_resource_reference'] ?? null) !== $connection->settlementResourceReference) {
            throw FundingSettlementDenied::because('the funding destination Treasury binding has changed');
        }

        return $connection;
    }
}
