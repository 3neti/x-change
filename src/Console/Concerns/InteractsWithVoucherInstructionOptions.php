<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Concerns;

use LBHurtado\XChange\Support\VoucherInstructionPayloadFactory;

trait InteractsWithVoucherInstructionOptions
{
    /**
     * @return array<string, mixed>
     */
    protected function voucherInstructionPayloadFromOptions(): array
    {
        $amount = (float) ($this->safeOption('amount', 0) ?: 0);

        $metadata = $this->defaultVoucherInstructionMetadata();
        $commandMeta = $this->commandMetaPayload();

        if ($commandMeta !== []) {
            $metadata = array_replace($metadata, $commandMeta);
        }

        $payload = VoucherInstructionPayloadFactory::make(
            amount: $amount,
            settlementRail: $this->normalizedSettlementRail(),
            metadata: $metadata,
            overrides: $this->voucherInstructionOverridesFromOptions(),
        );

        $issuer = $this->safeOption('issuer');
        if (is_string($issuer) && trim($issuer) !== '') {
            $payload['issuer_id'] = trim($issuer);
        }

        $wallet = $this->safeOption('wallet');
        if (is_string($wallet) && trim($wallet) !== '') {
            $payload['wallet_id'] = trim($wallet);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function voucherInstructionOverridesFromOptions(): array
    {
        $overrides = [];

        $issuer = $this->safeOption('issuer');
        if (is_string($issuer) && trim($issuer) !== '') {
            $overrides['metadata']['issuer_id'] = trim($issuer);
        }

        $wallet = $this->safeOption('wallet');
        if (is_string($wallet) && trim($wallet) !== '') {
            $overrides['metadata']['wallet_id'] = trim($wallet);
        }

        $sliceMode = $this->safeOption('slice-mode');
        if (is_string($sliceMode) && trim($sliceMode) !== '') {
            $overrides['cash']['slice_mode'] = trim($sliceMode);
        }

        $expiresAt = $this->safeOption('expires-at');
        if (is_string($expiresAt) && trim($expiresAt) !== '') {
            $overrides['ttl'] = trim($expiresAt);
        }

        $quantity = $this->safeOption('quantity', 1);
        if ($quantity !== null && $quantity !== '') {
            $overrides['count'] = (int) $quantity;
        }

        $webhook = $this->safeOption('webhook');
        if (is_string($webhook) && trim($webhook) !== '') {
            $overrides['feedback']['webhook'] = trim($webhook);
        }

        if ((bool) $this->safeOption('email', false)) {
            $overrides['feedback']['email'] = 'example@example.com';
        }

        if ((bool) $this->safeOption('sms', false)) {
            $overrides['feedback']['mobile'] = '09171234567';
        }

        if ((bool) $this->safeOption('otp', false)) {
            $overrides['inputs']['fields'][] = 'otp';
            $overrides['cash']['validation']['otp'] = true;
        }

        if ((bool) $this->safeOption('location', false)) {
            $overrides['cash']['validation']['location'] = true;
            $overrides['inputs']['fields'][] = 'location';
        }

        if ((bool) $this->safeOption('kyc', false)) {
            $overrides['inputs']['fields'][] = 'kyc';
        }

        if ((bool) $this->safeOption('selfie', false)) {
            $overrides['inputs']['fields'][] = 'selfie';
        }

        if ((bool) $this->safeOption('signature', false)) {
            $overrides['inputs']['fields'][] = 'signature';
        }

        if ((bool) $this->safeOption('withdrawable', false)) {
            $overrides['cash']['slice_mode'] ??= 'open';
            $overrides['metadata']['withdrawable'] = true;
        }

        if ((bool) $this->safeOption('divisible', false)) {
            $overrides['cash']['max_slices'] = 999;
            $overrides['metadata']['divisible'] = true;
        }

        return $overrides;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultVoucherInstructionMetadata(): array
    {
        return [
            'issuer_id' => (string) ($this->safeOption('issuer', '') ?: ''),
            'wallet_id' => (string) ($this->safeOption('wallet', '') ?: ''),
            'created_at' => now()->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
        ];
    }

    protected function normalizedSettlementRail(): ?string
    {
        $settlementRail = $this->safeOption('settlement-rail');

        if (! is_string($settlementRail) || trim($settlementRail) === '') {
            return 'INSTAPAY';
        }

        return strtoupper(trim($settlementRail));
    }

    protected function safeOption(string $name, mixed $default = null): mixed
    {
        if (method_exists($this, 'hasOption') && $this->hasOption($name)) {
            return $this->option($name);
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    protected function commandMetaPayload(): array
    {
        $meta = [];

        $idempotencyKey = $this->safeOption('idempotency-key');
        if (is_string($idempotencyKey) && trim($idempotencyKey) !== '') {
            $meta['idempotency_key'] = trim($idempotencyKey);
        }

        return $meta;
    }
}
