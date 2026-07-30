<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class SystemPrincipalProvisioningData
{
    public function __construct(
        public string $status,
        public bool $committed,
        public bool $created,
        public bool $accountReady,
        public string $model,
        public string $identifierColumn,
        public string $identifier,
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
            'model' => $this->model,
            'identifier_column' => $this->identifierColumn,
            'identifier' => $this->identifier,
            'key' => $this->key,
            'authorization_reference' => $this->authorizationReference,
        ];
    }
}
