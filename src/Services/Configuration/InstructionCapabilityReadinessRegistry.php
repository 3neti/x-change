<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use InvalidArgumentException;
use LBHurtado\XChange\Contracts\InstructionCapabilityContributor;
use LBHurtado\XChange\Data\Configuration\InstructionCapabilityReadinessData;

final class InstructionCapabilityReadinessRegistry
{
    /**
     * @param  iterable<InstructionCapabilityContributor>  $contributors
     */
    public function __construct(private readonly iterable $contributors) {}

    /**
     * @return array<string, InstructionCapabilityReadinessData>
     */
    public function all(): array
    {
        $capabilities = [];

        foreach ($this->contributors as $contributor) {
            foreach ($contributor->instructionCapabilities() as $capability) {
                if (isset($capabilities[$capability->key])) {
                    throw new InvalidArgumentException(sprintf(
                        'Instruction capability [%s] was contributed more than once.',
                        $capability->key,
                    ));
                }

                $capabilities[$capability->key] = $capability;
            }
        }

        ksort($capabilities);

        return $capabilities;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sanitized(): array
    {
        return array_map(
            static fn (InstructionCapabilityReadinessData $capability): array => $capability->toArray(),
            $this->all(),
        );
    }

    public function find(string $key): ?InstructionCapabilityReadinessData
    {
        return $this->all()[$key] ?? null;
    }
}
