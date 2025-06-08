<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Generate;

use LBHurtado\PaymentGateway\Data\Netbank\Common\PayloadAmountData;
use LBHurtado\PaymentGateway\Contracts\MerchantInterface;
use Spatie\LaravelData\Data;
use Brick\Money\Money;

class GeneratePayloadData extends Data
{
    public function __construct(
        public string            $merchant_name,
        public string            $merchant_city,
        public string            $qr_type,
        public string            $qr_transaction_type,
        public string            $destination_account,
        public int               $resolution,
        public PayloadAmountData $amount,
    ){}

    public static function fromUserAccountAmount(MerchantInterface $user, string $account, Money $amount): self
    {
        return new self(
            merchant_name: $user->merchant->name,
            merchant_city: $user->merchant->city,
            qr_type: $amount->isZero() ? 'Static' : 'Dynamic',
            qr_transaction_type: 'P2M',
            destination_account: self::formatDestinationAccount($account, $user->merchant->code),
            resolution: 480,
            amount: PayloadAmountData::fromMoney($amount),
        );
    }

    protected static function formatDestinationAccount(string $account, ?string $merchantCode): string
    {
        return __(':alias:account', [
            'alias' => config('disbursement.client.alias'),
            'account' => $merchantCode ? $merchantCode[0] . substr($account, 1) : $account,
        ]);
    }
}
