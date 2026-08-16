<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use DomainException;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;

final readonly class RevokeCommercialRecipientDesignation
{
    public function __construct(private CommercialGovernanceJournal $journal) {}

    public function execute(string $authorityReference, string $revocationReference): CommercialRecipientDesignation
    {
        $revoked = DB::transaction(function () use ($authorityReference, $revocationReference): CommercialRecipientDesignation {
            $designation = CommercialRecipientDesignation::query()
                ->where('authority_reference', $authorityReference)
                ->lockForUpdate()
                ->firstOrFail();

            if ($designation->revoked_at !== null) {
                if ($designation->revocation_reference !== $revocationReference) {
                    throw new DomainException('Commercial Recipient Designation revocation conflicts with existing evidence.');
                }

                return $designation;
            }

            CommercialRecipientDesignation::query()
                ->whereKey($designation->getKey())
                ->update([
                    'revocation_reference' => $revocationReference,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            return $designation->refresh();
        }, attempts: 5);
        $this->journal->recordRecipientDesignation($revoked, 'commercial.recipient_designation.revoked');

        return $revoked;
    }
}
