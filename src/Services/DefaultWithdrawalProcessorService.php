<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Actions\WithdrawCash;
use LBHurtado\XChange\Contracts\WithdrawalProcessorContract;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use Propaganistas\LaravelPhone\PhoneNumber;
use RuntimeException;

/**
 * DefaultWithdrawalProcessorService
 *
 * This service is the core execution engine for voucher withdrawals (divisible vouchers).
 *
 * It is responsible for:
 *  - validating withdrawal eligibility at the domain level
 *  - resolving withdrawal amount based on slice mode (fixed/open)
 *  - constructing a payout request for external disbursement
 *  - enforcing settlement rail rules (INSTAPAY vs PESONET vs EMI constraints)
 *  - invoking the payout provider (emi-core)
 *  - recording wallet withdrawal and updating voucher metadata
 *
 * ---
 *
 * ⚠️ IMPORTANT ARCHITECTURAL NOTES
 *
 * 1. This is the FIRST LAYER where real money leaves the system.
 *    Everything before this (prepare/start/complete) is UX + validation only.
 *
 * 2. This class bridges:
 *      Voucher Domain → EMI Core → External Banking Rails
 *
 * 3. This class is intentionally DEFAULT and OVERRIDABLE.
 *    Host applications MAY override this to:
 *      - plug custom payout providers
 *      - change fee strategies
 *      - integrate reconciliation systems
 *      - alter metadata storage
 *
 * ---
 *
 * 💡 DESIGN INTENT
 *
 * - Keep orchestration here, not in controllers
 * - Keep transport (HTTP) out
 * - Keep UI concerns out
 *
 * ---
 *
 * 🔌 DEPENDENCIES
 *
 * - PayoutProvider (emi-core)
 * - BankRegistry (money-issuer)
 * - Voucher domain model
 * - Wallet withdrawal action
 *
 * ---
 *
 * 🔁 TRANSACTION BOUNDARIES
 *
 * External calls (gateway) happen OUTSIDE DB transaction.
 * State mutation (wallet + voucher metadata) happens INSIDE DB transaction.
 *
 * ---
 *
 * 🚨 FAILURE HANDLING
 *
 * - Gateway failure → record pending disbursement
 * - Throw exception → upstream handles audit + API response
 *
 * ---
 *
 * 📦 RETURN CONTRACT
 *
 * array{
 *   success: bool,
 *   amount: float,
 *   slice_number: int,
 *   remaining_slices: int,
 *   bank_code: string,
 *   account_number: string
 * }
 */
class DefaultWithdrawalProcessorService implements WithdrawalProcessorContract
{
    public function __construct(
        protected PayoutProvider $gateway,
        protected BankRegistry $bankRegistry,
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

        // Fixed mode → always fixed slice amount
        // Open mode → user-defined but validated
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

        // Prevent invalid rail usage
        if ($rail === SettlementRail::PESONET && $this->bankRegistry->isEMI($input->bank_code)) {
            $bankName = $this->bankRegistry->getBankName($input->bank_code);

            throw new RuntimeException(
                "Cannot disburse to {$bankName} via PESONET. E-money institutions require INSTAPAY."
            );
        }

        try {
            // 🚨 EXTERNAL I/O — do NOT wrap in DB transaction
            $response = $this->gateway->disburse($input);

            if ($response->status === PayoutStatus::FAILED) {
                throw new RuntimeException('Gateway returned failed status - disbursement failed');
            }
        } catch (\Throwable $e) {
            Log::warning('[DefaultWithdrawalProcessorService] Gateway disbursement failed — recording pending', [
                'voucher' => $voucher->code,
                'slice' => $sliceNumber,
                'amount' => $withdrawAmount,
                'error' => $e->getMessage(),
            ]);

            // If gateway fails → mark as PENDING
            // This enables reconciliation flows later
            $this->recordPendingDisbursement($voucher, $input, $e);

            throw new RuntimeException('Disbursement failed: '.$e->getMessage());
        }

        return DB::transaction(function () use ($voucher, $contact, $withdrawAmount, $sliceNumber, $input, $response): WithdrawPayCodeResultData {
            // wallet withdrawal
            // voucher metadata update
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

            $voucher->metadata = array_merge($voucher->metadata ?? [], [
                'disbursement' => [
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
                ],
            ]);

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
        // 1. Ensure voucher supports withdrawal
        // This enforces divisibility at the domain level.
        if (! method_exists($voucher, 'isDivisible') || ! $voucher->isDivisible()) {
            throw new RuntimeException('This voucher is not divisible.');
        }

        // 2. Ensure withdrawal is still allowed
        // Prevents over-withdrawal / exhaustion.
        if (! method_exists($voucher, 'canWithdraw') || ! $voucher->canWithdraw()) {
            throw new RuntimeException('This voucher cannot accept further withdrawals.');
        }
    }

    protected function assertOriginalRedeemer(Voucher $voucher, Contact $contact): void
    {
        // Only the original redeemer can withdraw
        // This ensures ownership consistency of the voucher lifecycle.
        $originalContact = $voucher->contact;

        if (! $originalContact || $originalContact->id !== $contact->id) {
            throw new RuntimeException('Only the original redeemer can withdraw from this voucher.');
        }
    }

    protected function resolveAmount(Voucher $voucher, ?float $amount): float
    {
        $sliceMode = method_exists($voucher, 'getSliceMode')
            ? $voucher->getSliceMode()
            : null;

        if ($sliceMode === 'fixed') {
            if (! method_exists($voucher, 'getSliceAmount')) {
                throw new RuntimeException('Fixed-slice voucher is missing slice amount support.');
            }

            return (float) $voucher->getSliceAmount();
        }

        if ($amount === null) {
            throw new \InvalidArgumentException('Amount is required for open-mode vouchers.');
        }

        $minWithdrawal = method_exists($voucher, 'getMinWithdrawal')
            ? (float) $voucher->getMinWithdrawal()
            : 0.0;

        if ($amount < $minWithdrawal) {
            throw new \InvalidArgumentException(
                "Withdrawal amount ({$amount}) is below minimum ({$minWithdrawal})."
            );
        }

        $remainingBalance = method_exists($voucher, 'getRemainingBalance')
            ? (float) $voucher->getRemainingBalance()
            : 0.0;

        if ($amount > $remainingBalance) {
            throw new \InvalidArgumentException(
                "Withdrawal amount ({$amount}) exceeds remaining balance ({$remainingBalance})."
            );
        }

        return $amount;
    }

    protected function buildPayoutRequest(
        Voucher $voucher,
        Contact $contact,
        array $payload,
        float $amount,
        int $sliceNumber,
    ): PayoutRequestData {
        $rawBank = data_get($payload, 'bank_account');

        if ($rawBank === null && property_exists($voucher, 'redeemer') && $voucher->redeemer) {
            $rawBank = Arr::get($voucher->redeemer->metadata ?? [], 'redemption.bank_account');
        }

        $bankAccount = BankAccount::fromBankAccountWithFallback($rawBank, $contact->bank_account);

        $reference = "{$voucher->code}-{$contact->mobile}-S{$sliceNumber}";

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

        $voucher->metadata = array_merge($voucher->metadata ?? [], [
            'disbursement' => [
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
            ],
        ]);

        $voucher->save();
    }
}
