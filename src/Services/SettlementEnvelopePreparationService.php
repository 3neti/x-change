<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeProfileData;
use Symfony\Component\Yaml\Yaml;

class SettlementEnvelopePreparationService
{
    public function prepare(
        Voucher $voucher,
        string $gate = 'settleable',
        array $context = [],
    ): SettlementEnvelopeProfileData {
        $metadata = $this->voucherMetadata($voucher);
        $instructionsMetadata = $this->voucherInstructionsMetadata($voucher);

        $flowType = $context['flow_type']
            ?? Arr::get($instructionsMetadata, 'flow_type')
            ?? Arr::get($metadata, 'flow_type');

        $driver = $this->resolveDriver($voucher, $context, $metadata, $instructionsMetadata);

        $requiresEnvelope = $this->requiresEnvelope(
            flowType: $flowType,
            driver: $driver,
            context: $context,
            metadata: $metadata,
            instructionsMetadata: $instructionsMetadata,
        );

        if (! $requiresEnvelope) {
            return SettlementEnvelopeProfileData::notRequired([
                'voucher_id' => $voucher->getKey(),
                'voucher_code' => $voucher->code ?? null,
                'reason' => 'voucher_does_not_require_settlement_envelope',
            ]);
        }

        $driverConfig = $this->loadDriver($driver);

        $requiredPayloadFields = collect(Arr::get($driverConfig, 'schema.payload.fields', []))
            ->filter(fn (array $field): bool => (bool) ($field['required'] ?? false))
            ->keys()
            ->values()
            ->all();

        $documentTypes = Arr::get($driverConfig, 'schema.documents', []);
        $checklistItems = collect($driverConfig['checklist'] ?? [])
            ->keyBy('id')
            ->all();

        $gateConditions = Arr::get($driverConfig, "gates.{$gate}.conditions", []);

        return new SettlementEnvelopeProfileData(
            requires_envelope: true,
            driver: $driver,
            gate: $gate,
            envelope_id: $context['settlement_envelope_id']
            ?? Arr::get($instructionsMetadata, 'settlement_envelope_id')
            ?? Arr::get($instructionsMetadata, 'envelope_id')
            ?? Arr::get($metadata, 'settlement_envelope_id')
            ?? Arr::get($metadata, 'envelope_id'),
            flow_type: $flowType,
            driver_config: $driverConfig,
            required_payload_fields: $requiredPayloadFields,
            document_types: $documentTypes,
            checklist_items: $checklistItems,
            gate_conditions: $gateConditions,
            meta: [
                'voucher_id' => $voucher->getKey(),
                'voucher_code' => $voucher->code ?? null,
                'driver_path' => $this->driverPath($driver),
            ],
        );
    }

    protected function resolveDriver(
        Voucher $voucher,
        array $context,
        array $metadata,
        array $instructionsMetadata,
    ): string {
        return (string) (
            $context['driver']
            ?? $context['settlement_driver']
            ?? Arr::get($instructionsMetadata, 'settlement_driver')
            ?? Arr::get($instructionsMetadata, 'envelope_driver')
            ?? Arr::get($metadata, 'settlement_driver')
            ?? Arr::get($metadata, 'envelope_driver')
            ?? config('x-change.settlement.default_driver')
            ?? 'philhealth-bst'
        );
    }

    protected function requiresEnvelope(
        mixed $flowType,
        string $driver,
        array $context,
        array $metadata,
        array $instructionsMetadata,
    ): bool {
        if (array_key_exists('requires_envelope', $context)) {
            return (bool) $context['requires_envelope'];
        }

        return $flowType === 'settlement'
            || $driver !== ''
            || Arr::has($context, 'settlement_envelope_id')
            || Arr::has($context, 'envelope_id')
            || Arr::has($metadata, 'settlement_envelope_id')
            || Arr::has($metadata, 'envelope_id')
            || Arr::has($instructionsMetadata, 'settlement_envelope_id')
            || Arr::has($instructionsMetadata, 'envelope_id');
    }

    protected function loadDriver(string $driver): array
    {
        $path = $this->driverPath($driver);

        if (! File::exists($path)) {
            throw new \RuntimeException("Settlement envelope driver [{$driver}] was not found at [{$path}].");
        }

        return Yaml::parseFile($path) ?? [];
    }

    protected function driverPath(string $driver): string
    {
        $configuredBasePath = config('x-change.settlement.drivers_path');

        $candidates = array_filter([
            $configuredBasePath
                ? rtrim((string) $configuredBasePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$driver.'.yaml'
                : null,

            config_path('envelope-drivers'.DIRECTORY_SEPARATOR.$driver.'.yaml'),

            dirname(__DIR__, 2)
            .DIRECTORY_SEPARATOR.'config'
            .DIRECTORY_SEPARATOR.'envelope-drivers'
            .DIRECTORY_SEPARATOR.$driver.'.yaml',
        ]);

        foreach ($candidates as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return (string) reset($candidates);
    }

    protected function voucherMetadata(Voucher $voucher): array
    {
        $raw = $voucher->getAttributes()['metadata'] ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    protected function voucherInstructionsMetadata(Voucher $voucher): array
    {
        $raw = $voucher->getAttributes()['instructions'] ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded)
                ? Arr::get($decoded, 'metadata', [])
                : [];
        }

        if (is_array($raw)) {
            return Arr::get($raw, 'metadata', []);
        }

        return [];
    }
}
