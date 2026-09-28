<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Services\Campaigns\CampaignCommercialSponsorshipResolver;
use RuntimeException;

final readonly class PayCodeFundingPrincipalResolver
{
    private const SCHEMA = 'x-change.pay-code-funding-principal.v1';

    public function __construct(
        private CampaignCommercialSponsorshipResolver $commercialSponsorships,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
    ) {}

    public function forAuthorization(
        CampaignWorksheetAuthorization $authorization,
        Model $voucherOwner,
    ): Model {
        return $this->commercialSponsorships->resolve($authorization, $voucherOwner)
            ?? $voucherOwner;
    }

    /** @return array<string, mixed> */
    public function reservationContext(
        CampaignWorksheetAuthorization $authorization,
        Model $voucherOwner,
        Model $fundingPrincipal,
    ): array {
        if ($fundingPrincipal->is($voucherOwner)) {
            return [];
        }

        $facts = [
            'authorization_reference' => (string) $authorization->reference,
            'instruction_blueprint_hash' => (string) $authorization->instruction_blueprint_hash,
            'voucher_owner_type' => $voucherOwner->getMorphClass(),
            'voucher_owner_id' => (string) $voucherOwner->getKey(),
            'funding_principal_type' => $fundingPrincipal->getMorphClass(),
            'funding_principal_id' => (string) $fundingPrincipal->getKey(),
            'funding_principal_reference' => $this->principalReferences->resolve($fundingPrincipal),
        ];

        return [
            'schema' => self::SCHEMA,
            ...$facts,
            'evidence_hash' => $this->hash($facts),
        ];
    }

    public function forVoucher(Voucher $voucher): Model
    {
        $owner = $voucher->owner;

        if (! $owner instanceof Model) {
            throw new RuntimeException("Pay Code [{$voucher->code}] has no Account owner.");
        }

        $context = data_get($voucher->metadata, 'treasury.pay_code_reservation.funding_principal');

        if (! is_array($context)) {
            return $owner;
        }

        if (data_get($context, 'schema') !== self::SCHEMA) {
            throw new RuntimeException("Pay Code [{$voucher->code}] has an unsupported funding principal schema.");
        }

        $authorization = CampaignWorksheetAuthorization::query()
            ->where('reference', trim((string) data_get($context, 'authorization_reference')))
            ->first();

        if (! $authorization instanceof CampaignWorksheetAuthorization) {
            throw new RuntimeException("Pay Code [{$voucher->code}] has no authoritative funding authorization.");
        }

        $principal = $this->forAuthorization($authorization, $owner);
        $facts = [
            'authorization_reference' => (string) $authorization->reference,
            'instruction_blueprint_hash' => (string) $authorization->instruction_blueprint_hash,
            'voucher_owner_type' => $owner->getMorphClass(),
            'voucher_owner_id' => (string) $owner->getKey(),
            'funding_principal_type' => $principal->getMorphClass(),
            'funding_principal_id' => (string) $principal->getKey(),
            'funding_principal_reference' => $this->principalReferences->resolve($principal),
        ];
        $expectedHash = trim((string) data_get($context, 'evidence_hash'));

        if (
            $expectedHash === ''
            || ! hash_equals($expectedHash, $this->hash($facts))
            || ! hash_equals(
                (string) data_get($context, 'instruction_blueprint_hash'),
                (string) $authorization->instruction_blueprint_hash,
            )
        ) {
            throw new RuntimeException("Pay Code [{$voucher->code}] funding principal evidence is invalid.");
        }

        return $principal;
    }

    /** @param array<string, mixed> $facts */
    private function hash(array $facts): string
    {
        return hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
