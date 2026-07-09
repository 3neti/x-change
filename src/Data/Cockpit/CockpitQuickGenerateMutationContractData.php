<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateMutationContractData extends Data
{
    /**
     * @param  array<int, CockpitQuickGenerateMutationContractGateData>  $gates
     * @param  array<int, string>  $allowed_methods
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.quick-generate-mutation.v1',
        public readonly string $status = 'not_wired',
        public readonly string $authorization = 'not-loaded',
        public readonly string $route = 'not-loaded',
        public readonly ?string $route_url = null,
        public readonly string $request_adapter = 'not-loaded',
        public readonly string $issuance_owner = 'not-loaded',
        public readonly string $idempotency = 'not-loaded',
        public readonly string $response_contract = 'not-loaded',
        public readonly bool $runtime_enabled = false,
        public readonly array $gates = [],
        public readonly array $allowed_methods = ['GET'],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
