<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

final readonly class CompiledClaimSubmissionData
{
    public function __construct(
        public string $code,
        public array $inputs,
    ) {}

    public static function fromValidated(array $validated): self
    {
        return new self(
            code: strtoupper(trim($validated['code'])),
            inputs: $validated['inputs'],
        );
    }

    public function toSessionPayload(): array
    {
        return [
            'code' => $this->code,
            'inputs' => $this->inputs,
        ];
    }
}
