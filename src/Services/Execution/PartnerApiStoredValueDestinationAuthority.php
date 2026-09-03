<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Execution\StoredValueDestinationAuthorityData;
use LBHurtado\XChange\Enums\PartnerApiProductionMandateStatus;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use Throwable;

final class PartnerApiStoredValueDestinationAuthority implements StoredValueDestinationAuthorityContract
{
    private ?bool $ready = null;

    public function __construct(
        private readonly PartnerApiRequestContext $partnerContext,
        private readonly TreasuryAccountPortfolioProvisioningContract $portfolios,
        private readonly TreasuryPrincipalReferenceResolverContract $principalReferences,
    ) {}

    public function isReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }

        if (! (bool) config('x-change.partner_api.enabled', false)) {
            return $this->ready = false;
        }

        try {
            return $this->ready = PartnerApiClient::query()
                ->where('status', 'active')
                ->whereNull('suspended_at')
                ->whereNull('revoked_at')
                ->limit(100)
                ->get(['id', 'environment', 'scopes', 'mandate'])
                ->contains(function (PartnerApiClient $client): bool {
                    if (! in_array('stored-value:spend', $client->scopes, true)
                        || data_get($client->mandate, 'stored_value_spend.enabled') !== true) {
                        return false;
                    }

                    return $client->environment !== 'production'
                        || PartnerApiProductionMandate::query()
                            ->where('partner_api_client_id', $client->getKey())
                            ->where('status', PartnerApiProductionMandateStatus::Activated)
                            ->exists();
                });
        } catch (Throwable) {
            return $this->ready = false;
        }
    }

    public function authorize(
        ExecutionContextData $context,
        int $amountMinor,
    ): StoredValueDestinationAuthorityData {
        try {
            $client = $this->partnerContext->client();
        } catch (Throwable $exception) {
            throw new StoredValueSpendRejectedException(
                'Stored value destination authority is unavailable outside an authenticated Partner API request.',
                previous: $exception,
            );
        }

        if (! $client->isActive()
            || ! in_array('stored-value:spend', $client->scopes, true)
            || data_get($client->mandate, 'stored_value_spend.enabled') !== true) {
            throw new StoredValueSpendRejectedException(
                'The authenticated Partner API mandate does not authorize stored value spending.',
            );
        }

        if ($client->environment === 'production' && ! PartnerApiProductionMandate::query()
            ->where('partner_api_client_id', $client->getKey())
            ->where('status', PartnerApiProductionMandateStatus::Activated)
            ->exists()) {
            throw new StoredValueSpendRejectedException(
                'Production stored value spending requires an activated maker-checker mandate.',
            );
        }

        $binding = StoredValueHolderBinding::query()
            ->where('voucher_id', $context->voucher?->getKey())
            ->where('status', 'active')
            ->first();
        $allocation = $binding instanceof StoredValueHolderBinding
            ? TreasuryAllocation::query()->with('reservePosition')->where('allocation_reference', $binding->allocation_reference)->first()
            : null;
        $reserve = $allocation?->reservePosition;

        if ($reserve === null) {
            throw new StoredValueSpendRejectedException('Stored value Treasury authority cannot be resolved.');
        }

        $currency = strtoupper((string) $reserve->currency);
        $currencies = array_map('strtoupper', (array) data_get($client->mandate, 'stored_value_spend.currencies', []));
        $maximum = (int) data_get($client->mandate, 'stored_value_spend.maximum_amount_minor', 0);
        $daily = (int) data_get($client->mandate, 'stored_value_spend.daily_amount_minor', 0);

        if ($amountMinor <= 0 || ! in_array($currency, $currencies, true) || $amountMinor > $maximum) {
            throw new StoredValueSpendRejectedException(
                'The requested spend is outside the authenticated Partner API mandate.',
            );
        }

        $usedToday = (int) PartnerApiOperation::query()
            ->where('partner_api_client_id', $client->getKey())
            ->where('operation', 'stored_value_spent')
            ->where('currency', $currency)
            ->where('occurred_at', '>=', now()->utc()->startOfDay())
            ->sum('principal_minor');

        if ($daily <= 0 || $usedToday + $amountMinor > $daily) {
            throw new StoredValueSpendRejectedException(
                'The authenticated Partner API daily stored value limit is exhausted.',
            );
        }

        $issuer = $client->issuer;

        if (! $issuer instanceof Model) {
            throw new StoredValueSpendRejectedException('The merchant Account cannot be resolved.');
        }

        $principalReference = $this->principalReferences->resolve($issuer);
        $portfolio = $this->portfolios->provision($issuer, [(string) $reserve->connection_reference]);
        $matches = array_values(array_filter(
            $portfolio->positions,
            static fn ($position): bool => $position->purpose === TreasuryPositionPurpose::ClientFunds
                && $position->currency === $currency
                && $position->connectionReference === $reserve->connection_reference
                && $position->principalReference === $principalReference,
        ));

        if (count($matches) !== 1) {
            throw new StoredValueSpendRejectedException(
                'The merchant mandate does not resolve to exactly one compatible Client Funds position.',
            );
        }

        return new StoredValueDestinationAuthorityData(
            counterpartyPositionReference: $matches[0]->positionReference,
            authorityReference: 'partner-api-stored-value:'.$client->reference,
            principalReference: $principalReference,
        );
    }
}
