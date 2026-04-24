<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Cash\Contracts\CashClaimantAuthorizationContract;
use LBHurtado\Cash\Contracts\CashWithdrawalAmountResolverContract;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Actions\WithdrawCash;
use LBHurtado\XChange\Adapters\ContactClaimantIdentityAdapter;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Contracts\WithdrawalProcessorContract;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use Propaganistas\LaravelPhone\PhoneNumber;
use RuntimeException;

/**
 * Withdrawal processor service.
 *
 * ⚠️ Refactor Status: Legacy Execution Orchestrator (Phase 1 – Cash Extraction)
 *
 * This class still coordinates the x-change withdrawal execution flow:
 * - Resolves claimant/contact context
 * - Checks current withdrawal eligibility
 * - Resolves or prepares payout details
 * - Calls the payout provider
 * - Records reconciliation metadata
 * - Performs wallet/cash mutation through existing package actions
 *
 * Refactor Note:
 * Some withdrawal rules are being progressively extracted to the `3neti/cash`
 * package. Do not add new domain/business rules here unless they are strictly
 * x-change orchestration or infrastructure concerns.
 *
 * Already delegated to cash:
 * - Amount resolution
 *
 * Candidate responsibilities for future extraction:
 * - Ownership/original-claimer authorization
 * - Withdrawal execution policy
 * - Settlement rail eligibility
 *
 * Responsibilities that should likely remain in x-change:
 * - Provider adapter calls
 * - Reconciliation recording
 * - API/lifecycle result shaping
 *
 * Future Plan:
 * - Delegate additional cash-domain rules to the cash package in later slices
 * - Mark as @deprecated only after execution orchestration is replaced
 *
 * @internal Legacy execution orchestrator during cash extraction
 */
class DefaultWithdrawalProcessorService implements WithdrawalProcessorContract
{
    public function __construct(
        protected PayoutProvider $gateway,
        protected BankRegistry $bankRegistry,
        protected DisbursementReconciliationStoreContract $reconciliations,
        protected DisbursementStatusResolverContract $statusResolver,
        protected CashWithdrawalAmountResolverContract $amountResolver,
        protected CashClaimantAuthorizationContract $claimantAuthorization,
    ) {}

    public function process(Voucher $voucher, array $payload): WithdrawPayCodeResultData
    {
        $mobile = data_get($payload, 'mobile');
        $country = (string) data_get($payload, 'recipient_country', 'PH');
        $amount = data_get($payload, 'amount');

        if (! is_string($mobile) || trim($mobile) === '') {
            throw new \InvalidArgumentException('Mobile number is required.');
        }

        $phoneNumber = new PhoneNumber($mobile, $country);
        $contact = Contact::fromPhoneNumber($phoneNumber);

        $this->assertVoucherIsWithdrawable($voucher);
        $this->assertOriginalRedeemer($voucher, $contact);

        $withdrawAmount = $this->resolveAmount(
            $voucher,
            $amount !== null && $amount !== '' ? (float) $amount : null,
        );

        $sliceNumber = method_exists($voucher, 'getConsumedSlices')
            ? $voucher->getConsumedSlices() + 1
            : 1;

        Log::info('[DefaultWithdrawalProcessorService] Processing withdrawal', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'amount' => $withdrawAmount,
            'slice_number' => $sliceNumber,
        ]);

        if (property_exists($voucher, 'redeemer') && method_exists($voucher, 'redeemers')) {
            $voucher->redeemer = $voucher->redeemers->first();
        }

        $input = $this->buildPayoutRequest($voucher, $contact, $payload, $withdrawAmount, $sliceNumber);

        $rail = SettlementRail::from($input->settlement_rail);

        if ($rail === SettlementRail::PESONET && $this->bankRegistry->isEMI($input->bank_code)) {
            $bankName = $this->bankRegistry->getBankName($input->bank_code);

            throw new RuntimeException(
                "Cannot disburse to {$bankName} via PESONET. E-money institutions require INSTAPAY."
            );
        }

        try {
            $response = $this->gateway->disburse($input);

            if ($response->status === PayoutStatus::FAILED) {
                throw new RuntimeException('Gateway returned failed status - disbursement failed');
            }

            $this->reconciliations->record([
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'claim_type' => 'withdraw',
                'provider' => $response->provider ?? 'unknown',
                'provider_reference' => $input->reference,
                'provider_transaction_id' => $response->transaction_id ?? null,
                'transaction_uuid' => $response->uuid ?? null,
                'status' => $this->statusResolver->resolveFromGatewayResponse($response),
                'internal_status' => 'recorded',
                'amount' => $input->amount,
                'currency' => 'PHP',
                'bank_code' => $input->bank_code,
                'account_number_masked' => $this->maskAccountNumber($input->account_number),
                'settlement_rail' => $input->settlement_rail,
                'attempt_count' => 1,
                'attempted_at' => now(),
                'completed_at' => $this->statusResolver->resolveFromGatewayResponse($response) === 'succeeded' ? now() : null,
                'raw_request' => $input->toArray(),
                'raw_response' => method_exists($response, 'toArray') ? $response->toArray() : [
                    'status' => $response->status?->value ?? null,
                    'transaction_id' => $response->transaction_id ?? null,
                    'uuid' => $response->uuid ?? null,
                    'provider' => $response->provider ?? null,
                ],
                'meta' => [
                    'flow' => 'withdraw',
                    'voucher_code' => $voucher->code,
                    'slice_number' => $sliceNumber,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[DefaultWithdrawalProcessorService] Gateway disbursement failed — recording pending', [
                'voucher' => $voucher->code,
                'slice' => $sliceNumber,
                'amount' => $withdrawAmount,
                'error' => $e->getMessage(),
            ]);

            $this->reconciliations->record([
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'claim_type' => 'withdraw',
                'provider' => 'unknown',
                'provider_reference' => $input->reference,
                'provider_transaction_id' => null,
                'transaction_uuid' => null,
                'status' => $this->statusResolver->resolveFromGatewayException($e),
                'internal_status' => 'recorded',
                'amount' => $input->amount,
                'currency' => 'PHP',
                'bank_code' => $input->bank_code,
                'account_number_masked' => $this->maskAccountNumber($input->account_number),
                'settlement_rail' => $input->settlement_rail,
                'attempt_count' => 1,
                'attempted_at' => now(),
                'raw_request' => $input->toArray(),
                'raw_response' => [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ],
                'needs_review' => $this->statusResolver->resolveFromGatewayException($e) === 'unknown',
                'review_reason' => $this->statusResolver->resolveFromGatewayException($e) === 'unknown'
                    ? 'Gateway outcome uncertain'
                    : null,
                'error_message' => $e->getMessage(),
                'meta' => [
                    'flow' => 'withdraw',
                    'voucher_code' => $voucher->code,
                    'slice_number' => $sliceNumber,
                ],
            ]);

            $this->recordPendingDisbursement($voucher, $input, $e);

            throw new RuntimeException('Disbursement failed: '.$e->getMessage());
        }

        return DB::transaction(function () use ($voucher, $contact, $withdrawAmount, $sliceNumber, $input, $response): WithdrawPayCodeResultData {
            $bankName = $this->bankRegistry->getBankName($input->bank_code);
            $providerName = $response->provider ?? 'unknown';
            $normalizedStatus = $response->status->value;
            $rail = SettlementRail::from($input->settlement_rail);

            $feeAmount = method_exists($this->gateway, 'getRailFee')
                ? $this->gateway->getRailFee($rail)
                : 0;

            $feeStrategy = data_get($voucher->instructions, 'cash.fee_strategy', 'absorb');

            $amountCentavos = (int) round($withdrawAmount * 100);

            $withdrawal = WithdrawCash::run(
                $voucher->cash,
                $response->transaction_id,
                'Disbursed to external bank account',
                [
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $voucher->code,
                    'flow' => 'withdraw',
                    'counterparty' => $bankName,
                    'reference' => $input->account_number,
                    'idempotency_key' => $response->uuid,
                    'slice_number' => $sliceNumber,
                ],
                $amountCentavos
            );

            $metadata = $voucher->metadata ?? [];

            if ($voucher->redeemed_at === null) {
                $voucher->redeemed_at = now();
            }

            $originalRedeemer = data_get($metadata, 'redemption.original_redeemer');

            if (! is_array($originalRedeemer) || $originalRedeemer === []) {
                data_set($metadata, 'redemption.original_redeemer', [
                    'contact_id' => $contact->id,
                    'mobile' => $contact->mobile,
                    'country' => $contact->country ?? null,
                ]);
            }

            data_set($metadata, 'disbursement', [
                'gateway' => $providerName,
                'transaction_id' => $response->transaction_id,
                'status' => $normalizedStatus,
                'amount' => $input->amount,
                'currency' => 'PHP',
                'settlement_rail' => $rail->value,
                'fee_amount' => $feeAmount,
                'total_cost' => ($input->amount * 100) + $feeAmount,
                'fee_strategy' => $feeStrategy,
                'recipient_identifier' => $input->account_number,
                'disbursed_at' => now()->toIso8601String(),
                'transaction_uuid' => $response->uuid,
                'recipient_name' => $bankName,
                'payment_method' => 'bank_transfer',
                'cash_withdrawal_uuid' => $withdrawal->uuid,
                'slice_number' => $sliceNumber,
                'metadata' => [
                    'bank_code' => $input->bank_code,
                    'bank_name' => $bankName,
                    'bank_logo' => $this->bankRegistry->getBankLogo($input->bank_code),
                    'rail' => $input->settlement_rail,
                    'is_emi' => $this->bankRegistry->isEMI($input->bank_code),
                ],
            ]);

            $voucher->metadata = $metadata;
            $voucher->save();
            $voucher->refresh();

            $remainingBalance = method_exists($voucher, 'getRemainingBalance')
                ? (float) $voucher->getRemainingBalance()
                : null;

            $remainingSlices = method_exists($voucher, 'getRemainingSlices')
                ? (int) $voucher->getRemainingSlices()
                : null;

            Log::info('[DefaultWithdrawalProcessorService] Withdrawal disbursed successfully', [
                'voucher' => $voucher->code,
                'transaction_id' => $response->transaction_id,
                'amount' => $withdrawAmount,
                'slice_number' => $sliceNumber,
                'remaining_slices' => $remainingSlices,
                'remaining_balance' => $remainingBalance,
            ]);

            return new WithdrawPayCodeResultData(
                voucher_code: (string) $voucher->code,
                withdrawn: true,
                status: 'withdrawn',
                requested_amount: $withdrawAmount,
                disbursed_amount: $withdrawAmount,
                currency: 'PHP',
                remaining_balance: $remainingBalance,
                slice_number: $sliceNumber,
                remaining_slices: $remainingSlices,
                slice_mode: method_exists($voucher, 'getSliceMode') ? $voucher->getSliceMode() : null,
                redeemer: [
                    'mobile' => $contact->mobile,
                    'country' => $contact->country ?? null,
                    'contact_id' => $contact->id,
                ],
                bank_account: [
                    'bank_code' => $input->bank_code,
                    'account_number' => $input->account_number,
                ],
                disbursement: [
                    'status' => $normalizedStatus,
                    'bank_code' => $input->bank_code,
                    'account_number' => $input->account_number,
                    'transaction_id' => $response->transaction_id,
                    'gateway' => $providerName,
                    'settlement_rail' => $rail->value,
                ],
                messages: ['Voucher withdrawal successful.'],
            );
        });
    }

    protected function assertVoucherIsWithdrawable(Voucher $voucher): void
    {
        if (! method_exists($voucher, 'isDivisible') || ! $voucher->isDivisible()) {
            throw new RuntimeException('This voucher is not divisible.');
        }

        if ($this->isOpenSliceVoucher($voucher)) {
            $state = is_object($voucher->state) && property_exists($voucher->state, 'value')
                ? $voucher->state->value
                : (string) $voucher->state;

            if ($state !== 'active') {
                throw new RuntimeException('This voucher cannot accept further withdrawals.');
            }

            if (method_exists($voucher, 'isExpired') && $voucher->isExpired()) {
                throw new RuntimeException('This voucher cannot accept further withdrawals.');
            }

            if (method_exists($voucher, 'getRemainingBalance') && (float) $voucher->getRemainingBalance() <= 0) {
                throw new RuntimeException('This voucher cannot accept further withdrawals.');
            }

            if (method_exists($voucher, 'getMaxSlices') && method_exists($voucher, 'getConsumedSlices')) {
                $maxSlices = $voucher->getMaxSlices();

                if ($maxSlices !== null && $voucher->getConsumedSlices() >= $maxSlices) {
                    throw new RuntimeException('This voucher cannot accept further withdrawals.');
                }
            }

            return;
        }

        if (! method_exists($voucher, 'canWithdraw') || ! $voucher->canWithdraw()) {
            throw new RuntimeException('This voucher cannot accept further withdrawals.');
        }
    }

    protected function assertOriginalRedeemer(Voucher $voucher, Contact $contact): void
    {
        $instrument = new VoucherWithdrawableInstrumentAdapter($voucher);
        $claimant = new ContactClaimantIdentityAdapter($contact);

        $this->claimantAuthorization->authorize($instrument, $claimant);
    }

    protected function resolveAmount(Voucher $voucher, ?float $amount): float
    {
        $instrument = new VoucherWithdrawableInstrumentAdapter($voucher);

        return $this->amountResolver->resolve($instrument, $amount);
    }

    protected function buildPayoutRequest(
        Voucher $voucher,
        Contact $contact,
        array $payload,
        float $amount,
        int $sliceNumber,
    ): PayoutRequestData {
        $rawBank = data_get($payload, 'bank_account');
        $bankAccount = null;

        if (is_array($rawBank)) {
            $bankCode = data_get($rawBank, 'bank_code');
            $accountNumber = data_get($rawBank, 'account_number');

            if (
                is_string($bankCode) && trim($bankCode) !== ''
                && is_string($accountNumber) && trim($accountNumber) !== ''
            ) {
                $bankAccount = BankAccount::fromBankAccount(
                    trim($bankCode).':'.trim($accountNumber)
                );
            }
        }

        if ($bankAccount === null && is_string($rawBank) && trim($rawBank) !== '') {
            $fallbackBankAccount = is_string($contact->bank_account) && trim($contact->bank_account) !== ''
                ? $contact->bank_account
                : null;

            $bankAccount = $fallbackBankAccount
                ? BankAccount::fromBankAccountWithFallback($rawBank, $fallbackBankAccount)
                : BankAccount::fromBankAccount($rawBank);
        }

        if ($bankAccount === null) {
            $bankCode = data_get($payload, 'bank_code');
            $accountNumber = data_get($payload, 'account_number');

            if (
                is_string($bankCode) && trim($bankCode) !== ''
                && is_string($accountNumber) && trim($accountNumber) !== ''
            ) {
                $bankAccount = BankAccount::fromBankAccount(
                    trim($bankCode).':'.trim($accountNumber)
                );
            }
        }

        if ($bankAccount === null && property_exists($voucher, 'redeemer') && $voucher->redeemer) {
            $fallbackRawBank = Arr::get($voucher->redeemer->metadata ?? [], 'redemption.bank_account');

            if (is_string($fallbackRawBank) && trim($fallbackRawBank) !== '') {
                $fallbackBankAccount = is_string($contact->bank_account) && trim($contact->bank_account) !== ''
                    ? $contact->bank_account
                    : null;

                $bankAccount = $fallbackBankAccount
                    ? BankAccount::fromBankAccountWithFallback($fallbackRawBank, $fallbackBankAccount)
                    : BankAccount::fromBankAccount($fallbackRawBank);
            }
        }

        if ($bankAccount === null) {
            $fallbackBankAccount = is_string($contact->bank_account) && trim($contact->bank_account) !== ''
                ? $contact->bank_account
                : null;

            if ($fallbackBankAccount) {
                $bankAccount = BankAccount::fromBankAccount($fallbackBankAccount);
            }
        }

        if ($bankAccount === null) {
            throw new RuntimeException('Bank account information is required for withdrawal.');
        }

        $reference = "{$voucher->code}-{$bankAccount->getAccountNumber()}-S{$sliceNumber}";

        $settlementRailEnum = data_get($voucher->instructions, 'cash.settlement_rail');
        $via = $settlementRailEnum instanceof SettlementRail
            ? $settlementRailEnum->value
            : ((float) $amount < 50000 ? 'INSTAPAY' : 'PESONET');

        return PayoutRequestData::from([
            'reference' => $reference,
            'amount' => $amount,
            'account_number' => $bankAccount->getAccountNumber(),
            'bank_code' => $bankAccount->getBankCode(),
            'settlement_rail' => $via,
            'external_id' => (string) $voucher->id,
            'external_code' => $voucher->code,
            'user_id' => $voucher->user_id,
            'mobile' => $contact->mobile,
        ]);
    }

    protected function recordPendingDisbursement(Voucher $voucher, PayoutRequestData $input, \Throwable $e): void
    {
        $bankName = $this->bankRegistry->getBankName($input->bank_code);

        $metadata = $voucher->metadata ?? [];

        data_set($metadata, 'disbursement', [
            'gateway' => 'unknown',
            'transaction_id' => $input->reference,
            'status' => PayoutStatus::PENDING->value,
            'amount' => $input->amount,
            'currency' => 'PHP',
            'settlement_rail' => $input->settlement_rail,
            'recipient_identifier' => $input->account_number,
            'disbursed_at' => now()->toIso8601String(),
            'recipient_name' => $bankName,
            'payment_method' => 'bank_transfer',
            'error' => $e->getMessage(),
            'requires_reconciliation' => true,
            'metadata' => [
                'bank_code' => $input->bank_code,
                'bank_name' => $bankName,
                'bank_logo' => $this->bankRegistry->getBankLogo($input->bank_code),
                'rail' => $input->settlement_rail,
                'is_emi' => $this->bankRegistry->isEMI($input->bank_code),
            ],
        ]);

        $voucher->metadata = $metadata;
        $voucher->save();
    }

    protected function maskAccountNumber(?string $accountNumber): ?string
    {
        if ($accountNumber === null || $accountNumber === '') {
            return null;
        }

        $length = strlen($accountNumber);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($accountNumber, -4);
    }

    protected function isOpenSliceVoucher(Voucher $voucher): bool
    {
        return method_exists($voucher, 'isDivisible')
            && $voucher->isDivisible()
            && method_exists($voucher, 'getSliceMode')
            && $voucher->getSliceMode() === 'open';
    }
}
