<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use InvalidArgumentException;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;

final class VoucherValueFlowPolicy
{
    public function source(Voucher $voucher): ?string
    {
        return $this->address($voucher, 'source');
    }

    public function destination(Voucher $voucher): ?string
    {
        return $this->address($voucher, 'destination');
    }

    private function address(Voucher $voucher, string $role): ?string
    {
        if ($voucher->voucher_type === VoucherType::REDEEMABLE
            && data_get($voucher->metadata, 'instructions.execution.driver') !== 'stored_value') {
            return null;
        }

        $schema = data_get($voucher->metadata, 'instructions.execution.schema', 'voucher.execution.v1');
        if (! in_array($schema, ['voucher.execution.v1', 'voucher.execution.v2'], true)) {
            throw new InvalidArgumentException('Unsupported execution schema.');
        }
        if ($schema === 'voucher.execution.v2' && data_get($voucher->metadata, 'instructions.execution.metadata.value_flow') !== null) {
            throw new InvalidArgumentException('V2 value_flow must not appear in legacy metadata.');
        }
        $flow = data_get($voucher->metadata, $schema === 'voucher.execution.v2'
            ? 'instructions.execution.value_flow'
            : 'instructions.execution.metadata.value_flow');

        if ($flow === null) {
            return null;
        }

        if (! is_array($flow)) {
            throw new InvalidArgumentException('Voucher value_flow must be an object.');
        }

        if (array_diff(array_keys($flow), ['source', 'destination']) !== []) {
            throw new InvalidArgumentException('Voucher value_flow supports only source and destination addresses.');
        }

        $address = $flow[$role] ?? null;

        if ($address === null) {
            return null;
        }

        if (! is_string($address) || trim($address) === '') {
            throw new InvalidArgumentException("Voucher value_flow.{$role} must be an address.");
        }

        return trim($address);
    }
}
