<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Cash\Contracts\CashClaimantAuthorizationContract;
use LBHurtado\Cash\Contracts\CashWithdrawalAmountResolverContract;
use LBHurtado\Cash\Contracts\CashWithdrawalEligibilityContract;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Contracts\WithdrawalProcessorContract;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;

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
 * Pipeline Note:
 *
 * Initial withdrawal gates are delegated to WithdrawalPipeline:
 *
 * - claimant/contact resolution
 * - cash eligibility
 * - claimant authorization
 *
 * Add future pre-execution gates as pipeline steps rather than expanding this
 *
 * processor directly.
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
        protected WithdrawalPipeline $withdrawalPipeline,
        protected WithdrawalExecutionContextResolver $executionContextResolver,
        protected WithdrawalBankAccountResolver $bankAccountResolver,
        protected WithdrawalPayoutRequestFactory $payoutRequestFactory,
        protected WithdrawalRailGuard $railGuard,
        protected WithdrawalWalletSettlementService $walletSettlementService,
        protected WithdrawalResultFactory $resultFactory,
    ) {}

    public function process(Voucher $voucher, array $payload): WithdrawPayCodeResultData
    {
        $context = $this->withdrawalPipeline->process(
            new WithdrawalPipelineContextData(
                voucher: $voucher,
                payload: $payload,
            ),
        );

        $contact = $context->contact;

        if ($contact === null) {
            throw new \LogicException('Withdrawal claimant was not resolved.');
        }

        $withdrawAmount = $context->withdrawAmount;

        $sliceNumber = $context->sliceNumber;

        Log::info('[DefaultWithdrawalProcessorService] Processing withdrawal', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'amount' => $withdrawAmount,
            'slice_number' => $sliceNumber,
        ]);

        if (property_exists($voucher, 'redeemer') && method_exists($voucher, 'redeemers')) {
            $voucher->redeemer = $voucher->redeemers->first();
        }

        $input = $context->payoutRequest;
        $this->railGuard->assertAllowed($input);

        $disbursement = $context->disbursement;

        if ($disbursement === null) {
            throw new \LogicException('Withdrawal disbursement was not executed.');
        }

        $response = $disbursement->response;

        return DB::transaction(function () use ($voucher, $contact, $withdrawAmount, $sliceNumber, $input, $response): WithdrawPayCodeResultData {
            $voucher->refresh();

            $this->walletSettlementService->settle(
                voucher: $voucher,
                input: $input,
                withdrawAmount: $withdrawAmount,
                sliceNumber: $sliceNumber,
            );

            $voucher->refresh();

            $result = $this->resultFactory->make(
                voucher: $voucher,
                contact: $contact,
                input: $input,
                response: $response,
                withdrawAmount: $withdrawAmount,
                sliceNumber: $sliceNumber,
            );

            Log::info('[DefaultWithdrawalProcessorService] Withdrawal disbursed successfully', [
                'voucher' => $voucher->code,
                'transaction_id' => $response->transaction_id,
                'amount' => $withdrawAmount,
                'slice_number' => $sliceNumber,
                'remaining_slices' => $result->remaining_slices,
                'remaining_balance' => $result->remaining_balance,
            ]);

            return $result;
        });
    }

    protected function resolveAmount(Voucher $voucher, ?float $amount): float
    {
        $instrument = new VoucherWithdrawableInstrumentAdapter($voucher);

        return $this->amountResolver->resolve($instrument, $amount);
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
