<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use LBHurtado\PaymentGateway\Data\Netbank\Disburse\{
    DisburseInputData,
    DisbursePayloadData,
    DisburseResponseData
};
use LBHurtado\Wallet\Events\DisbursementConfirmed;
use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\{
    Http,
    Log,
    DB
};
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Brick\Money\Money;

trait CanDisburse
{
    /**
     * Attempt to disburse funds.
     *
     * @param Wallet $wallet
     * @param DisburseInputData|array $validated
     * @return DisburseResponseData|bool
     */
    public function disburse(Wallet $wallet, DisburseInputData|array $validated): DisburseResponseData|bool
    {
        $data = $validated instanceof DisburseInputData
            ? $validated->toArray()
            : $validated;

        $amount  = Arr::get($data, 'amount');
        $currency = config('disbursement.currency', 'PHP');
        $credits  = Money::of($amount, $currency);

        DB::beginTransaction();

        try {
            // Reserve funds (pending withdrawal)
            $transaction = $wallet->withdraw(
                $credits->getMinorAmount()->toInt(),
                [],
                false
            );

            // Build and log request payload
            $payload = DisbursePayloadData::fromValidated($data)->toArray();
            Log::info('[Netbank] Disburse payload prepared', $payload);

            // Send to bank
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type'  => 'application/json',
            ])->post(config('disbursement.server.end-point'), $payload);

            if (! $response->successful()) {
                Log::warning('[Netbank] Disbursement failed', ['body' => $response->body()]);
                DB::rollBack();
                return false;
            }

            // Persist operationId and commit
            $transaction->meta = [
                'operationId' => $response->json('transaction_id'),
                'user_id'     => $wallet->getKey(),
                'payload'     => $payload,
            ];
            $transaction->save();

            DB::commit();

            // Build response DTO
            return DisburseResponseData::from(array_merge(
                ['uuid' => $transaction->uuid],
                $response->json()
            ));
        } catch (\Throwable $e) {
            Log::error('[Netbank] Disbursement error', ['error' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    /**
     * Retrieve the validation rules for disbursement input.
     *
     * @return array
     */
    protected function rules(): array
    {
        $min  = config('disbursement.min');
        $max  = config('disbursement.max');
        $rails = config('disbursement.settlement_rails', []);

        return [
            'reference'      => ['required', 'string', 'min:2', 'unique:references,code'],
            'bank'           => ['required', 'string'],
            'account_number' => ['required', 'string'],
            'via'            => ['required', 'string', Rule::in($rails)],
            'amount'         => ['required', 'integer', 'min:'.$min, 'max:'.$max],
        ];
    }

    /**
     * Confirm a previously‐reserved disbursement once the bank calls back.
     *
     * @param string $operationId
     * @return bool
     */
    public function confirmDisbursement(string $operationId): bool
    {
        try {
            $transaction = Transaction::whereJsonContains('meta->operationId', $operationId)
                ->firstOrFail();

            // Mark it as completed
            $transaction->payable->confirm($transaction);
            DisbursementConfirmed::dispatch($transaction);

            Log::info("[Netbank] Disbursement confirmed for operation {$operationId}");
            return true;
        } catch (\Throwable $e) {
            Log::error('[Netbank] Confirm disbursement failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
