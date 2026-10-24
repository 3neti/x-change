<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Services\Claim\ClaimValidationDecisionJournal;
use RuntimeException;

final readonly class CampaignDirectTransferAuthority
{
    public function __construct(private ClaimValidationDecisionJournal $decisions) {}

    public function assertAuthorized(ExecutionContextData $context): void
    {
        $authorizationReference = $context->correlation['authorization_reference'] ?? null;
        $fulfillmentReference = $context->correlation['fulfillment_reference'] ?? null;
        $idempotencyKey = $context->correlation['idempotency_key'] ?? null;
        $fulfillment = is_string($fulfillmentReference)
            ? CampaignWorksheetFulfillment::query()->with(['authorization.worksheet', 'row'])
                ->where('reference', $fulfillmentReference)->first()
            : null;
        $authorization = $fulfillment?->authorization;
        $worksheet = $authorization?->worksheet;
        $beneficiary = (array) ($fulfillment?->row?->beneficiary_ciphertext ?? []);
        $claim = (array) data_get($context->meta, 'claim', []);

        if ($context->voucher === null
            || $fulfillment === null
            || $authorization === null
            || $worksheet === null
            || $authorization->status !== 'authorized'
            || $authorization->reference !== $authorizationReference
            || $worksheet->fulfillment_mode !== 'direct_bank_transfer'
            || data_get($worksheet->metadata, 'lifecycle.automatic_fulfillment') !== true
            || data_get($worksheet->metadata, 'lifecycle.live_provider_authorized') !== true
            || data_get($worksheet->metadata, 'lifecycle.live_transfer_confirmed') !== true
            || $fulfillment->mode !== 'direct_bank_transfer'
            || $fulfillment->status !== 'executing'
            || $fulfillment->pay_code !== $context->voucherCode
            || $context->voucher->code !== $context->voucherCode
            || data_get($context->voucher->metadata, 'treasury.pay_code_reservation.status') !== 'reserved'
            || (int) data_get($context->voucher->metadata, 'treasury.pay_code_reservation.amount_minor') !== (int) $fulfillment->row?->amount_minor
            || data_get($context->voucher->metadata, 'instructions.metadata.custom.campaign.authorization_reference') !== $authorizationReference
            || data_get($context->voucher->metadata, 'instructions.metadata.custom.campaign.fulfillment_reference') !== $fulfillmentReference
            || data_get($context->voucher->metadata, 'instructions.metadata.custom.campaign.claim_activation') !== 'provider_rejection'
            || data_get($context->voucher->metadata, 'instructions.validation.otp.required') !== true
            || $idempotencyKey !== 'campaign-direct-transfer:'.$fulfillmentReference
            || data_get($claim, '_meta.idempotency_key') !== $idempotencyKey
            || ! $this->sameValue(data_get($claim, 'mobile'), $beneficiary['mobile'] ?? null)
            || ! $this->sameValue(data_get($claim, 'bank_account.bank_code'), $beneficiary['bank_code'] ?? null)
            || ! $this->sameValue(data_get($claim, 'bank_account.account_number'), $beneficiary['bank_account'] ?? null)) {
            throw new RuntimeException('Approved campaign direct-transfer authority or frozen destination is invalid.');
        }

        $this->decisions->record($context->voucher, [
            'mobile' => data_get($claim, 'mobile'),
            '_meta' => ['idempotency_key' => $idempotencyKey],
        ], 'passed', [[
            'field' => 'campaign_authorization',
            'code' => 'approved_direct_transfer',
            'severity' => 'info',
        ]]);
    }

    private function sameValue(mixed $actual, mixed $expected): bool
    {
        return is_scalar($actual)
            && is_scalar($expected)
            && trim((string) $actual) !== ''
            && hash_equals(trim((string) $expected), trim((string) $actual));
    }
}
