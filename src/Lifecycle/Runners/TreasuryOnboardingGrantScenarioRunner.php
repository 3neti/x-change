<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleClaimSubmitter;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use Throwable;

final readonly class TreasuryOnboardingGrantScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private SystemUserResolverContract $systemUsers,
        private IssueSystemAccountFundingPayCode $issuePayCode,
        private LifecycleClaimSubmitter $claimSubmitter,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryPositionReadModelContract $positions,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $runReference = trim((string) data_get(
            $context->scenario,
            '_runtime.run_reference',
        ));

        if ($runReference === '') {
            return $this->failure(
                $context,
                'Treasury onboarding grants require a stable --run-reference.',
            );
        }

        $mobile = MobileNumber::normalize($context->baseClaimMobile);
        $name = trim((string) data_get(
            $context->scenario,
            'onboarding.name',
            'Sofia Hurtado',
        ));
        $email = mb_strtolower(trim((string) data_get(
            $context->scenario,
            'onboarding.email',
            'sofia@hurtado.ph',
        )));
        $amount = data_get($context->scenario, '_runtime.amount');

        if (! is_numeric($amount)) {
            $amount = data_get($context->scenario, 'amount', 15);
        }

        $amountMinor = (int) round(((float) $amount) * 100);

        if (
            $mobile === null
            || $name === ''
            || $email === ''
            || $amountMinor <= 0
        ) {
            return $this->failure(
                $context,
                'The Treasury onboarding grant identity or amount is invalid.',
            );
        }

        $system = $this->systemUsers->resolve();

        if (! $system instanceof Model || ! $system instanceof Authenticatable) {
            return $this->failure(
                $context,
                'The configured system Account is unavailable.',
            );
        }

        $inventoryBefore = (int) TreasuryInventory::query()
            ->sum('balance_minor');
        $systemBefore = $this->positionBalances($system);

        try {
            $issuance = $this->issuePayCode->handle(
                new IssueSystemAccountFundingPayCodeData(
                    amountMinor: $amountMinor,
                    connectionReference: (string) data_get(
                        $context->scenario,
                        'treasury.connection',
                        'netbank-primary',
                    ),
                    idempotencyReference: $runReference,
                    expiresAt: now()->addSeconds((int) data_get(
                        $context->scenario,
                        'ttl_seconds',
                        604_800,
                    )),
                    evidenceReference: 'system-reserve:'.$runReference,
                    authorizationReference: (string) data_get(
                        $context->scenario,
                        'treasury.authorization_reference',
                        'system-policy:onboarding-grant-v1',
                    ),
                    source: 'treasury_onboarding_grant',
                    metadata: [
                        'custom' => [
                            'onboarding_grant' => [
                                'run_reference_hash' => hash(
                                    'sha256',
                                    $runReference,
                                ),
                                'commercial_treatment' => 'system_sponsored_utility',
                            ],
                        ],
                    ],
                    onboarding: true,
                ),
            );
        } catch (Throwable $exception) {
            return $this->failure(
                $context,
                'The system Account could not reserve the onboarding grant.',
                $exception,
            );
        }

        $voucher = $issuance->voucher;

        if (! $voucher instanceof Voucher) {
            return $this->failure(
                $context,
                'The onboarding grant issuance did not produce a Pay Code.',
            );
        }

        if ((bool) data_get($context->scenario, '_runtime.no_claim', false)) {
            return new ScenarioRunResult(
                exitCode: Command::SUCCESS,
                payload: $this->payload(
                    context: $context,
                    system: $system,
                    voucher: $voucher,
                    amountMinor: $amountMinor,
                    mobile: $mobile,
                    name: $name,
                    email: $email,
                    systemBefore: $systemBefore,
                    inventoryBefore: $inventoryBefore,
                    claimed: false,
                    recipient: null,
                ),
            );
        }

        $verificationRequired = (bool) data_get(
            $voucher->metadata,
            'instructions.execution.metadata.onboarding.mobile_verification_required',
            true,
        );

        try {
            $result = $this->claimSubmitter->submit(
                $context,
                $voucher,
                [
                    'mobile' => $mobile,
                    'recipient_country' => 'PH',
                    'inputs' => [
                        'full_name' => $name,
                        'name' => $name,
                        'email' => $email,
                        'mobile' => $mobile,
                    ] + $this->verificationEvidence($verificationRequired),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failure(
                $context,
                'The onboarding grant claim failed safely.',
                $exception,
            );
        }

        if (! $result instanceof SubmitPayCodeClaimResultData || ! $result->claimed) {
            return $this->failure(
                $context,
                'The onboarding grant did not reach a completed claim.',
            );
        }

        $recipient = $context->issuer::query()
            ->whereIn('mobile', MobileNumber::candidates($mobile))
            ->first();

        if (! $recipient instanceof Model) {
            return $this->failure(
                $context,
                'The claimed onboarding grant has no resolvable recipient Account.',
            );
        }

        return new ScenarioRunResult(
            exitCode: Command::SUCCESS,
            payload: $this->payload(
                context: $context,
                system: $system,
                voucher: $voucher,
                amountMinor: $amountMinor,
                mobile: $mobile,
                name: $name,
                email: $email,
                systemBefore: $systemBefore,
                inventoryBefore: $inventoryBefore,
                claimed: true,
                recipient: $recipient,
            ),
        );
    }

    /**
     * @param  array<string, int>  $systemBefore
     * @return array<string, mixed>
     */
    private function payload(
        ScenarioRunContext $context,
        Model $system,
        Voucher $voucher,
        int $amountMinor,
        string $mobile,
        string $name,
        string $email,
        array $systemBefore,
        int $inventoryBefore,
        bool $claimed,
        ?Model $recipient,
    ): array {
        $systemAfter = $this->positionBalances($system);
        $recipientAfter = $recipient instanceof Model
            ? $this->positionBalances($recipient)
            : null;
        $inventoryAfter = (int) TreasuryInventory::query()
            ->sum('balance_minor');
        $providerAttempts = DisbursementReconciliation::query()
            ->where('voucher_code', $voucher->code)
            ->count();
        $journalEvents = ExecutionJournalEntry::query()
            ->where('subject->id', (string) $voucher->getKey())
            ->orderBy('id')
            ->pluck('event_type')
            ->all();

        return [
            'schema' => 'x-change.lifecycle.treasury-onboarding-grant.v1',
            'scenario' => $context->scenarioKey,
            'label' => $context->label(),
            'mode' => $context->mode(),
            'success' => true,
            'message' => $claimed
                ? 'The system Treasury funded the newly provisioned Account without a provider payout.'
                : 'The system Treasury reserved the onboarding grant; claim it through the canonical browser flow.',
            'grant' => [
                'amount_minor' => $amountMinor,
                'currency' => 'PHP',
                'commercial_treatment' => 'system_sponsored_utility',
                'provider_calls' => false,
            ],
            'pay_code' => [
                'code' => $voucher->code,
                'claimed' => $claimed,
                'claim_url' => route(
                    'x-change.claim.show',
                    ['code' => $voucher->code],
                ),
                'execution_driver' => data_get(
                    $voucher->metadata,
                    'instructions.execution.driver',
                ),
                'default_outcome' => data_get(
                    $voucher->metadata,
                    'instructions.claim.default_outcome',
                ),
            ],
            'recipient' => [
                'name' => $name,
                'email' => $email,
                'mobile' => $this->maskedMobile($mobile),
                'account_id' => $recipient?->getKey(),
                'positions' => $recipientAfter,
            ],
            'accounting' => [
                'system_before' => $systemBefore,
                'system_after' => $systemAfter,
                'inventory_before_minor' => $inventoryBefore,
                'inventory_after_minor' => $inventoryAfter,
                'inventory_unchanged' => $inventoryBefore === $inventoryAfter,
            ],
            'controls' => [
                'run_reference' => 'accepted_and_hashed',
                'provider_attempt_count' => $providerAttempts,
                'provider_calls' => $providerAttempts > 0,
                'claim_count' => $voucher->claims()->count(),
                'journal_events' => $journalEvents,
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function positionBalances(Model $owner): array
    {
        $principal = $this->principalReferences->resolve($owner);
        $positions = collect($this->positions->forPrincipal($principal));

        return [
            'account_funding_reserve_minor' => (int) ($positions->first(
                static fn ($position): bool => $position->purpose
                    === TreasuryPositionPurpose::AccountFundingReserve,
            )?->balanceMinor ?? 0),
            'pay_code_reserve_minor' => (int) ($positions->first(
                static fn ($position): bool => $position->purpose
                    === TreasuryPositionPurpose::PayCodeReserve,
            )?->balanceMinor ?? 0),
            'client_funds_minor' => (int) ($positions->first(
                static fn ($position): bool => $position->purpose
                    === TreasuryPositionPurpose::ClientFunds,
            )?->balanceMinor ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function verificationEvidence(bool $required): array
    {
        if (! $required) {
            return [];
        }

        return [
            'verified_at' => now()->toIso8601String(),
            'otp' => [
                'verified' => true,
                'verified_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function maskedMobile(string $mobile): string
    {
        return str_repeat('*', max(0, mb_strlen($mobile) - 4))
            .mb_substr($mobile, -4);
    }

    private function failure(
        ScenarioRunContext $context,
        string $message,
        ?Throwable $exception = null,
    ): ScenarioRunResult {
        return new ScenarioRunResult(
            exitCode: Command::FAILURE,
            payload: [
                'schema' => 'x-change.lifecycle.treasury-onboarding-grant.v1',
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => $context->mode(),
                'success' => false,
                'message' => $message,
                'exception' => app()->environment(['local', 'testing'])
                    ? $exception?->getMessage()
                    : null,
                'provider_calls' => false,
            ],
        );
    }
}
