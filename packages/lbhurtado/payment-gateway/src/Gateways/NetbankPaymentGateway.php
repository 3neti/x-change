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

use LBHurtado\PaymentGateway\Data\GatewayResponseData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

use Bavix\Wallet\Models\Transaction;

use LBHurtado\PaymentGateway\Events\DisbursementConfirmed;
use Illuminate\Validation\Rule;
use LBHurtado\PaymentGateway\Support\Address;

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

    protected function transferToWallet(Wallet $user, DepositResponseData $response): void
    {
        $amountFloat = $response->amount;

        $transfer = TopupWalletAction::run($user, $amountFloat);
        $transfer->deposit->meta = $response->toArray();
        $transfer->deposit->save();

        DepositConfirmed::dispatch($transfer->deposit);
    }

    public function disburse(Wallet $user, array $validated): GatewayResponseData|bool
    {
        // Parse the transaction amount into minor units (e.g., cents)
        $credits = Money::of(Arr::get($validated, 'amount'), 'PHP');

        // Begin a database transaction
        DB::beginTransaction();

        try {
            // Step 1: Withdraw the amount from the user's wallet
            $transaction = $user->withdraw(
                $credits->getMinorAmount()->toInt(),
                [],
                false
            );

            // Step 2: Prepare the disbursement payload
            $payload = [
                'reference_id'         => $reference = Arr::get($validated, 'reference'),
                'settlement_rail'      => Arr::get($validated, 'via'),
                'amount'               => $this->getAmountArray($validated),
                'source_account_number' => config('disbursement.source.account_number'),
                'sender'               => config('disbursement.source.sender'),
                'destination_account'  => $this->getDestinationAccount($validated),
                'recipient'            => $this->getRecipient($validated),
            ];

            // Log the disbursement payload
            Log::info('NetbankPaymentGateway@disburse', compact('payload'));

            // Step 3: Send the disbursement request to the Netbank API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type'  => 'application/json',
            ])->post(config('disbursement.server.end-point'), $payload);

            // Step 4: Handle successful disbursement
            if ($response->successful()) {
                // Attach metadata to the transaction
                $transaction->meta = [
                    'operationId' => $response->json('transaction_id'),
                    'request'     => [
                        'user_id' => $user->getKey(),
                        'payload' => $payload
                    ],
                ];
                $transaction->save();

                // Commit the database transaction
                DB::commit();

                // Create and return response data object
                return GatewayResponseData::from(
                    array_merge(['uuid' => $transaction->uuid], $response->json())
                );
            }

            // Step 5: Handle failure response from the API
            Log::warning('Netbank disbursement failed', ['body' => $response->body()]);
            DB::rollBack();
            return false;

        } catch (\Throwable $e) {
            // Step 6: Handle errors and rollback transaction on failure
            Log::error('Netbank disbursement error', ['error' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    protected function rules(): array
    {
        $min = config('disbursement.min');
        $min_rule = 'min:' . $min;
        $max = config('disbursement.max');
        $max_rule = 'max:' . $max;
        $settlement_rails = config('disbursement.settlement_rails');

        return [
            'reference' => ['required', 'string', 'min:2', 'unique:references,code'],
            'bank' => ['required', 'string'],
            'account_number' => ['required', 'string'],
            'via' => ['required', 'string', Rule::in($settlement_rails)],
            'amount' => ['required', 'integer', $min_rule, $max_rule],
        ];
    }

    protected function getDestinationAccount(array $validated): array
    {
        return [
            'bank_code'      => Arr::get($validated, 'bank'),
            'account_number' => Arr::get($validated, 'account_number'),
        ];
    }

    protected function getRecipient(array $validated): array
    {
        return [
            'name'    => Arr::get($validated, 'account_number'), // Or fetch user-provided "name"
            'address' => Address::generate(),                   // Address generation logic
        ];
    }

    protected function getAmountArray(array $validated): array
    {
        // Parse the amount into minor units (e.g., cents)
        $minor = Money::of(Arr::get($validated, 'amount'), 'PHP')
            ->getMinorAmount()
            ->toInt();

        // Add a small variance (randomized amount) for operational reasons
        $variance = rand(config('disbursement.variance.min'), config('disbursement.variance.max'));
        $minor += $variance;

        return [
            'cur' => 'PHP',      // Currency code (PHP for Philippine Peso)
            'num' => (string) $minor,
        ];
    }

    public function confirmDisbursement(string $operationId): bool
    {
        try {
            $transaction = Transaction::whereJsonContains('meta->operationId', $operationId)->firstOrFail();
            $payable = $transaction->payable;

            $payable->confirm($transaction);
            DisbursementConfirmed::dispatch($transaction);

            Log::info("Disbursement confirmed for operation ID: {$operationId}");

            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to confirm disbursement: {$e->getMessage()}");
            return false;
        }
    }
}
