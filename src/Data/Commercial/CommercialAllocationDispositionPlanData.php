<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

use DomainException;
use LBHurtado\XProvisioning\Enums\CommercialSettlementDisposition;

final readonly class CommercialAllocationDispositionPlanData
{
    public function __construct(
        public string $policyRuleReference,
        public CommercialSettlementDisposition $disposition,
        public string $designationReference,
        public string $authorityReference,
        public string $authorityHash,
        public ?string $accountReferenceHash = null,
        public ?string $principalReferenceHash = null,
        public ?string $destinationClientFundsPositionReference = null,
    ) {
        foreach ([
            'policy rule' => $this->policyRuleReference,
            'designation' => $this->designationReference,
            'authority' => $this->authorityReference,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new DomainException("Commercial allocation disposition {$field} reference is required.");
            }
        }

        if (preg_match('/^[a-f0-9]{64}$/', $this->authorityHash) !== 1) {
            throw new DomainException('Commercial allocation disposition authority hash is invalid.');
        }

        if ($this->disposition === CommercialSettlementDisposition::InternalAccountCredit) {
            foreach ([$this->accountReferenceHash, $this->principalReferenceHash] as $hash) {
                if ($hash === null || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                    throw new DomainException('Internal Account credit requires valid Account and principal hashes.');
                }
            }

            if (blank($this->destinationClientFundsPositionReference)) {
                throw new DomainException('Internal Account credit requires a Client Funds Position.');
            }
        }

        if ($this->disposition === CommercialSettlementDisposition::RetainPayable
            && ($this->accountReferenceHash !== null
                || $this->principalReferenceHash !== null
                || $this->destinationClientFundsPositionReference !== null)) {
            throw new DomainException('Retained payables cannot contain an internal Account destination.');
        }
    }

    /** @return array<string, mixed> */
    public function evidence(): array
    {
        return [
            'policy_rule_reference' => $this->policyRuleReference,
            'disposition' => $this->disposition->value,
            'designation_reference' => $this->designationReference,
            'authority_reference' => $this->authorityReference,
            'authority_hash' => $this->authorityHash,
            'account_reference_hash' => $this->accountReferenceHash,
            'principal_reference_hash' => $this->principalReferenceHash,
            'destination_position_reference_hash' => $this->destinationClientFundsPositionReference !== null
                ? hash('sha256', $this->destinationClientFundsPositionReference)
                : null,
        ];
    }
}
