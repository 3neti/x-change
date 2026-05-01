<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Arr;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Services\VoucherAccessService;
use LBHurtado\XChange\Services\VoucherPaymentWebhookParserManager;
use RuntimeException;

class HandleVoucherPaymentWebhook
{
    public function __construct(
        protected VoucherPaymentWebhookParserManager $parsers,
        protected VoucherAccessService $vouchers,
        protected CollectVoucherFunds $collect,
    ) {}

    public function handle(string $provider, array $payload): VoucherPaymentResultData
    {
        $parser = $this->parsers->driver($provider);

        $voucherCode = $parser->voucherCode($payload);

        if (! is_string($voucherCode) || trim($voucherCode) === '') {
            throw new RuntimeException('Voucher payment webhook payload is missing voucher code.');
        }

        $voucher = $this->vouchers->findByCodeOrFail($voucherCode);
        $parsed = $parser->parse($payload);

        return $this->collect->handle($voucher, [
            'amount' => $parsed->amount,
            'currency' => $parsed->currency,
            'status' => $parsed->status,
            'provider' => $parsed->provider ?: $provider,
            'provider_reference' => $parsed->provider_reference,
            'provider_transaction_id' => $parsed->provider_transaction_id,
            'payer' => $parsed->payer,
            'meta' => array_merge((array) $parsed->meta, [
                'source' => 'webhook',
                'webhook_provider' => $provider,
                'raw' => Arr::except($payload, ['secret', 'signature']),
            ]),
            'idempotency_key' => Arr::get($payload, 'idempotency_key')
                ?? Arr::get($payload, 'event_id')
                    ?? Arr::get($payload, 'webhook_id'),
        ]);
    }
}
