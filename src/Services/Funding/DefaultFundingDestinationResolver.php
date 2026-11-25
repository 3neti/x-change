<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Contracts\ProviderFundingDestinationResolver;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingDestinationLinkData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingDestinationUnavailable;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Banking\AssignedFundingTreasuryConnection;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use RuntimeException;

class DefaultFundingDestinationResolver implements FundingDestinationResolverContract
{
    public function resolve(mixed $owner, string $provider, string $accountReference): FundingDestinationData
    {
        if (! $owner instanceof Model) {
            return $this->shared($provider, $accountReference);
        }

        $provider = $this->providerCode($provider);
        $preference = FundingDestinationPreference::query()
            ->with('providerAccountLink')
            ->whereMorphedTo('owner', $owner)
            ->where('provider_code', $provider)
            ->first();

        if ($preference === null || $preference->mode === 'shared') {
            return $this->shared($provider, $accountReference);
        }

        if ($preference->mode !== 'dedicated') {
            throw new FundingDestinationUnavailable("Funding destination mode [{$preference->mode}] is unsupported.");
        }

        $link = $preference->providerAccountLink;

        if (! $link instanceof ProviderAccountLink
            || $link->owner_type !== $owner::class
            || (string) $link->owner_id !== (string) $owner->getKey()
            || $this->providerCode((string) $link->provider) !== $provider
            || ! $link->isReady()) {
            throw new FundingDestinationUnavailable(
                'The selected dedicated funding destination is not active.',
            );
        }

        $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
            ->where('provider_account_link_id', $link->getKey())->first();
        if ($assignment !== null && ! in_array('funding', $link->capabilities ?? [], true)) {
            throw new FundingDestinationUnavailable('The selected account is not ready for funding.');
        }

        try {
            $destination = $this->providerResolver($provider)->dedicatedFundingDestination(
                new ProviderFundingDestinationLinkData(
                    verificationStatus: (string) $link->verification_status,
                    providerAccountId: $link->provider_account_id,
                    providerWalletId: $link->provider_wallet_id,
                    displayReference: $link->display_reference,
                    fingerprint: $link->routing_fingerprint,
                    routing: $link->routing_profile_ciphertext,
                ),
                $accountReference,
            );

            $destination = $this->validateDestination($destination, $provider, $accountReference, 'dedicated');
            if ($assignment !== null) {
                if ($provider === 'netbank') {
                    $details = $assignment->details_ciphertext;
                    $providerNumber = is_array($details) ? ($details['provider_account_number'] ?? $details['account_number'] ?? null) : null;
                    $identity = is_array($details) ? ($details['account_number'] ?? null) : null;
                    if (! is_string($providerNumber) || ! is_string($identity)
                        || preg_replace('/\D+/', '', $providerNumber) !== $identity
                        || preg_replace('/\D+/', '', (string) $destination->bankAccountNumber) !== $identity) {
                        throw new FundingDestinationUnavailable('The selected account funding details are inconsistent.');
                    }
                    $destination->bankAccountNumber = $providerNumber;
                }
                try {
                    $connection = app(AssignedBankTreasuryConnection::class)->resolve($owner, (int) $link->getKey());
                } catch (RuntimeException $exception) {
                    throw new FundingDestinationUnavailable('The selected account Treasury connection is unavailable.', previous: $exception);
                }
                $destination->metadata = array_merge($destination->metadata, [
                    'assigned_bank_account_link_id' => (int) $link->getKey(),
                    'netbank_instruction_mode' => data_get($link->metadata, 'funding_assignment.instruction_mode', 'registered_vca'),
                    'treasury_connection_reference' => $connection->reference,
                    'treasury_inventory_reference' => $connection->inventoryReference,
                    'treasury_resource_reference' => $connection->settlementResourceReference,
                ]);
                try {
                    app(AssignedFundingTreasuryConnection::class)->resolve(
                        $owner, $provider, $connection->currency, FundingDestinationSnapshot::fromData($destination),
                    );
                } catch (FundingSettlementDenied $exception) {
                    throw new FundingDestinationUnavailable('The selected account funding details are inconsistent.', previous: $exception);
                }
            }

            return $destination;
        } catch (ProviderFundingDestinationUnavailable $exception) {
            throw new FundingDestinationUnavailable($exception->getMessage(), previous: $exception);
        }
    }

    public function shared(string $provider, string $accountReference): FundingDestinationData
    {
        $provider = $this->providerCode($provider);
        if ($provider === 'qrph_simulator') {
            return $this->sharedQrPhSimulator($accountReference);
        }

        try {
            $destination = $this->providerResolver($provider)->sharedFundingDestination($accountReference);

            return $this->validateDestination($destination, $provider, $accountReference, 'shared');
        } catch (ProviderFundingDestinationUnavailable $exception) {
            throw new FundingDestinationUnavailable($exception->getMessage(), previous: $exception);
        }
    }

    private function providerResolver(string $provider): ProviderFundingDestinationResolver
    {
        $matches = collect(app()->tagged('emi.funding-destination-resolvers'))
            ->filter(fn (mixed $candidate): bool => $candidate instanceof ProviderFundingDestinationResolver
                && $candidate->providerCode() === $provider);
        if ($matches->count() !== 1) {
            throw new FundingDestinationUnavailable('A unique provider funding destination resolver is required.');
        }

        return $matches->first();
    }

    private function validateDestination(FundingDestinationData $destination, string $provider, string $accountReference, string $mode): FundingDestinationData
    {
        if ($destination->provider !== $provider || $destination->accountReference !== $accountReference || $destination->mode !== $mode) {
            throw new FundingDestinationUnavailable('The provider returned a mismatched funding destination.');
        }

        return $destination;
    }

    private function sharedQrPhSimulator(string $accountReference): FundingDestinationData
    {
        return new FundingDestinationData(
            provider: 'qrph_simulator',
            mode: 'shared',
            destinationType: 'wallet',
            accountReference: $accountReference,
            displayReference: 'Local simulated clearing',
            fingerprint: $this->fingerprint('qrph_simulator', 'local-simulated-clearing'),
            verificationStatus: 'simulation_only',
            providerWalletId: 'qrph-simulator-clearing',
        );
    }

    private function providerCode(string $provider): string
    {
        return match (strtolower(trim($provider))) {
            'paynamics' => 'paynamics_constellation',
            default => strtolower(trim($provider)),
        };
    }

    private function fingerprint(string ...$parts): string
    {
        return hash('sha256', implode('|', $parts));
    }
}
