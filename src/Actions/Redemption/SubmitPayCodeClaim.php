<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use BadMethodCallException;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiPaynamicsConstellation\Exceptions\PendingConstellationOtpException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ApprovalWorkflowContract;
use LBHurtado\XChange\Contracts\ClaimApprovalInitiationContract;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ProviderReadinessGuardContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Contracts\XChangeOnboardingGatewayContract;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Data\Settlement\SettlementExecutionResultData;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Exceptions\ProviderProvisioningRequired;
use LBHurtado\XChange\Services\BuildProvisioningFlowDescriptor;
use LBHurtado\XChange\Services\ResumeProviderProvisioningFromOnboarding;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;
use LBHurtado\XChange\Support\Claim\PendingPaynamicsOtpClaimResult;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class SubmitPayCodeClaim
{
    use AsAction;

    public function __construct(
        protected ClaimExecutionFactoryContract $factory,
        protected RecordVoucherClaim $recordVoucherClaim,
        protected ?ApprovalWorkflowContract $approvalWorkflow = null,
        protected ?ClaimApprovalInitiationContract $approvalInitiation = null,
        protected ?PendingPaynamicsOtpClaimResult $pendingPaynamicsOtpResult = null,
        protected ?WithdrawalDisbursementExecutor $approvalReplayDisbursements = null,
        protected ?ProviderReadinessGuardContract $readinessGuard = null,
        protected ?ProviderRuntimeSettingsResolverContract $settings = null,
        protected ?UserResolverContract $users = null,
        protected ?XChangeOnboardingGatewayContract $onboarding = null,
        protected ?BuildProvisioningFlowDescriptor $descriptors = null,
        protected ?ResumeProviderProvisioningFromOnboarding $onboardingProvisioning = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData|ClaimApprovalInitiationResultData
    {
        $this->guardClaimantProvisioning($voucher, $payload);

        $executor = $this->factory->make($voucher, $payload);

        try {
            if ($executor instanceof SettlementExecutionContract) {
                return $this->fromSettlementResult(
                    $executor->execute($voucher, $payload)
                );
            }

            $result = $executor->handle($voucher, $payload);
        } catch (PendingConstellationOtpException $e) {
            return ClaimApprovalInitiationResultData::from(
                $this->pendingPaynamicsOtpResult()
                    ->fromException($voucher, $e)
            );
        } catch (Throwable $e) {
            if ($this->shouldReplayApprovedPaynamicsPayout($voucher, $payload, $e)) {
                return $this->replayApprovedPaynamicsPayout($voucher, $payload);
            }

            throw $e;
        }

        if (
            $result instanceof WithdrawPayCodeResultData
            && $result->status === 'approval_required'
            && ! $this->isApprovalReplay($payload)
        ) {
            $approval = $this->approvalWorkflow()->resolve($result, [
                'voucher_code' => $voucher->code,
                'payload' => $payload,
            ]);

            if ($approval->status === 'pending') {
                return $this->approvalInitiation()->initiate(
                    $voucher,
                    $payload,
                    $approval->toArray(),
                );
            }
        }

        $normalized = $this->normalizeResult($voucher, $result, $payload);

        if (! $this->isApprovalReplay($payload)) {
            $pendingOtp = $this->pendingPaynamicsOtpMetadata($voucher, $normalized, $payload);

            if ($pendingOtp !== null) {
                return $this->toPendingPaynamicsOtpApprovalResult($voucher, $normalized, $pendingOtp);
            }
        }

        if (
            ! $this->isApprovalReplay($payload)
            && $this->isApprovalPipelinePaynamicsPayload($payload)
            && $this->isDeferredPaynamicsOtpPendingResult($voucher, $normalized, $payload)
        ) {
            return $this->toDeferredPaynamicsOtpApprovalResult($voucher, $normalized, $payload);
        }

        $this->recordVoucherClaim->handle($voucher, $normalized, $payload);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function normalizeResult(Voucher $voucher, mixed $result, array $payload): SubmitPayCodeClaimResultData
    {
        if ($result instanceof RedeemPayCodeResultData) {
            $isDivisible = $this->safeBoolMethod($voucher, 'isDivisible');

            $remainingBalance = null;

            if ($isDivisible) {
                $resolvedRemaining = $this->safeCall($voucher, 'getRemainingBalance');

                if ($resolvedRemaining !== null) {
                    $remainingBalance = (float) $resolvedRemaining;
                }
            }

            return new SubmitPayCodeClaimResultData(
                voucher_code: $result->voucher_code,
                claim_type: 'redeem',
                claimed: $result->redeemed,
                status: $result->status,
                requested_amount: null,
                disbursed_amount: null,
                currency: null,
                remaining_balance: $remainingBalance,
                fully_claimed: ! $isDivisible,
                disbursement: $result->disbursement,
                messages: $result->messages,
            );
        }

        if ($result instanceof WithdrawPayCodeResultData) {
            return new SubmitPayCodeClaimResultData(
                voucher_code: $result->voucher_code,
                claim_type: 'withdraw',
                claimed: $result->withdrawn,
                status: $result->status,
                requested_amount: $result->requested_amount,
                disbursed_amount: $result->disbursed_amount,
                currency: $result->currency,
                remaining_balance: $result->remaining_balance,
                fully_claimed: (float) ($result->remaining_balance ?? 0) <= 0,
                disbursement: $result->disbursement,
                messages: $result->messages,
            );
        }

        throw new \RuntimeException('Unsupported claim execution result type: '.get_debug_type($result));
    }

    protected function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    protected function safeBoolMethod(object $target, string $method, bool $default = false): bool
    {
        return (bool) $this->safeCall($target, $method, $default);
    }

    protected function safeCall(object $target, string $method, mixed $default = null): mixed
    {
        if (! method_exists($target, $method)) {
            return $default;
        }

        try {
            return $target->{$method}();
        } catch (BadMethodCallException) {
            return $default;
        }
    }

    protected function fromSettlementResult(SettlementExecutionResultData $result): SubmitPayCodeClaimResultData
    {
        return new SubmitPayCodeClaimResultData(
            voucher_code: $result->voucher_code,
            claim_type: 'settlement',
            claimed: false,
            status: $result->status,
            requested_amount: null,
            disbursed_amount: null,
            currency: null,
            remaining_balance: null,
            fully_claimed: false,
            disbursement: null,
            messages: [
                $result->message,
            ],
            settlement: $result->meta,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function guardClaimantProvisioning(Voucher $voucher, array $payload): void
    {
        if (! $this->shouldGuardClaimantProvisioning()) {
            return;
        }

        $provider = $this->settings()->provider(data_get($payload, 'provider'));

        if (! $this->requiresClaimantProvisioning($voucher, $provider)) {
            return;
        }

        $claimant = $this->users()->resolve($payload);
        $mode = ProviderProvisioningMode::BankAccountLink->value;
        $topology = $this->settings()->topology($provider);
        $resume = null;

        if ($claimant !== null) {
            $resume = $this->onboardingProvisioning()->handle(
                $this->onboardingReference($payload),
                $claimant,
                [
                    'provider' => $provider,
                    'mode' => $mode,
                    'purpose' => 'BankOnboardingRequired',
                    'status' => 'ready',
                    'bank_code' => data_get($payload, 'bank_account.bank_code'),
                    'account_number_masked' => $this->maskAccountNumber(data_get($payload, 'bank_account.account_number')),
                ],
            );

            if ((bool) data_get($resume, 'ready') === true) {
                $readiness = $this->readinessGuard()->evaluateClaimant($claimant, $provider, [
                    'requires_bank_account' => true,
                ]);

                if ($readiness->ready) {
                    return;
                }
            }

            $readiness = $this->readinessGuard()->evaluateClaimant($claimant, $provider, [
                'requires_bank_account' => true,
            ]);

            if ($readiness->ready) {
                return;
            }

            throw new ProviderProvisioningRequired(
                'Claim requires provider bank-account provisioning before payout can continue.',
                $this->buildProvisioningContext(
                    purpose: 'redeem_pay_code',
                    provider: $provider,
                    mode: $mode,
                    topology: $topology,
                    reason: $readiness->reason,
                    missing: $readiness->missing,
                    readiness: $readiness->toArray(),
                    onboarding: data_get($resume, 'onboarding')
                        ?? $this->startClaimOnboarding($payload, $provider, $mode),
                ),
            );
        }

        throw new ProviderProvisioningRequired(
            'Claim requires claimant onboarding before provider bank-account provisioning can continue.',
            $this->buildProvisioningContext(
                purpose: 'redeem_pay_code',
                provider: $provider,
                mode: $mode,
                topology: $topology,
                reason: 'Claimant identity is not resolved.',
                missing: ['claimant_identity', 'bank_account_link'],
                readiness: [
                    'status' => 'blocked',
                    'provider' => $provider,
                    'topology' => $topology,
                    'mode' => $mode,
                    'reason' => 'Claimant identity is not resolved.',
                    'missing' => ['claimant_identity', 'bank_account_link'],
                    'ready' => false,
                ],
                onboarding: $this->startClaimOnboarding($payload, $provider, $mode),
            ),
        );
    }

    protected function approvalWorkflow(): ApprovalWorkflowContract
    {
        return $this->approvalWorkflow
            ??= app(ApprovalWorkflowContract::class);
    }

    protected function approvalInitiation(): ClaimApprovalInitiationContract
    {
        return $this->approvalInitiation
            ??= app(ClaimApprovalInitiationContract::class);
    }

    protected function isApprovalReplay(array $payload): bool
    {
        return data_get($payload, 'approval.resume') === true
            || data_get($payload, 'otp.verified') === true;
    }

    protected function readinessGuard(): ProviderReadinessGuardContract
    {
        return $this->readinessGuard ??= app(ProviderReadinessGuardContract::class);
    }

    protected function settings(): ProviderRuntimeSettingsResolverContract
    {
        return $this->settings ??= app(ProviderRuntimeSettingsResolverContract::class);
    }

    protected function users(): UserResolverContract
    {
        return $this->users ??= app(UserResolverContract::class);
    }

    protected function onboarding(): XChangeOnboardingGatewayContract
    {
        return $this->onboarding ??= app(XChangeOnboardingGatewayContract::class);
    }

    protected function descriptors(): BuildProvisioningFlowDescriptor
    {
        return $this->descriptors ??= app(BuildProvisioningFlowDescriptor::class);
    }

    protected function onboardingProvisioning(): ResumeProviderProvisioningFromOnboarding
    {
        return $this->onboardingProvisioning ??= app(ResumeProviderProvisioningFromOnboarding::class);
    }

    protected function requiresClaimantProvisioning(Voucher $voucher, string $provider): bool
    {
        if ($provider !== 'netbank') {
            return false;
        }

        return (bool) (
            data_get($voucher->metadata, 'instructions.redemption_form.collect_bank_account')
            ?? data_get($voucher->instructions?->toArray(), 'redemption_form.collect_bank_account', false)
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function startClaimOnboarding(array $payload, string $provider, string $mode): array
    {
        return (array) $this->onboarding()->startRedemption([
            ...$payload,
            'provider' => $provider,
            'mode' => $mode,
            'purpose' => 'BankOnboardingRequired',
            'disbursement' => [
                'bank_onboarding' => 'required',
            ],
            'bank_code' => data_get($payload, 'bank_account.bank_code'),
            'account_number' => data_get($payload, 'bank_account.account_number'),
            'metadata' => [
                'onboarding_reference' => $this->onboardingReference($payload),
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $missing
     * @param  array<string, mixed>  $readiness
     * @param  array<string, mixed>  $onboarding
     * @return array<string, mixed>
     */
    protected function buildProvisioningContext(
        string $purpose,
        string $provider,
        string $mode,
        string $topology,
        string $reason,
        array $missing,
        array $readiness,
        array $onboarding,
    ): array {
        return [
            'purpose' => $purpose,
            'provider' => $provider,
            'mode' => $mode,
            'reason' => $reason,
            'missing' => $missing,
            'readiness' => $readiness,
            'onboarding' => $onboarding,
            'descriptor' => $this->descriptors()->handle($provider, $mode, $topology)->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function onboardingReference(array $payload): ?string
    {
        $reference = data_get($payload, 'onboarding.reference')
            ?? data_get($payload, 'metadata.onboarding_reference')
            ?? data_get($payload, '_meta.onboarding_reference');

        if (! is_string($reference) || trim($reference) === '') {
            return null;
        }

        return trim($reference);
    }

    protected function maskAccountNumber(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $digits = trim($value);
        $length = strlen($digits);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($digits, -4);
    }

    protected function shouldGuardClaimantProvisioning(): bool
    {
        if ($this->readinessGuard !== null || $this->settings !== null || $this->users !== null) {
            return true;
        }

        return app()->bound(ProviderReadinessGuardContract::class)
            && app()->bound(ProviderRuntimeSettingsResolverContract::class)
            && app()->bound(UserResolverContract::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function shouldReplayApprovedPaynamicsPayout(Voucher $voucher, array $payload, Throwable $e): bool
    {
        if (
            ! $this->isApprovalReplay($payload)
            || data_get($payload, 'approval.provider') !== 'paynamics'
            || data_get($payload, 'otp.verified') !== true
        ) {
            return false;
        }

        $message = mb_strtolower($e->getMessage());
        $class = mb_strtolower($e::class);

        if (
            str_contains($message, 'already been redeemed')
            || str_contains($class, 'voucherredeemed')
        ) {
            return true;
        }

        if (! str_contains($message, 'failed to redeem voucher')) {
            return false;
        }

        $metadata = $this->voucherMetadata($voucher);
        $reference = data_get($payload, 'approval.reference_id') ?? data_get($payload, 'reference_id');
        $metadataReference = data_get($metadata, 'disbursement.transaction_id')
            ?? data_get($metadata, 'disbursement.reference_id')
            ?? data_get($metadata, 'disbursement.provider_reference')
            ?? data_get($metadata, 'disbursement.provider_tx')
            ?? data_get($metadata, 'disbursement.request_id');

        if (
            is_string($reference)
            && trim($reference) !== ''
            && is_string($metadataReference)
            && trim($metadataReference) !== ''
            && trim($reference) !== trim($metadataReference)
        ) {
            return false;
        }

        $haystack = mb_strtolower(json_encode(
            data_get($metadata, 'disbursement', []),
            JSON_THROW_ON_ERROR,
        ));

        return str_contains($haystack, 'otp')
            && str_contains($haystack, 'pending');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function replayApprovedPaynamicsPayout(
        Voucher $voucher,
        array $payload,
    ): SubmitPayCodeClaimResultData {
        $request = $this->approvedPaynamicsPayoutRequest($voucher, $payload);

        $disbursement = $this->approvalReplayDisbursements()
            ->execute($voucher, $request, 1);

        return new SubmitPayCodeClaimResultData(
            voucher_code: (string) $voucher->code,
            claim_type: 'redeem',
            claimed: true,
            status: 'redeemed',
            requested_amount: $request->amount,
            disbursed_amount: $request->amount,
            currency: 'PHP',
            remaining_balance: null,
            fully_claimed: true,
            disbursement: [
                'status' => $disbursement->status,
                'bank_code' => $request->bank_code,
                'account_number' => $request->account_number,
                'transaction_id' => $disbursement->response->transaction_id,
                'gateway' => $disbursement->response->provider,
                'settlement_rail' => $request->settlement_rail,
            ],
            messages: [
                'Voucher redemption payout resumed after approval OTP.',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function approvedPaynamicsPayoutRequest(Voucher $voucher, array $payload): PayoutRequestData
    {
        $metadata = $this->voucherMetadata($voucher);

        $reference = (string) (
            data_get($payload, 'approval.reference_id')
            ?? data_get($payload, 'reference_id')
            ?? data_get($metadata, 'disbursement.transaction_id')
            ?? $voucher->code
        );

        $amount = (float) (
            data_get($metadata, 'disbursement.amount')
            ?? data_get($payload, 'amount')
            ?? 0
        );

        $accountNumber = (string) (
            data_get($payload, 'bank_account.account_number')
            ?? data_get($metadata, 'disbursement.recipient_identifier')
            ?? ''
        );

        $bankCode = (string) (
            data_get($payload, 'bank_account.bank_code')
            ?? data_get($metadata, 'disbursement.metadata.bank_code')
            ?? ''
        );

        return PayoutRequestData::from([
            'reference' => $reference,
            'amount' => $amount,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'recipient_name' => data_get($payload, 'bank_account.account_name', 'Voucher Recipient'),
            'recipient_mobile' => data_get($payload, 'mobile'),
            'settlement_rail' => data_get($metadata, 'disbursement.settlement_rail', 'INSTAPAY'),
            'currency' => 'PHP',
        ]);
    }

    protected function approvalReplayDisbursements(): WithdrawalDisbursementExecutor
    {
        return $this->approvalReplayDisbursements
            ??= app(WithdrawalDisbursementExecutor::class);
    }

    /**
     * @param  array<string, mixed>  $pendingOtp
     */
    protected function toPendingPaynamicsOtpApprovalResult(
        Voucher $voucher,
        SubmitPayCodeClaimResultData $result,
        array $pendingOtp,
    ): ClaimApprovalInitiationResultData {
        $referenceId = (string) data_get($pendingOtp, 'reference_id', $voucher->code);

        return ClaimApprovalInitiationResultData::from([
            'status' => 'approval_required',
            'voucher_code' => (string) $voucher->code,
            'requirements' => ['otp'],
            'actions' => ['otp'],
            'meta' => [
                'provider' => 'paynamics',
                'authorization_type' => 'otp',
                'reference_id' => $referenceId,
                'amount' => data_get($pendingOtp, 'amount'),
                'bank_account_no' => data_get($pendingOtp, 'bank_account_no'),
                'bank_id' => data_get($pendingOtp, 'bank_id'),
                'reason' => data_get($pendingOtp, 'reason'),
                'target' => data_get($pendingOtp, 'target'),
                'otp_required' => true,
                'message' => 'Paynamics payout OTP is pending.',
            ],
            'messages' => [
                'Payout OTP approval required.',
            ],
        ]);
    }

    protected function pendingPaynamicsOtpResult(): PendingPaynamicsOtpClaimResult
    {
        return $this->pendingPaynamicsOtpResult
            ??= app(PendingPaynamicsOtpClaimResult::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function pendingPaynamicsOtpMetadata(
        Voucher $voucher,
        SubmitPayCodeClaimResultData $result,
        array $payload,
    ): ?array {
        $store = app(ClaimApprovalPendingOtpStore::class);

        foreach ($this->pendingPaynamicsOtpReferenceCandidates($voucher, $result, $payload) as $reference) {
            $pending = $store->pending($reference);

            if (is_array($pending)) {
                return [
                    ...$pending,
                    'reference_id' => $reference,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function pendingPaynamicsOtpReferenceCandidates(
        Voucher $voucher,
        SubmitPayCodeClaimResultData $result,
        array $payload,
    ): array {
        $references = array_filter([
            data_get($result, 'disbursement.reference_id'),
            data_get($result, 'disbursement.provider_reference'),
            data_get($result, 'disbursement.provider_tx'),
            data_get($result, 'disbursement.transaction_id'),
            data_get($result, 'disbursement.request_id'),
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        $accounts = array_filter([
            data_get($payload, 'bank_account.account_number'),
            data_get($payload, 'account_number'),
            data_get($payload, 'bank_account_no'),
            data_get($result, 'disbursement.account_number'),
            data_get($result, 'disbursement.bank_account_no'),
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        foreach ($accounts as $account) {
            $references[] = (string) $voucher->code.'-'.trim((string) $account);
        }

        return array_values(array_unique(array_map(
            fn (mixed $value): string => trim((string) $value),
            $references,
        )));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function toDeferredPaynamicsOtpApprovalResult(
        Voucher $voucher,
        SubmitPayCodeClaimResultData $result,
        array $payload,
    ): ClaimApprovalInitiationResultData {
        $referenceId = $this->resolveDeferredPaynamicsOtpReference($voucher, $result, $payload);

        return ClaimApprovalInitiationResultData::from([
            'status' => 'approval_required',
            'voucher_code' => (string) $voucher->code,
            'requirements' => ['otp'],
            'actions' => ['otp'],
            'meta' => [
                'provider' => 'paynamics',
                'authorization_type' => 'otp',
                'reference_id' => $referenceId,
                'otp_required' => true,
                'message' => 'Paynamics payout OTP is pending.',
            ],
            'messages' => [
                'Payout OTP approval required.',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function isApprovalPipelinePaynamicsPayload(array $payload): bool
    {
        return data_get($payload, 'approval.pipeline') === true
            && data_get($payload, 'approval.provider') === 'paynamics';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function isDeferredPaynamicsOtpPendingResult(
        Voucher $voucher,
        SubmitPayCodeClaimResultData $result,
        array $payload,
    ): bool {
        if (! $this->isApprovalPipelinePaynamicsPayload($payload)) {
            return false;
        }

        $haystack = mb_strtolower(json_encode([
            'status' => $result->status,
            'messages' => $result->messages,
            'disbursement' => $result->disbursement,
            'voucher_disbursement' => data_get($this->voucherMetadata($voucher), 'disbursement'),
        ], JSON_THROW_ON_ERROR));

        if (
            str_contains($haystack, 'otp')
            && str_contains($haystack, 'pending')
        ) {
            return true;
        }

        /*
         * In deferred Paynamics approval-pipeline mode, the voucher pipeline may
         * swallow the provider OTP exception and return a successful redemption
         * result with no usable disbursement reference yet.
         */
        return $result->claim_type === 'redeem'
            && $result->status === 'redeemed'
            && (
                $result->disbursement === null
                || $result->disbursement === []
                || data_get($result, 'disbursement.status') === null
                || data_get($result, 'disbursement.needs_review') === true
            );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveDeferredPaynamicsOtpReference(
        Voucher $voucher,
        SubmitPayCodeClaimResultData $result,
        array $payload,
    ): string {
        $reference = data_get($result, 'disbursement.reference_id')
            ?? data_get($result, 'disbursement.provider_reference')
            ?? data_get($result, 'disbursement.provider_tx')
            ?? data_get($result, 'disbursement.transaction_id')
            ?? data_get($result, 'disbursement.request_id');

        $metadata = $this->voucherMetadata($voucher);

        $reference ??= data_get($metadata, 'disbursement.reference_id')
            ?? data_get($metadata, 'disbursement.provider_reference')
            ?? data_get($metadata, 'disbursement.provider_tx')
            ?? data_get($metadata, 'disbursement.transaction_id')
            ?? data_get($metadata, 'disbursement.request_id');

        if (is_string($reference) && trim($reference) !== '') {
            return trim($reference);
        }

        $account = data_get($payload, 'bank_account.account_number')
            ?? data_get($payload, 'account_number')
            ?? data_get($payload, 'bank_account_no');

        if (is_string($account) && trim($account) !== '') {
            return (string) $voucher->code.'-'.trim($account);
        }

        return (string) $voucher->code;
    }

    /**
     * @return array<string, mixed>
     */
    protected function voucherMetadata(Voucher $voucher): array
    {
        $metadata = $voucher->exists
            ? $voucher->fresh()?->metadata
            : $voucher->metadata;

        return is_array($metadata) ? $metadata : [];
    }
}
