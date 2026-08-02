<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleClaimSubmitter;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Throwable;

final readonly class OnboardingVoucherScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private LifecycleClaimSubmitter $claimSubmitter,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryPositionReadModelContract $positions,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $mobile = MobileNumber::normalize($context->baseClaimMobile);

        if ($mobile === null || ! $context->voucher instanceof Model) {
            return $this->failure(
                $context,
                'The onboarding Voucher scenario requires a valid claimant Mobile and generated Voucher.',
            );
        }

        $email = mb_strtolower(trim((string) data_get(
            $context->scenario,
            'onboarding.email',
            'onboarding-voucher-'.$mobile.'@example.test',
        )));
        $name = trim((string) data_get(
            $context->scenario,
            'onboarding.name',
            'Onboarding Voucher Recipient',
        ));
        $mobileVerificationRequired = (bool) data_get(
            $context->voucher->metadata,
            'instructions.execution.metadata.onboarding.mobile_verification_required',
            true,
        );
        $accountBeforeClaim = $this->accountFor($context, $mobile);
        $positionsBeforeClaim = $accountBeforeClaim instanceof Model
            ? $this->recipientPositions($accountBeforeClaim)
            : $this->emptyRecipientPositions();

        try {
            $result = $this->claimSubmitter->submit(
                $context,
                $context->voucher,
                [
                    'mobile' => $mobile,
                    'recipient_country' => 'PH',
                    'inputs' => [
                        'full_name' => $name,
                        'name' => $name,
                        'email' => $email,
                        'mobile' => $mobile,
                    ] + $this->verificationEvidence($mobileVerificationRequired, $mobile),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failure(
                $context,
                'The onboarding Voucher claim failed safely.',
                $exception::class,
                $exception->getMessage(),
            );
        }

        if (! $result instanceof SubmitPayCodeClaimResultData || ! $result->claimed) {
            return $this->failure(
                $context,
                'The onboarding Voucher did not reach a completed claim.',
            );
        }

        $account = $this->accountFor($context, $mobile);

        if (! $account instanceof Model) {
            return $this->failure(
                $context,
                'The onboarding Voucher was claimed without a resolvable recipient Account.',
            );
        }

        $providerAttempts = DisbursementReconciliation::query()
            ->where('voucher_code', $context->voucher->getAttribute('code'))
            ->count();
        $executionPolicy = data_get(
            $context->voucher->metadata,
            'instructions.execution.metadata.post_redemption.mode',
        );
        $providerCallsSuppressed = $executionPolicy === OnboardingVoucherInstructionPolicy::PostRedemptionMode
            && $providerAttempts === 0;
        $principalMinor = (int) round(
            ((float) ($context->generated?->amount ?? 0)) * 100
        );
        $instructionDebitMinor = abs((int) ($context->generated?->debit->amount ?? 0));
        $estimatedTotalCommitmentMinor = (int) round(
            ((float) ($context->generated?->cost->account_debit ?? 0)) * 100
        );
        $recipientPositions = $this->recipientPositions($account);
        $clientFundsCreditMinor = $recipientPositions['client_funds_minor']
            - $positionsBeforeClaim['client_funds_minor'];
        $principalDisposition = $principalMinor === 0
            ? 'not_applicable'
            : 'redeemed_without_provider_payout_or_account_credit';

        return new ScenarioRunResult(
            exitCode: $providerCallsSuppressed
                ? Command::SUCCESS
                : Command::FAILURE,
            payload: [
                'schema' => 'x-change.lifecycle.onboarding-voucher.v2',
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => $context->mode(),
                'success' => $providerCallsSuppressed,
                'message' => $providerCallsSuppressed
                    ? 'The system Account issued the onboarding Pay Code; the execution engine provisioned the recipient Account without a provider payout.'
                    : 'The onboarding account was provisioned, but the no-provider-call invariant failed.',
                'generated' => $context->generated?->toArray(),
                'issuer' => [
                    'role' => 'system',
                    'model' => $context->issuer::class,
                    'key' => (string) $context->issuer->getKey(),
                    'funding_boundary' => data_get(
                        $context->scenario,
                        'lifecycle.funding_boundary',
                    ),
                ],
                'issuance_ledger' => [
                    'currency' => $context->generated?->currency,
                    'pay_code_principal_minor' => $principalMinor,
                    'instruction_cost_minor' => (int) round(
                        ((float) ($context->generated?->cost->total ?? 0)) * 100
                    ),
                    'instruction_debit_minor' => $instructionDebitMinor,
                    'estimated_total_commitment_minor' => $estimatedTotalCommitmentMinor,
                    'principal_treatment_at_issuance' => 'voucher_liability_only',
                    'charges' => $context->generated?->cost->charges ?? [],
                ],
                'voucher' => [
                    'code' => $context->voucher->getAttribute('code'),
                    'onboarding' => data_get($context->voucher, 'instructions.onboarding') === true,
                    'execution_driver' => data_get($context->voucher, 'instructions.execution.driver'),
                    'claimed' => $result->claimed,
                    'status' => $result->status,
                    'post_redemption_mode' => $executionPolicy,
                ],
                'recipient_account' => [
                    'model' => $account::class,
                    'key' => (string) $account->getKey(),
                    'mobile' => $this->maskedMobile($mobile),
                    'mobile_verified' => $account->getAttribute('mobile_verified_at') !== null,
                    'platform_account_ready' => method_exists($account, 'wallet')
                        && $account->wallet()->where('slug', 'platform')->exists(),
                    'treasury_positions_ready' => $recipientPositions['count'] > 0,
                    'client_funds_minor' => $recipientPositions['client_funds_minor'],
                    'pay_code_reserve_minor' => $recipientPositions['pay_code_reserve_minor'],
                ],
                'economic_outcome' => [
                    'provider_payout_minor' => 0,
                    'recipient_client_funds_credit_minor' => $clientFundsCreditMinor,
                    'principal_disposition' => $principalDisposition,
                    'requires_product_decision' => $principalDisposition
                        === 'redeemed_without_provider_payout_or_account_credit',
                ],
                'controls' => [
                    'mobile_verification_required' => $mobileVerificationRequired,
                    'provider_calls' => $providerAttempts > 0,
                    'provider_attempt_count' => $providerAttempts,
                    'external_payout_suppressed' => $providerCallsSuppressed,
                    'raw_otp_persisted' => $this->rawOtpPersisted($context->voucher),
                    'canonical_claim_link' => route(
                        'x-change.claim.show',
                        ['code' => $context->voucher->getAttribute('code')],
                        false,
                    ),
                ],
            ],
        );
    }

    private function accountFor(ScenarioRunContext $context, string $mobile): ?Model
    {
        $modelClass = $context->issuer::class;

        return $modelClass::query()
            ->whereIn('mobile', MobileNumber::candidates($mobile))
            ->first();
    }

    private function failure(
        ScenarioRunContext $context,
        string $message,
        ?string $exception = null,
        ?string $exceptionMessage = null,
    ): ScenarioRunResult {
        return new ScenarioRunResult(
            exitCode: Command::FAILURE,
            payload: [
                'schema' => 'x-change.lifecycle.onboarding-voucher.v2',
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => $context->mode(),
                'success' => false,
                'message' => $message,
                'exception' => $exception,
                'exception_message' => app()->environment(['local', 'testing'])
                    ? $exceptionMessage
                    : null,
                'provider_calls' => false,
            ],
        );
    }

    private function maskedMobile(string $mobile): string
    {
        return str_repeat('*', max(0, mb_strlen($mobile) - 4)).mb_substr($mobile, -4);
    }

    /**
     * @return array<string, mixed>
     */
    private function verificationEvidence(bool $required, string $mobile): array
    {
        if (! $required) {
            return [];
        }

        $verifiedAt = now()->toIso8601String();

        return [
            'otp' => [
                'mobile' => $mobile,
                'verified_at' => $verifiedAt,
                'verification_reference' => 'simulation:'.hash('sha256', $verifiedAt),
                'verification_purpose' => (string) config(
                    'x-change.onboarding.identity_otp.purpose',
                    'onboarding.account',
                ),
            ],
            'otp_verified' => true,
        ];
    }

    /**
     * @return array{count:int,client_funds_minor:int,pay_code_reserve_minor:int}
     */
    private function recipientPositions(Model $account): array
    {
        $positions = array_values(array_filter(
            $this->positions->forPrincipal(
                $this->principalReferences->resolve($account),
            ),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active',
        ));

        return [
            'count' => count($positions),
            'client_funds_minor' => $this->sumPurpose(
                $positions,
                TreasuryPositionPurpose::ClientFunds,
            ),
            'pay_code_reserve_minor' => $this->sumPurpose(
                $positions,
                TreasuryPositionPurpose::PayCodeReserve,
            ),
        ];
    }

    /**
     * @return array{count:int,client_funds_minor:int,pay_code_reserve_minor:int}
     */
    private function emptyRecipientPositions(): array
    {
        return [
            'count' => 0,
            'client_funds_minor' => 0,
            'pay_code_reserve_minor' => 0,
        ];
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function sumPurpose(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): int {
        return array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->purpose === $purpose
                ? $position->balanceMinor
                : 0,
            $positions,
        ));
    }

    private function rawOtpPersisted(Model $voucher): bool
    {
        $metadata = $voucher->redeemers()->latest('id')->first()?->metadata;
        $inputs = (array) data_get(
            $metadata,
            'redemption.inputs',
            data_get($metadata, 'inputs', []),
        );
        $otp = data_get($inputs, 'otp');
        $otpValue = data_get($inputs, 'otp.value');

        return filled(data_get($inputs, 'otp_code'))
            || filled(data_get($inputs, 'otp.otp_code'))
            || (is_string($otp) && $otp !== 'verified' && trim($otp) !== '')
            || (is_string($otpValue) && $otpValue !== 'verified' && trim($otpValue) !== '');
    }
}
