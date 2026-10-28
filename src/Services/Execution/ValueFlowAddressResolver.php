<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\MoneyIssuer\Contracts\InstitutionResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\PayoutDestinationValidatorContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Disbursement\PayoutDestinationValidationData;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use RuntimeException;

final readonly class ValueFlowAddressResolver
{
    public function __construct(
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryPositionReadModelContract $positions,
        private InstitutionResolverContract $institutions,
        private PayoutDestinationValidatorContract $payoutDestinations,
    ) {}

    public function resolve(
        string $address,
        string $connectionReference,
        string $currency,
    ): TreasuryPositionData|PayoutDestinationValidationData {
        if (str_starts_with($address, 'account.')) {
            return $this->clientFunds($address, $connectionReference, $currency);
        }

        if (str_starts_with($address, 'instapay.')) {
            return $this->instapayDestination($address);
        }

        throw new InvalidArgumentException('Unsupported value flow address.');
    }

    public function clientFunds(
        string $address,
        string $connectionReference,
        string $currency,
    ): TreasuryPositionData {
        if (preg_match('/^account\.mobile:(09\d{9})\.client_funds$/', $address, $matches) !== 1) {
            throw new InvalidArgumentException('Expected account.mobile:09XXXXXXXXX.client_funds.');
        }

        $modelClass = config('x-change.onboarding.issuer_model');

        if (! is_string($modelClass) || ! is_a($modelClass, Model::class, true)) {
            throw new RuntimeException('The account principal model is not configured.');
        }

        $owners = $modelClass::query()
            ->whereIn('mobile', MobileNumber::candidates($matches[1]))
            ->limit(2)
            ->get();

        if ($owners->count() !== 1) {
            throw new RuntimeException('The account mobile must identify exactly one principal.');
        }

        $positions = array_values(array_filter(
            $this->positions->forPrincipal($this->principalReferences->resolve($owners->first())),
            static fn (TreasuryPositionData $position): bool => $position->purpose === TreasuryPositionPurpose::ClientFunds
                && $position->connectionReference === $connectionReference
                && $position->currency === strtoupper($currency)
                && $position->status === 'active',
        ));

        if (count($positions) !== 1) {
            throw new RuntimeException('The account must have exactly one active Client Funds position for this connection and currency.');
        }

        return $positions[0];
    }

    public function instapayDestination(string $address): PayoutDestinationValidationData
    {
        if (preg_match('/^instapay\.([a-z0-9_-]+)\.(mobile|account):([0-9]{6,34})$/', $address, $matches) !== 1) {
            throw new InvalidArgumentException('Expected instapay.<institution>.mobile:09XXXXXXXXX.');
        }

        if ($matches[2] === 'mobile' && preg_match('/^09\d{9}$/', $matches[3]) !== 1) {
            throw new InvalidArgumentException('Expected a Philippine mobile number.');
        }

        $institution = $this->institutions->resolve($matches[1], SettlementRail::INSTAPAY);
        $destination = $this->payoutDestinations->validate(
            $institution->bankCode,
            $matches[3],
            SettlementRail::INSTAPAY->value,
        );

        if (! $destination->isValid()) {
            throw new InvalidArgumentException($destination->message);
        }

        return $destination;
    }
}
