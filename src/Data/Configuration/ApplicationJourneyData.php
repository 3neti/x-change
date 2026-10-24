<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Configuration;

use InvalidArgumentException;
use LBHurtado\XChange\Enums\ApplicationInstructionContract;
use LBHurtado\XChange\Enums\ApplicationJourneyKind;

final readonly class ApplicationJourneyData
{
    public function __construct(
        public string $key,
        public string $label,
        public ApplicationJourneyKind $kind,
        public ApplicationInstructionContract $instructionContract,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $key) !== 1 || trim($label) === '') {
            throw new InvalidArgumentException('Application journeys require a valid key and label.');
        }

        if ($instructionContract !== $kind->instructionContract()) {
            throw new InvalidArgumentException("Journey [{$key}] has an incompatible instruction contract.");
        }
    }

    /** @param array<string, mixed> $attributes */
    public static function fromArray(array $attributes): self
    {
        self::assertFields($attributes);

        $kind = ApplicationJourneyKind::tryFrom((string) $attributes['kind'])
            ?? throw new InvalidArgumentException('Application journey kind is not supported.');
        $contract = ApplicationInstructionContract::tryFrom((string) $attributes['instruction_contract'])
            ?? throw new InvalidArgumentException('Application journey instruction contract is not supported.');

        return new self(
            key: $attributes['key'],
            label: $attributes['label'],
            kind: $kind,
            instructionContract: $contract,
        );
    }

    /** @param array<string, mixed> $attributes */
    private static function assertFields(array $attributes): void
    {
        if (array_diff(array_keys($attributes), ['key', 'label', 'kind', 'instruction_contract']) !== []
            || ! is_string($attributes['key'] ?? null)
            || ! is_string($attributes['label'] ?? null)
            || ! is_string($attributes['kind'] ?? null)
            || ! is_string($attributes['instruction_contract'] ?? null)) {
            throw new InvalidArgumentException('Application journey fields are invalid.');
        }
    }
}
