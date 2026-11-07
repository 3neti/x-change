<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use BadMethodCallException;
use LBHurtado\EmiCore\Contracts\PendingPayoutApproval;
use LBHurtado\EmiCore\Contracts\ProviderClaimantBankProvisioningPolicy;
use LBHurtado\EmiCore\Contracts\ProviderPayoutApprovalReplayPolicy;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Settlement\ProjectCompletionClaimEvidence;
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
use LBHurtado\XChange\Enums\VoucherSliceExecutionStatus;
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Events\DisbursementRejected;
use LBHurtado\XChange\Exceptions\ProviderProvisioningRequired;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherSliceExecution;
use LBHurtado\XChange\Services\BuildProvisioningFlowDescriptor;
use LBHurtado\XChange\Services\Claim\ClaimExecutionPreflight;
use LBHurtado\XChange\Services\ClaimPayoutLocalityScope;
use LBHurtado\XChange\Services\ClaimPayoutParties;
use LBHurtado\XChange\Services\Execution\DurableTreasuryPayout;
use LBHurtado\XChange\Services\Execution\FinalizeApprovedLegacyPayout;
use LBHurtado\XChange\Services\Leads\CampaignDisplaySessions;
use LBHurtado\XChange\Services\NamedVoucherSliceService;
use LBHurtado\XChange\Services\Provisioning\ProviderProvisioningProfileResolver;
use LBHurtado\XChange\Services\ResumeProviderProvisioningFromOnboarding;
use LBHurtado\XChange\Services\Slices\VoucherSliceExecutionCoordinator;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;
use LBHurtado\XChange\Support\Claim\ClaimPreviewExecutionGuard;
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
        protected ?NamedVoucherSliceService $namedSlices = null,
        protected ?ClaimPreviewExecutionGuard $previewExecutionGuard = null,
        protected ?VoucherSliceExecutionCoordinator $sliceExecutions = null,
        protected ?ProjectCompletionClaimEvidence $completionClaimEvidenceProjector = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData|ClaimApprovalInitiationResultData
    {
        $this->previewExecutionGuard()->assertExecutable($voucher);

        $payload = $this->namedSlices()->enrichClaimPayload($voucher, $payload);

        $this->guardClaimantProvisioning($voucher, $payload);

        $validationDecision = app(ClaimExecutionPreflight::class)->assertExecutable($voucher, $payload);

        $isV2Attestation = data_get($voucher->metadata, 'instructions.execution.schema') === 'voucher.execution.v2'
            && data_get($voucher->metadata, 'instructions.execution.driver') === 'settlement_envelope'
            && is_array(data_get($voucher->metadata, 'instructions.execution.events.attest'));
        $preparedClaim = app(PrepareVoucherClaimEvidence::class)->handle($voucher, $payload, $isV2Attestation);
        if ($isV2Attestation && $preparedClaim instanceof VoucherClaim) {
            $voucher->refresh();
        }
        if ($preparedClaim instanceof VoucherClaim) {
            $validationDecision->forceFill(['voucher_claim_id' => $preparedClaim->getKey()])->save();
        }

        app(CampaignDisplaySessions::class)->recordIntake($voucher);

        if ($preparedClaim instanceof VoucherClaim) {
            data_set($payload, '_meta.prepared_claim_id', $preparedClaim->getKey());
        }

        $sliceReservation = $this->sliceExecutions()->reserve($voucher, $payload);

        if ($sliceReservation !== null) {
            $payload = $sliceReservation->payload;

            if ($sliceReservation->replayed
                && $sliceReservation->execution->status !== VoucherSliceExecutionStatus::Reserved
                && ! $this->isApprovalReplay($payload)) {
                return $this->sliceExecutions()->replayResult($sliceReservation->execution);
            }

            $this->restorePreparedClaimForSafeRetry($preparedClaim, $sliceReservation->execution);
        }

        $executor = $this->factory->make($voucher, $payload);

        $localityScope = app(ClaimPayoutLocalityScope::class);
        $previousLocalityKey = $localityScope->get();
        $claimKey = data_get($payload, '_meta.idempotency_key');
        $localityScope->set(is_string($claimKey) && $claimKey !== '' ? $claimKey : null);
        try {
            if ($executor instanceof SettlementExecutionContract) {
                return $this->fromSettlementResult(
                    $executor->execute($voucher, $payload)
                );
            }

            $result = $executor->handle($voucher, $payload);
        } catch (PendingPayoutApproval $e) {
            return ClaimApprovalInitiationResultData::from(
                $this->pendingPaynamicsOtpResult()
                    ->fromException($voucher, $e)
            );
        } catch (Throwable $e) {
            $this->markPreparedClaimExecutionFailed($preparedClaim, $e);

            if ($sliceReservation !== null) {
                $this->sliceExecutions()->failBeforeProvider($sliceReservation->execution);
                $this->sliceExecutions()->indeterminate($sliceReservation->execution);
            }

            if ($this->shouldReplayApprovedPaynamicsPayout($voucher, $payload, $e)) {
                return $this->replayApprovedPaynamicsPayout($voucher, $payload);
            }

            throw $e;
        } finally {
            $localityScope->set($previousLocalityKey);
        }

        $treasuryApproval = config('x-change.payout_locality.submission_guard_enabled', false)
            ? app(DurableTreasuryPayout::class)->approvalFor($voucher, is_string($claimKey) ? $claimKey : null)
            : null;
        if (is_array($treasuryApproval)) {
            return ClaimApprovalInitiationResultData::from([
                'status' => 'approval_required', 'voucher_code' => (string) $voucher->code,
                'requirements' => ['otp'], 'reference_id' => $treasuryApproval['reference_id'],
                'provider' => $treasuryApproval['provider'], 'messages' => ['Payout OTP approval required.'],
                'meta' => $treasuryApproval['metadata'], 'approval_metadata' => $treasuryApproval['metadata'],
            ]);
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

        $claim = $this->recordVoucherClaim->handle($voucher, $normalized, $payload);
        if ($claim->exists) {
            $validationDecision->forceFill(['voucher_claim_id' => $claim->getKey()])->save();
        }
        $this->completionClaimEvidenceProjector?->handle($claim);

        if ($sliceReservation !== null) {
            $this->sliceExecutions()->succeed($sliceReservation->execution, $claim);
        }

        return $normalized;
    }

    private function markPreparedClaimExecutionFailed(?VoucherClaim $claim, Throwable $exception): void
    {
        if (! $claim instanceof VoucherClaim || $claim->status !== 'prepared') {
            return;
        }

        $meta = (array) $claim->meta;
        data_set($meta, 'evidence.execution_status', 'failed_before_finalization');
        data_set($meta, 'evidence.execution_exception', $exception::class);

        $claim->forceFill([
            'status' => 'execution_failed',
            'failure_message' => 'Claim execution failed after evidence capture.',
            'completed_at' => now(),
            'meta' => $meta,
        ])->save();
    }

    private function restorePreparedClaimForSafeRetry(
        ?VoucherClaim $claim,
        VoucherSliceExecution $execution,
    ): void {
        if (! $claim instanceof VoucherClaim
            || $claim->status !== 'execution_failed'
            || $execution->status !== VoucherSliceExecutionStatus::Reserved) {
            return;
        }

        $meta = (array) $claim->meta;
        data_set($meta, 'evidence.execution_status', 'not_started');
        data_forget($meta, 'evidence.execution_exception');

        $claim->forceFill([
            'status' => 'prepared',
            'failure_message' => null,
            'completed_at' => null,
            'meta' => $meta,
        ])->save();
    }

    protected function namedSlices(): NamedVoucherSliceService
    {
        return $this->namedSlices ??= app(NamedVoucherSliceService::class);
    }

    protected function previewExecutionGuard(): ClaimPreviewExecutionGuard
    {
        return $this->previewExecutionGuard ??= app(ClaimPreviewExecutionGuard::class);
    }

    protected function sliceExecutions(): VoucherSliceExecutionCoordinator
    {
        return $this->sliceExecutions ??= app(VoucherSliceExecutionCoordinator::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function normalizeResult(Voucher $voucher, mixed $result, array $payload): SubmitPayCodeClaimResultData
    {
        if ($result instanceof RedeemPayCodeResultData) {
            $treasuryDisbursement = $this->treasuryDisbursementResult($voucher);

            if ($treasuryDisbursement !== null) {
                return $treasuryDisbursement;
            }

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

    protected function treasuryDisbursementResult(
        Voucher $voucher,
    ): ?SubmitPayCodeClaimResultData {
        if (data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation',
        ) === null) {
            return null;
        }

        $disbursement = data_get($voucher->refresh()->metadata, 'disbursement');

        if (! is_array($disbursement)) {
            return null;
        }

        $status = (string) data_get($disbursement, 'status', 'pending');
        $succeeded = in_array($status, ['succeeded', 'completed'], true)
            && data_get($disbursement, 'requires_reconciliation') !== true;
        $amount = (float) data_get($disbursement, 'amount', 0);

        return new SubmitPayCodeClaimResultData(
            voucher_code: (string) $voucher->code,
            claim_type: 'redeem',
            claimed: true,
            status: $succeeded ? 'succeeded' : 'pending_review',
            requested_amount: $amount,
            disbursed_amount: $succeeded ? $amount : 0,
            currency: (string) data_get($disbursement, 'currency', 'PHP'),
            remaining_balance: 0,
            fully_claimed: true,
            disbursement: $disbursement,
            messages: [
                $succeeded
                    ? 'Pay Code redeemed and paid successfully.'
                    : 'Pay Code redeemed; provider settlement requires review.',
            ],
        );

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
        if (! (bool) (
            data_get($voucher->metadata, 'instructions.redemption_form.collect_bank_account')
            ?? data_get($voucher->instructions?->toArray(), 'redemption_form.collect_bank_account', false)
        )) {
            return false;
        }

        $profile = app(ProviderProvisioningProfileResolver::class)->for($provider);
        if ($profile instanceof ProviderClaimantBankProvisioningPolicy) {
            return $profile->requiresClaimantBankProvisioning();
        }

        return preg_match('/\A[a-z0-9_]+\z/', $provider) === 1
            && (bool) config("x-change.provider_runtime.providers.{$provider}.claimant_bank_provisioning_required", false);
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
        if (! $this->isApprovalReplay($payload)) {
            return false;
        }
        $policy = $this->payoutApprovalPolicy($payload);

        return $policy !== null
            && $policy->shouldResume($payload, fn (): array => $this->voucherMetadata($voucher), $e);
    }

    /** @param array<string, mixed> $payload */
    protected function payoutApprovalPolicy(array $payload): ?ProviderPayoutApprovalReplayPolicy
    {
        $provider = data_get($payload, 'approval.provider');
        if (! is_string($provider) || $provider === '') {
            return null;
        }
        $policies = collect(app()->tagged('emi.payout-approval-replay-policies'))
            ->filter(fn (mixed $candidate): bool => $candidate instanceof ProviderPayoutApprovalReplayPolicy
                && $candidate->supportsProvider($provider))
            ->uniqueStrict();

        return $policies->count() === 1 ? $policies->first() : null;
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
            ->execute($voucher, $request, 1, true);

        $claimKey = data_get($payload, '_meta.idempotency_key');
        $outbox = app(DurableTreasuryPayout::class);
        $treasuryApproval = config('x-change.payout_locality.submission_guard_enabled', false)
            && is_string($claimKey) && $outbox->approvalFor($voucher, $claimKey) !== null;

        if ($treasuryApproval && data_get($voucher->metadata, 'treasury.pay_code_reservation') === null
            && in_array($disbursement->status, ['succeeded', 'pending'], true)) {
            app(FinalizeApprovedLegacyPayout::class)
                ->handle($voucher->fresh() ?? $voucher, $request, $disbursement->response);
        }

        $result = new SubmitPayCodeClaimResultData(
            voucher_code: (string) $voucher->code,
            claim_type: 'redeem',
            claimed: true,
            status: $treasuryApproval ? match ($disbursement->status) {
                'succeeded' => 'succeeded',
                'failed' => 'failed',
                default => 'pending_review',
            } : 'redeemed',
            requested_amount: $request->amount,
            disbursed_amount: $treasuryApproval && $disbursement->status !== 'succeeded' ? 0.0 : $request->amount,
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

        if ($treasuryApproval) {
            $this->recordVoucherClaim->handle($voucher, $result, $payload);
            $reconciliation = DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())
                ->where('provider_reference', $request->reference)->where('claim_type', 'withdraw')->firstOrFail();
            if ($disbursement->status === 'succeeded') {
                DisbursementConfirmed::dispatch($reconciliation);
            } elseif ($disbursement->status === 'failed') {
                DisbursementRejected::dispatch($reconciliation);
            }
            $outbox->completeApproval($voucher, $claimKey, $disbursement->status);
        }

        return $result;
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
            ...app(ClaimPayoutParties::class)->resolve($voucher, $payload, (string) data_get($payload, 'bank_account.account_name', 'Voucher Recipient')),
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
                'provider' => data_get($pendingOtp, 'provider', 'paynamics'),
                'authorization_type' => 'otp',
                'reference_id' => $referenceId,
                'amount' => data_get($pendingOtp, 'amount'),
                'bank_account_no' => data_get($pendingOtp, 'bank_account_no'),
                'bank_id' => data_get($pendingOtp, 'bank_id'),
                'reason' => data_get($pendingOtp, 'reason'),
                'target' => data_get($pendingOtp, 'target'),
                'otp_required' => true,
                'message' => data_get($pendingOtp, 'message', 'Paynamics payout OTP is pending.'),
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
                'provider' => data_get($payload, 'approval.provider'),
                'authorization_type' => 'otp',
                'reference_id' => $referenceId,
                'otp_required' => true,
                'message' => $this->payoutApprovalPolicy($payload)?->pendingMessage() ?? 'Payout OTP is pending.',
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
            && $this->payoutApprovalPolicy($payload) !== null;
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

        return $this->payoutApprovalPolicy($payload)?->isDeferredApprovalPending([
            'claim_type' => $result->claim_type,
            'status' => $result->status,
            'messages' => $result->messages,
            'disbursement' => $result->disbursement,
        ], fn (): array => $this->voucherMetadata($voucher)) ?? false;
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
