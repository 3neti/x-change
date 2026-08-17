<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Data\Funding\StandingFundingAddressBindingData;
use LBHurtado\XChange\Exceptions\StandingFundingAddressBindingTimeUnavailable;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressBindingHead;
use LBHurtado\XChange\Models\StandingFundingAddressBindingRevision;

final class StandingFundingAddressBindingResolver
{
    public function current(StandingFundingAddress $address): StandingFundingAddressBindingData
    {
        if (! Schema::hasTable('x_change_standing_funding_address_binding_heads')) {
            return $this->legacy($address);
        }

        $head = StandingFundingAddressBindingHead::query()
            ->with('currentBindingRevision.effectiveTimeCorrection')
            ->whereKey($address->getKey())
            ->first();

        if ($head?->currentBindingRevision instanceof StandingFundingAddressBindingRevision) {
            $revision = $head->currentBindingRevision;
            $effectiveAt = $this->effectiveAt($revision);

            if ($effectiveAt->isFuture()) {
                $revision = $revision->previousBindingRevision()
                    ->with('effectiveTimeCorrection')
                    ->first();
            }

            if ($revision instanceof StandingFundingAddressBindingRevision) {
                return StandingFundingAddressBindingData::fromRevision(
                    $revision,
                    $this->effectiveAt($revision),
                );
            }
        }

        return $this->legacy($address);
    }

    public function at(
        StandingFundingAddress $address,
        ?CarbonImmutable $occurredAt,
    ): StandingFundingAddressBindingData {
        if (! Schema::hasTable('x_change_standing_funding_address_binding_revisions')) {
            return $this->legacy($address);
        }

        $revisionCount = StandingFundingAddressBindingRevision::query()
            ->whereBelongsTo($address)
            ->count();

        if ($revisionCount === 0) {
            return $this->legacy($address);
        }

        if ($occurredAt === null) {
            if ($revisionCount > 1) {
                throw StandingFundingAddressBindingTimeUnavailable::forCutoverAddress();
            }

            return StandingFundingAddressBindingData::fromRevision(
                StandingFundingAddressBindingRevision::query()
                    ->whereBelongsTo($address)
                    ->sole(),
            );
        }

        $revision = StandingFundingAddressBindingRevision::query()
            ->with('effectiveTimeCorrection')
            ->whereBelongsTo($address)
            ->get()
            ->filter(fn (StandingFundingAddressBindingRevision $revision): bool => $this
                ->effectiveAt($revision)->lessThanOrEqualTo($occurredAt->utc()))
            ->sortByDesc(fn (StandingFundingAddressBindingRevision $revision): string => implode(':', [
                $this->effectiveAt($revision)->format('Y-m-d H:i:s.u'),
                str_pad((string) $revision->binding_version, 10, '0', STR_PAD_LEFT),
            ]))
            ->first();

        return $revision instanceof StandingFundingAddressBindingRevision
            ? StandingFundingAddressBindingData::fromRevision($revision, $this->effectiveAt($revision))
            : $this->legacy($address);
    }

    public function findCurrentByBindingKey(string $bindingKey): ?StandingFundingAddress
    {
        if (! Schema::hasTable('x_change_standing_funding_address_binding_heads')) {
            return null;
        }

        $head = StandingFundingAddressBindingHead::query()
            ->whereHas('currentBindingRevision', fn ($query) => $query
                ->where('binding_key', $bindingKey))
            ->first();

        return $head?->standingFundingAddress()->first();
    }

    private function legacy(StandingFundingAddress $address): StandingFundingAddressBindingData
    {
        return new StandingFundingAddressBindingData(
            accountReference: $address->account_reference,
            bindingKey: $address->binding_key,
            destinationSnapshot: $address->destination_snapshot_ciphertext,
            destinationFingerprint: $address->destination_fingerprint,
            revisionId: null,
            revisionReference: null,
            version: 0,
            effectiveAt: CarbonImmutable::parse($address->activated_at ?? $address->created_at),
        );
    }

    private function effectiveAt(StandingFundingAddressBindingRevision $revision): CarbonImmutable
    {
        return $revision->effectiveTimeCorrection?->corrected_effective_at
            ?? $revision->effective_at;
    }
}
