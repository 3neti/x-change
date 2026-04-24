<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Cash\Contracts\CashClaimantAuthorizationContract;
use LBHurtado\Cash\Contracts\CashWithdrawalAmountResolverContract;
use LBHurtado\Cash\Contracts\CashWithdrawalEligibilityContract;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Adapters\ContactClaimantIdentityAdapter;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
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
 * - Ownership/original-claimer authorization
 *
 * Candidate responsibilities for future extraction:
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
        protected BankRegistry $bankRegistry,
        protected CashWithdrawalAmountResolverContract $amountResolver,
        protected CashClaimantAuthorizationContract $claimantAuthorization,
        protected CashWithdrawalEligibilityContract $withdrawalEligibility,
        protected WithdrawalExecutionContextResolver $executionContextResolver,
        protected WithdrawalBankAccountResolver $bankAccountResolver,
        protected WithdrawalPayoutRequestFactory $payoutRequestFactory,
        protected WithdrawalDisbursementExecutor $disbursementExecutor,
        protected WithdrawalWalletSettlementService $walletSettlementService,
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

        $bankAccount = $this->bankAccountResolver->resolve($voucher, $contact, $payload);

        $executionContext = $this->executionContextResolver->resolve(
            $voucher,
            $bankAccount->getAccountNumber(),
        );

        $sliceNumber = $executionContext->sliceNumber;
        $providerReference = $executionContext->providerReference;

        Log::info('[DefaultWithdrawalProcessorService] Processing withdrawal', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'amount' => $withdrawAmount,
            'slice_number' => $sliceNumber,
        ]);

        if (property_exists($voucher, 'redeemer') && method_exists($voucher, 'redeemers')) {
            $voucher->redeemer = $voucher->redeemers->first();
        }

        $input = $this->payoutRequestFactory->make(
            $voucher,
            $contact,
            $bankAccount,
            $providerReference,
            $withdrawAmount,
        );

        $rail = SettlementRail::from($input->settlement_rail);

        if ($rail === SettlementRail::PESONET && $this->bankRegistry->isEMI($input->bank_code)) {
            $bankName = $this->bankRegistry->getBankName($input->bank_code);

            throw new RuntimeException(
                "Cannot disburse to {$bankName} via PESONET. E-money institutions require INSTAPAY."
            );
        }

        try {
            $disbursement = $this->disbursementExecutor->execute(
                voucher: $voucher,
                input: $input,
                sliceNumber: $sliceNumber,
            );

            $response = $disbursement->response;
        } catch (\Throwable $e) {
            Log::warning('[DefaultWithdrawalProcessorService] Gateway disbursement failed — recording pending', [
                'voucher' => $voucher->code,
                'slice' => $sliceNumber,
                'amount' => $withdrawAmount,
                'error' => $e->getMessage(),
            ]);

            $this->recordPendingDisbursement($voucher, $input, $e);

            throw $e;
        }

        return DB::transaction(function () use ($voucher, $contact, $withdrawAmount, $sliceNumber, $input, $response): WithdrawPayCodeResultData {
            $voucher->refresh();

            $normalizedStatus = $response->status->value;
            $rail = SettlementRail::from($input->settlement_rail);

            $this->walletSettlementService->settle(
                voucher: $voucher,
                input: $input,
                withdrawAmount: $withdrawAmount,
                sliceNumber: $sliceNumber,
            );

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

            $providerName = $response->provider ?? 'unknown';

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
        $instrument = new VoucherWithdrawableInstrumentAdapter($voucher);

        $this->withdrawalEligibility->assertEligible($instrument);
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
}
