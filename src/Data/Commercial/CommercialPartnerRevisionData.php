<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class CommercialPartnerRevisionData extends Data
{
    /** @param array<string, scalar|array|null> $terms */
    public function __construct(
        public readonly string $reference,
        public readonly string $displayName,
        public readonly ?string $legalName,
        public readonly ?string $externalReference,
        public readonly string $attributionBasis,
        public readonly string $authorizationReference,
        public readonly array $terms = [],
    ) {
        foreach ([
            'reference' => $this->reference,
            'display name' => $this->displayName,
            'attribution basis' => $this->attributionBasis,
            'authorization reference' => $this->authorizationReference,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Commercial Partner {$field} is required.");
            }
        }
    }
}
