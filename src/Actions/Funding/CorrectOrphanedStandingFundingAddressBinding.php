<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressBindingHead;
use LBHurtado\XChange\Models\StandingFundingAddressBindingRevision;
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
        $collisionId = StandingFundingAddress::query()
            ->where('funding_address_hash', $fundingAddressHash)
            ->value('id');

        if (! is_numeric($collisionId)) {
            return null;
        }

        $lock = Cache::lock(
            'x-change:standing-funding-address:'.$collisionId,
            max(1, (int) config('x-change.funding.standing_addresses.lock_seconds', 120)),
        );
        $corrected = $lock->block(5, fn (): ?StandingFundingAddress => DB::transaction(function () use (
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

            $head = StandingFundingAddressBindingHead::query()
                ->whereKey($collision->getKey())
                ->lockForUpdate()
                ->first();
            $previous = $head?->currentBindingRevision()->first();

            if (! $previous instanceof StandingFundingAddressBindingRevision) {
                $previous = StandingFundingAddressBindingRevision::query()
                    ->whereBelongsTo($collision)
                    ->orderByDesc('binding_version')
                    ->lockForUpdate()
                    ->first();
            }

            $effectiveAt = now();

            if ($previous instanceof StandingFundingAddressBindingRevision
                && $effectiveAt->lessThanOrEqualTo($previous->effective_at)) {
                $effectiveAt = $previous->effective_at->addMicrosecond();
            }

            $revision = StandingFundingAddressBindingRevision::query()->create([
                'standing_funding_address_id' => $collision->getKey(),
                'binding_version' => ($previous?->binding_version ?? 0) + 1,
                'previous_binding_revision_id' => $previous?->getKey(),
                'account_reference_ciphertext' => $accountReference,
                'account_reference_hash' => hash('sha256', $accountReference),
                'binding_key' => $bindingKey,
                'destination_snapshot_ciphertext' => $destination === null
                    ? null
                    : FundingDestinationSnapshot::fromData($destination),
                'destination_fingerprint' => $destination?->fingerprint,
                'reason' => 'unused_orphan_binding_correction',
                'evidence_snapshot' => [
                    'schema' => 'x-change.funding-standing-address-binding-revision-evidence.v1',
                    'standing_funding_address_reference' => $collision->reference,
                    'role' => 'unused_orphan_binding_correction',
                    'account_reference_hash' => hash('sha256', $accountReference),
                ],
                'evidence_hash' => hash('sha256', implode('|', [
                    $collision->reference,
                    $bindingKey,
                    hash('sha256', $accountReference),
                ])),
                'effective_at' => $effectiveAt,
            ]);
            $head ??= new StandingFundingAddressBindingHead([
                'standing_funding_address_id' => $collision->getKey(),
            ]);
            $head->current_binding_revision_id = $revision->getKey();
            $head->saveQuietly();

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
        }, attempts: 5));

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
