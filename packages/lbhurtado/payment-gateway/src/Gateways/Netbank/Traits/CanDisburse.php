<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use LBHurtado\PaymentGateway\Data\Netbank\Common\PayloadAmountData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisbursePayloadData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisbursePayloadDestinationAccountData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisbursePayloadRecipientData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Events\DisbursementConfirmed;
use Illuminate\Support\Facades\{DB, Http, Log};
use LBHurtado\PaymentGateway\Support\Address;
use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Brick\Money\Money;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseInputData;

trait CanDisburse
{
    public function disburse(Wallet $user, DisburseInputData|array $validated): DisburseResponseData|bool
    {
        $validated = $validated instanceof DisburseInputData
            ? $validated->toArray()
            : $validated;

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

            $payload_data = DisbursePayloadData::fromValidated($validated);

            $payload = $payload_data->toArray();

            // Step 2: Prepare the disbursement payload
//            $payload = [
//                'reference_id'          => $reference = Arr::get($validated, 'reference'),
//                'settlement_rail'       => Arr::get($validated, 'via'),
//                'amount'                => $this->getAmountArray($validated),
//                'source_account_number' => config('disbursement.source.account_number'),
//                'sender'                => config('disbursement.source.sender'),
//                'destination_account'   => $this->getDestinationAccount($validated),
//                'recipient'             => $this->getRecipient($validated),
//            ];

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
                        'request' => [
                            'user_id' => $user->getKey(),
                            'payload' => $payload
                        ],
                ];
                $transaction->save();

                // Commit the database transaction
                DB::commit();

                // Create and return response data object
                return DisburseResponseData::from(
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
            'reference'      => ['required', 'string', 'min:2', 'unique:references,code'],
            'bank'           => ['required', 'string'],
            'account_number' => ['required', 'string'],
            'via'            => ['required', 'string', Rule::in($settlement_rails)],
            'amount'         => ['required', 'integer', $min_rule, $max_rule],
        ];
    }

//    protected function getDestinationAccount(array $validated): array
//    {
//        return [
//            'bank_code'      => Arr::get($validated, 'bank'),
//            'account_number' => Arr::get($validated, 'account_number'),
//        ];
//    }
//
//    protected function getRecipient(array $validated): array
//    {
//        return [
//            'name'    => Arr::get($validated, 'account_number'), // Or fetch user-provided "name"
//            'address' => Address::generate(),                    // Address generation logic
//        ];
//    }
//
//    protected function getAmountArray(array $validated): array
//    {
//        // Parse the amount into minor units (e.g., cents)
//        $minor = Money::of(Arr::get($validated, 'amount'), 'PHP')
//            ->getMinorAmount()
//            ->toInt();
//
//        // Add a small variance (randomized amount) for operational reasons
//        $variance = rand(config('disbursement.variance.min'), config('disbursement.variance.max'));
//        $minor += $variance;
//
//        return [
//            'cur' => 'PHP',      // Currency code (PHP for Philippine Peso)
//            'num' => (string) $minor,
//        ];
//    }

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
