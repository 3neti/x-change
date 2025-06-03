<?php

namespace LBHurtado\PaymentGateway\Gateways;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Contracts\HasMerchantInterface;
use LBHurtado\PaymentGateway\Actions\TopupWalletAction;
use LBHurtado\PaymentGateway\Data\DepositResponseData;
use LBHurtado\PaymentGateway\Events\DepositConfirmed;
use Illuminate\Support\Facades\Http;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\Log;
use Brick\Money\Money;

class NetbankPaymentGateway implements PaymentGatewayInterface
{
    public function generate(string $account, Money $amount): string
    {
        $user = auth()->user();

        if (!$user instanceof HasMerchantInterface) {
            throw new \LogicException('Authenticated user must implement HasMerchantInterface to use this functionality.');
        }

        $token = $this->getAccessToken();

        $payload = [
            'merchant_name' => $user->merchant->name,
            'merchant_city' => $user->merchant->city,
            'qr_type' => $amount->isZero() ? 'Static' : 'Dynamic',
            'qr_transaction_type' => 'P2M',
            'destination_account' => $this->formatDestinationAccount($account, $user->merchant_code),
            'resolution' => 480,
            'amount' => [
                'cur' => 'PHP',
                'num' => $amount->isZero() ? '' : (string) $amount->getMinorAmount()->toInt(),
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])->post(config('disbursement.server.qr-end-point'), $payload);

        return 'data:image/png;base64,' . $response->json('qr_code');
    }

    protected function getAccessToken(): string
    {
        $credentials = base64_encode(
            config('disbursement.client.id') . ':' . config('disbursement.client.secret')
        );

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
        ])->asForm()->post(config('disbursement.server.token-end-point'), [
            'grant_type' => 'client_credentials',
        ]);

        return $response->json('access_token');
    }

    protected function formatDestinationAccount(string $account, ?string $merchantCode): string
    {
        return __(':alias:account', [
            'alias' => config('disbursement.client.alias'),
            'account' => $merchantCode ? $merchantCode[0] . substr($account, 1) : $account,
        ]);
    }

    public function confirmDeposit(array $payload): bool
    {
        $response = DepositResponseData::from($payload);
        Log::info('Processing Netbank deposit confirmation', $response->toArray());

        $user = app(config('payment-gateway.models.user'))::findByMobile($response->referenceCode);

        if (!$user && ($merchant_code = $response->merchant_details->merchant_code ?? null) && strlen($merchant_code) === 1) {
            $user = app(config('payment-gateway.models.user'))
               ->where('meta->merchant->code', $merchant_code)->first();
//            $user = User::where('meta->merchant->code', $merchant_code)->firstOrFail();
        }

        if (!$user) {
            Log::warning('No user found for reference code or merchant code.');
            return false;
        }

        $this->transferToWallet($user, $response);

        return true;
    }

    public function transferToWallet(Wallet $user, DepositResponseData $response): void
    {
        $amountFloat = $response->amount;

        $transfer = TopupWalletAction::run($user, $amountFloat);
        $transfer->deposit->meta = $response->toArray();
        $transfer->deposit->save();

        DepositConfirmed::dispatch($transfer->deposit);
    }
}
