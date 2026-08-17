<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;

final readonly class CorrectOrphanedStandingFundingAddressBinding
{
    public function __construct(
        private FundingAccountCreditContract $accounts,
        private AuditLoggerContract $audit,
    ) {}

    public function handle(
        Model $owner,
        string $accountReference,
        string $provider,
        FundingAddressPurpose $purpose,
        string $currency,
        ?FundingDestinationData $destination,
        string $bindingKey,
        string $fundingAddressHash,
    ): ?StandingFundingAddress {
        $corrected = DB::transaction(function () use (
            $owner,
            $accountReference,
            $provider,
            $purpose,
            $currency,
            $destination,
            $bindingKey,
            $fundingAddressHash,
        ): ?StandingFundingAddress {
            $collision = StandingFundingAddress::query()
                ->where('funding_address_hash', $fundingAddressHash)
                ->lockForUpdate()
                ->first();

            if (! $collision instanceof StandingFundingAddress
                || $collision->owner_type !== $owner::class
                || (string) $collision->owner_id !== (string) $owner->getKey()
                || $collision->provider_code !== $provider
                || $collision->purpose !== $purpose
                || $collision->currency !== $currency
                || AccountFundingReceipt::query()
                    ->where('standing_funding_address_id', $collision->getKey())
                    ->exists()
                || ! $this->isOrphaned($collision->account_reference)
                || ! $this->belongsTo($accountReference, $owner)) {
                return null;
            }

            $collision->forceFill([
                'binding_key' => $bindingKey,
                'account_reference' => $accountReference,
                'version' => $collision->version + 1,
                'destination_snapshot_ciphertext' => $destination === null
                    ? null
                    : FundingDestinationSnapshot::fromData($destination),
                'destination_fingerprint' => $destination?->fingerprint,
                'metadata' => array_merge($collision->metadata ?? [], [
                    'binding_corrected' => true,
                    'binding_corrected_at' => now()->toRfc3339String(),
                ]),
            ])->saveQuietly();

            return $collision->refresh();
        }, attempts: 5);

        if ($corrected instanceof StandingFundingAddress) {
            $this->audit->log('funding.standing_address.binding_corrected', [
                'standing_funding_address_reference' => $corrected->reference,
                'actor_type' => $owner::class,
                'actor_id' => (string) $owner->getKey(),
                'provider' => $provider,
                'purpose' => $purpose->value,
            ]);
        }

        return $corrected;
    }

    private function isOrphaned(string $accountReference): bool
    {
        try {
            $this->accounts->resolve($accountReference);

            return false;
        } catch (FundingSettlementDenied) {
            return true;
        }
    }

    private function belongsTo(string $accountReference, Model $owner): bool
    {
        try {
            $account = $this->accounts->resolve($accountReference);
        } catch (FundingSettlementDenied) {
            return false;
        }

        $holder = data_get($account, 'holder');

        return $holder instanceof Model
            && $holder::class === $owner::class
            && (string) $holder->getKey() === (string) $owner->getKey();
    }
}
