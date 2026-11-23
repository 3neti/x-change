<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Banking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use RuntimeException;

final class SelectDefaultBankingAccount
{
    public function handle(Model $owner, int $assignmentId): FundingDestinationPreference
    {
        return DB::transaction(function () use ($owner, $assignmentId): FundingDestinationPreference {
            $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
                ->with('providerAccountLink')->find($assignmentId);
            $link = $assignment?->providerAccountLink;
            if ($assignment === null || $link === null || ! $link->isReady()
                || $link->owner_type !== $owner->getMorphClass()
                || (string) $link->owner_id !== (string) $owner->getKey()
                || $link->provider !== $assignment->provider
                || (array_intersect(['funding', 'disbursement'], $link->capabilities ?? []) === [])) {
                throw ValidationException::withMessages(['account' => 'This account is not ready for funding or issuance.']);
            }
            if (! in_array('funding', $link->capabilities ?? [], true)) {
                try {
                    app(AssignedBankTreasuryConnection::class)->resolve($owner, (int) $link->getKey());
                } catch (RuntimeException) {
                    throw ValidationException::withMessages(['account' => 'This account Treasury connection is unavailable.']);
                }
            }

            $preference = FundingDestinationPreference::query()->firstOrNew([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'provider_code' => $assignment->provider,
            ]);
            $preference->forceFill([
                'mode' => 'dedicated',
                'provider_account_link_id' => $link->getKey(),
                'version' => $preference->exists ? $preference->version + 1 : 1,
                'changed_by_type' => $owner->getMorphClass(),
                'changed_by_id' => (string) $owner->getKey(),
            ])->save();

            return $preference->refresh();
        }, attempts: 3);
    }
}
