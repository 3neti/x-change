<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LBHurtado\PaymentGateway\Funding\NetbankReusableFundingAddressProvider;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Funding\NetbankReusableFundingObservationData;

final class InspectNetbankReusableFundingAddressHistory
{
    public function __construct(
        private readonly NetbankReusableFundingAddressProvider $netbank,
        private readonly AuditLoggerContract $audit,
    ) {}

    /**
     * @return list<NetbankReusableFundingObservationData>
     */
    public function handle(Model $owner): array
    {
        $this->assertEnabled();
        $observations = array_map(
            static fn ($observation): NetbankReusableFundingObservationData => new NetbankReusableFundingObservationData(
                reference: 'NB-'.strtoupper(substr($observation->transactionHash, 0, 12)),
                grossAmountMinor: $observation->grossAmountMinor,
                feeAmountMinor: $observation->feeAmountMinor,
                netAmountMinor: $observation->netAmountMinor,
                currency: $observation->currency,
                providerStatus: $observation->providerStatus,
                occurredAt: $observation->occurredAt?->format(DATE_ATOM),
                settledAt: $observation->settledAt?->format(DATE_ATOM),
            ),
            $this->netbank->observationsForOwner($this->ownerReference($owner)),
        );

        $this->audit->log('funding.reusable_address.history_inspected', [
            'actor_type' => $owner::class,
            'actor_id' => (string) $owner->getKey(),
            'provider' => 'netbank',
            'mode' => 'authoritative-vca-history-observation',
            'observation_count' => count($observations),
            'funding_intent_created' => false,
            'automatic_credit_enabled' => false,
        ]);

        return $observations;
    }

    private function ownerReference(Model $owner): string
    {
        return $owner::class.':'.$owner->getKey();
    }

    private function assertEnabled(): void
    {
        if (! (bool) config('x-change.funding.reusable_address.enabled', false)) {
            throw ValidationException::withMessages([
                'reusable_address' => 'The temporary reusable funding address is disabled.',
            ]);
        }
    }
}
