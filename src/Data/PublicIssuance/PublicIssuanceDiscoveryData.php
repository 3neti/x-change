<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\PublicIssuance;

use Spatie\LaravelData\Data;

final class PublicIssuanceDiscoveryData extends Data
{
    /**
     * @param  array{estimate_url: ?string, handoff_url: ?string}  $api
     * @param  array{voucher_type: string, currencies: array<int, string>, minimum_principal_minor: int, maximum_principal_minor: int}  $supported
     * @param  array{basis: string, bank_transfer: bool, qr_ph: bool, pay_code: bool}  $funding
     * @param  array{read_only_contracts: bool, payment_required_before_issuance: bool, principal_selection: bool}  $safety
     */
    public function __construct(
        public string $schema,
        public string $service,
        public bool $available,
        public string $issuer_model,
        public bool $registration_required,
        public bool $creates_order,
        public string $canonical_url,
        public string $pricing_url,
        public array $api,
        public array $supported,
        public array $funding,
        public array $safety,
    ) {}
}
