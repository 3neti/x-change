<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\PartnerApi;

use Spatie\LaravelData\Data;

class PartnerApiCredentialData extends Data
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $mandate
     */
    public function __construct(
        public string $reference,
        public string $client_id,
        public string $client_secret,
        public string $environment,
        public array $scopes,
        public array $mandate,
    ) {}
}
