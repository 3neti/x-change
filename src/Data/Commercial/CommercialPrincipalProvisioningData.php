<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

final readonly class CommercialPrincipalProvisioningData
{
    public function __construct(
        public string $status,
        public bool $committed,
        public bool $created,
        public bool $accountReady,
        public string $reference,
        public string $legalName,
        public ?string $key,
        public string $authorizationReference,
    ) {}

    /**
     * @return array<string, bool|string|null>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'committed' => $this->committed,
            'created' => $this->created,
            'account_ready' => $this->accountReady,
            'reference' => $this->reference,
            'legal_name' => $this->legalName,
            'key' => $this->key,
            'authorization_reference' => $this->authorizationReference,
        ];
    }
}
