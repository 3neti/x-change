<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeEvidenceData;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeProfileData;

class SettlementEnvelopeEvidenceExtractor
{
    public function extract(
        Voucher $voucher,
        SettlementEnvelopeProfileData $profile,
        array $context = [],
    ): SettlementEnvelopeEvidenceData {
        $metadata = $this->voucherMetadata($voucher);
        $instructionsMetadata = $this->voucherInstructionsMetadata($voucher);

        $walletInfo = $context['wallet_info']
            ?? Arr::get($context, 'inputs.wallet_info')
            ?? Arr::get($metadata, 'wallet_info')
            ?? [];

        $bioFields = $context['bio_fields']
            ?? Arr::get($context, 'inputs.bio_fields')
            ?? Arr::get($metadata, 'bio_fields')
            ?? [];

        $mappedPayload = $this->mapPayloadFromFormFlow(
            mapping: Arr::get($profile->driver_config, 'form_flow_mapping.payload', []),
            context: [
                'wallet_info' => $walletInfo,
                'bio_fields' => $bioFields,
                'inputs' => $context['inputs'] ?? [],
            ],
        );

        $payload = array_filter([
            ...Arr::get($instructionsMetadata, 'settlement_payload', []),
            ...Arr::get($metadata, 'settlement_payload', []),
            ...$mappedPayload,
            ...Arr::get($context, 'payload', []),
        ], fn ($value): bool => $value !== null && $value !== '');

        $documents = $context['documents']
            ?? Arr::get($metadata, 'settlement_documents')
            ?? Arr::get($instructionsMetadata, 'settlement_documents')
            ?? [];

        $checklist = $context['checklist']
            ?? Arr::get($metadata, 'settlement_checklist')
            ?? Arr::get($instructionsMetadata, 'settlement_checklist')
            ?? [];

        return new SettlementEnvelopeEvidenceData(
            payload: $payload,
            documents: is_array($documents) ? $documents : [],
            checklist: is_array($checklist) ? $checklist : [],
            claims: $context['claims'] ?? [],
            wallet_info: is_array($walletInfo) ? $walletInfo : [],
            bio_fields: is_array($bioFields) ? $bioFields : [],
            meta: [
                'voucher_id' => $voucher->getKey(),
                'voucher_code' => $voucher->code ?? null,
                'source' => 'x-change',
            ],
        );
    }

    protected function mapPayloadFromFormFlow(array $mapping, array $context): array
    {
        $payload = [];

        foreach ($mapping as $target => $expression) {
            $payload[$target] = $this->resolveExpression((string) $expression, $context);
        }

        return array_filter($payload, fn ($value): bool => $value !== null && $value !== '');
    }

    protected function resolveExpression(string $expression, array $context): mixed
    {
        $paths = array_map('trim', explode('|', $expression));

        foreach ($paths as $path) {
            $value = Arr::get($context, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function voucherMetadata(Voucher $voucher): array
    {
        return is_array($voucher->metadata ?? null)
            ? $voucher->metadata
            : [];
    }

    protected function voucherInstructionsMetadata(Voucher $voucher): array
    {
        $instructions = $voucher->instructions ?? null;

        if (is_array($instructions)) {
            return Arr::get($instructions, 'metadata', []);
        }

        if (is_object($instructions) && isset($instructions->metadata)) {
            return is_array($instructions->metadata) ? $instructions->metadata : [];
        }

        return [];
    }
}
