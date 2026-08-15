<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;

class PartnerPayCodeReadModel
{
    public function __construct(
        protected VoucherLifecycleServiceContract $vouchers,
    ) {}

    /** @return array<string, mixed> */
    public function find(string $code, Model $issuer): array
    {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->where('owner_type', $issuer->getMorphClass())
            ->where('owner_id', (string) $issuer->getKey())
            ->firstOrFail();
        $detail = (array) $this->vouchers->show((string) $voucher->getKey());
        $currency = strtoupper((string) data_get($detail, 'currency', 'PHP'));

        return [
            'schema' => 'x-change.partner-pay-code.v1',
            'code' => (string) data_get($detail, 'code'),
            'amount_minor' => Money::of((string) data_get($detail, 'amount', 0), $currency)
                ->getMinorAmount()
                ->toInt(),
            'currency' => $currency,
            'status' => data_get($detail, 'operational_status'),
            'capability' => data_get($detail, 'capability'),
            'party' => data_get($detail, 'party'),
            'timing' => [
                'created_at' => data_get($detail, 'created_at'),
                'starts_at' => data_get($detail, 'starts_at'),
                'expires_at' => data_get($detail, 'expires_at'),
                'redeemed_at' => data_get($detail, 'redeemed_at'),
            ],
            'claimed' => (bool) data_get($detail, 'claimed', false),
            'fully_claimed' => (bool) data_get($detail, 'fully_claimed', false),
            'attention' => data_get($detail, 'attention'),
        ];
    }
}
