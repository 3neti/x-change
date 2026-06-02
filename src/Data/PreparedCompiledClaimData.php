<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

final readonly class PreparedCompiledClaimData
{
    public function __construct(
        public string $code,
        public int|string $voucherId,
        public array $inputs,
    ) {}

    public static function fromSessionPayload(array $payload): ?self
    {
        if (
            ! isset($payload['code'], $payload['voucher_id'], $payload['inputs'])
            || ! is_string($payload['code'])
            || ! is_array($payload['inputs'])
        ) {
            return null;
        }

        return new self(
            code: strtoupper(trim($payload['code'])),
            voucherId: $payload['voucher_id'],
            inputs: $payload['inputs'],
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'voucher_id' => $this->voucherId,
            'inputs' => $this->inputs,
        ];
    }
}
