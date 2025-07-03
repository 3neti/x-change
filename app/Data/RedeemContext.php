<?php

namespace App\Data;

use Illuminate\Support\Facades\Context;
use Spatie\LaravelData\Data;

class RedeemContext extends Data
{
    public function __construct(
        public string $voucherCode,
        public ?string $mobile = null,
        public ?string $bankCode = null,
        public ?string $accountNumber = null,
        public ?string $signature = null,
        public array $inputs = [],
        public ?string $haltedAt = null,
        public bool $success = true,
    ) {}

    public static function fromContext(): static
    {
        return new static(
            voucherCode: Context::get('voucherCode'),
            mobile: Context::get('mobile'),
            bankCode: Context::get('bankCode'),
            accountNumber: Context::get('accountNumber'),
            signature: Context::get('signature'),
            inputs: Context::get('inputs', []),
            haltedAt: Context::get('haltedAt'),
            success: Context::get('success', true),
        );
    }

    public function isHaltedAt(string $pipe): bool
    {
        return $this->haltedAt === $pipe;
    }

    public function markHaltedAt(string $pipe): void
    {
        Context::add('haltedAt', $pipe);
        Context::add('success', false);
        $this->haltedAt = $pipe;
        $this->success = false;
    }

    public function clearHalt(): void
    {
        Context::add('haltedAt', null);
        Context::add('success', true);
        $this->haltedAt = null;
        $this->success = true;
    }

    public function toContext(): void
    {
        Context::add('voucherCode', $this->voucherCode);
        Context::add('mobile', $this->mobile);
        Context::add('bankCode', $this->bankCode);
        Context::add('accountNumber', $this->accountNumber);
        Context::add('signature', $this->signature);
        Context::add('inputs', $this->inputs);
        Context::add('haltedAt', $this->haltedAt);
        Context::add('success', $this->success);
    }
}
