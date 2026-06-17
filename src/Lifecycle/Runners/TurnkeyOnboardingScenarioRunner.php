<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Contracts\ProviderReadinessGuardContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\XChangeOnboardingGatewayContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyResolverContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleUserSummary;
use Throwable;

final class TurnkeyOnboardingScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private readonly XChangeOnboardingGatewayContract $onboarding,
        private readonly XChangeProviderTopologyResolverContract $topologies,
        private readonly ProviderRuntimeSettingsResolverContract $settings,
        private readonly ProviderProvisioningManagerContract $provisioning,
        private readonly ProviderAccountLinkRepositoryContract $links,
        private readonly ProviderReadinessGuardContract $readinessGuard,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $checks = (array) data_get($context->scenario, 'turnkey.checks', []);

        if ($checks === []) {
            $checks = [
                'mobile_first_auth',
                'user_mobile',
                'provider_topology',
                'issuer_onboarding',
            ];
        }

        $results = [];
        $exitCode = Command::SUCCESS;

        foreach ($checks as $check) {
            $name = is_string($check) ? $check : (string) data_get($check, 'name');

            if ($name === '') {
                continue;
            }

            $result = $this->runCheck($name, $context);

            if (! $result['passed']) {
                $exitCode = Command::FAILURE;
            }

            $results[$name] = $result;

            if (! $context->wantsJson()) {
                $line = sprintf(
                    '[%s] %s',
                    $result['passed'] ? 'PASS' : 'FAIL',
                    $result['message'],
                );

                $result['passed']
                    ? $context->output->line($line)
                    : $context->output->warn($line);
            }
        }

        $summary = [
            'passed' => count(array_filter($results, fn (array $result): bool => (bool) $result['passed'])),
            'failed' => count(array_filter($results, fn (array $result): bool => ! (bool) $result['passed'])),
            'total' => count($results),
        ];

        return new ScenarioRunResult(
            exitCode: $exitCode,
            payload: [
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => 'turnkey_onboarding',
                'issuer' => app(LifecycleUserSummary::class)->fromModel($context->issuer),
                'claim_mobile' => $context->baseClaimMobile,
                'turnkey_checks' => $results,
                'attempt_summary' => $summary,
            ],
        );
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function runCheck(string $name, ScenarioRunContext $context): array
    {
        return match ($name) {
            'mobile_first_auth' => $this->mobileFirstAuthCheck(),
            'fortify_mobile_username' => $this->fortifyMobileUsernameCheck(),
            'user_mobile' => $this->userMobileCheck($context),
            'provider_topology' => $this->providerTopologyCheck($context),
            'provider_runtime_settings' => $this->providerRuntimeSettingsCheck($context),
            'issuer_onboarding' => $this->issuerOnboardingCheck($context),
            'bank_onboarding_required' => $this->bankOnboardingRequiredCheck($context),
            'provider_link_ready' => $this->providerLinkReadyCheck($context),
            'provider_link_pending_blocks' => $this->providerLinkPendingBlocksCheck($context),
            'netbank_bank_account_ready' => $this->netbankBankAccountReadyCheck($context),
            'paynamics_wallet_fake_provisioned' => $this->paynamicsWalletFakeProvisionedCheck($context),
            'issuer_missing_provider_wallet_blocks' => $this->issuerMissingProviderWalletBlocksCheck($context),
            'issuer_ready_provider_wallet_allows' => $this->issuerReadyProviderWalletAllowsCheck($context),
            'claim_missing_bank_account_blocks' => $this->claimMissingBankAccountBlocksCheck($context),
            'claim_ready_provider_account_allows' => $this->claimReadyProviderAccountAllowsCheck($context),
            default => [
                'passed' => false,
                'message' => "Unknown turnkey onboarding check [{$name}].",
                'actual' => null,
            ],
        };
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function mobileFirstAuthCheck(): array
    {
        $enabled = (bool) config('x-change.onboarding.mobile_first_auth', env('XCHANGE_MOBILE_FIRST_AUTH', true));

        return [
            'passed' => $enabled,
            'message' => $enabled
                ? 'Mobile-first auth is enabled.'
                : 'Mobile-first auth is disabled.',
            'actual' => $enabled,
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function fortifyMobileUsernameCheck(): array
    {
        $username = config('fortify.username');

        return [
            'passed' => $username === 'mobile',
            'message' => $username === 'mobile'
                ? 'Fortify username is mobile.'
                : 'Fortify username is not mobile.',
            'actual' => $username,
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function userMobileCheck(ScenarioRunContext $context): array
    {
        $mobile = $context->issuer instanceof HasMobileChannel
            ? $context->issuer->getMobileChannel()
            : null;

        $passed = is_string($mobile) && trim($mobile) !== '';

        return [
            'passed' => $passed,
            'message' => $passed
                ? 'Lifecycle issuer has a mobile channel.'
                : 'Lifecycle issuer does not have a mobile channel.',
            'actual' => $mobile,
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function providerTopologyCheck(ScenarioRunContext $context): array
    {
        try {
            $topology = $this->topologies->resolve(data_get($context->scenario, 'turnkey.provider_topology'));

            return [
                'passed' => true,
                'message' => "Provider topology [{$topology->key()}] resolves.",
                'actual' => [
                    'key' => $topology->key(),
                    'requires_provider_credentials_per_user' => $topology->requiresProviderCredentialsPerUser(),
                    'uses_local_ledger_as_source_of_truth' => $topology->usesLocalLedgerAsSourceOfTruth(),
                ],
            ];
        } catch (Throwable $e) {
            return [
                'passed' => false,
                'message' => $e->getMessage(),
                'actual' => [
                    'error' => $e::class,
                ],
            ];
        }
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function providerRuntimeSettingsCheck(ScenarioRunContext $context): array
    {
        try {
            $provider = $this->settings->provider(data_get($context->scenario, 'turnkey.provider'));
            $topology = $this->settings->topology($provider);

            return [
                'passed' => $provider !== '' && $topology !== '',
                'message' => "Provider runtime settings resolved [{$provider}:{$topology}].",
                'actual' => [
                    'provider' => $provider,
                    'topology' => $topology,
                    'enabled' => $this->settings->isEnabled($provider),
                    'allows_live_provider_scenarios' => $this->settings->allowsLiveProviderScenarios(),
                ],
            ];
        } catch (Throwable $e) {
            return [
                'passed' => false,
                'message' => $e->getMessage(),
                'actual' => [
                    'error' => $e::class,
                ],
            ];
        }
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function issuerOnboardingCheck(ScenarioRunContext $context): array
    {
        $result = $this->onboarding->startIssuer([
            'mobile' => $context->baseClaimMobile,
            'name' => data_get($context->scenario, 'turnkey.issuer_name', 'Lifecycle Issuer'),
            'meta' => [
                'scenario' => $context->scenarioKey,
            ],
        ]);

        return [
            'passed' => true,
            'message' => 'Issuer onboarding gateway accepted the request.',
            'actual' => $this->normalizeResult($result),
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function bankOnboardingRequiredCheck(ScenarioRunContext $context): array
    {
        $result = $this->onboarding->startRedemption([
            'mobile' => $context->baseClaimMobile,
            'disbursement' => [
                'bank_onboarding' => 'required',
            ],
            'meta' => [
                'scenario' => $context->scenarioKey,
            ],
        ]);

        $actual = $this->normalizeResult($result);
        $purpose = is_array($actual) ? data_get($actual, 'purpose') : null;

        return [
            'passed' => $purpose === null || $purpose === 'BankOnboardingRequired',
            'message' => 'Redemption onboarding gateway handled bank onboarding requirement.',
            'actual' => $actual,
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function providerLinkReadyCheck(ScenarioRunContext $context): array
    {
        $provider = (string) data_get($context->scenario, 'turnkey.provider', 'manual');
        $mode = (string) data_get($context->scenario, 'turnkey.provisioning_mode', ProviderProvisioningMode::LedgerWallet->value);

        $result = $this->provisioning->startOrResume($context->issuer, [
            'provider' => $provider,
            'mode' => $mode,
            'purpose' => data_get($context->scenario, 'turnkey.purpose', 'IssuePayCode'),
            'status' => 'ready',
        ]);

        $link = $this->links->findReadyForOwner($context->issuer, $provider, $mode);

        return [
            'passed' => $link !== null && (bool) data_get($result, 'ready') === true,
            'message' => $link !== null
                ? 'Provider account link is ready.'
                : 'Provider account link is not ready.',
            'actual' => [
                'result' => $this->withoutMetadata($result),
                'link_id' => $link?->getKey(),
            ],
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function providerLinkPendingBlocksCheck(ScenarioRunContext $context): array
    {
        $provider = (string) data_get($context->scenario, 'turnkey.provider', 'manual');
        $mode = (string) data_get($context->scenario, 'turnkey.provisioning_mode', ProviderProvisioningMode::LedgerWallet->value);

        $this->provisioning->startOrResume($context->issuer, [
            'provider' => $provider,
            'mode' => $mode,
            'purpose' => data_get($context->scenario, 'turnkey.purpose', 'IssuePayCode'),
            'status' => 'pending',
        ]);

        $readyLink = $this->links->findReadyForOwner($context->issuer, $provider, $mode);
        $latestLink = $this->links->findLatestForOwner($context->issuer, $provider, $mode);

        return [
            'passed' => $readyLink === null && $latestLink?->status === 'pending',
            'message' => $readyLink === null
                ? 'Pending provider account link blocks readiness.'
                : 'Pending provider account link unexpectedly resolved as ready.',
            'actual' => [
                'latest_status' => $latestLink?->status,
                'ready_link_id' => $readyLink?->getKey(),
            ],
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function netbankBankAccountReadyCheck(ScenarioRunContext $context): array
    {
        $result = $this->provisioning->startOrResume($context->issuer, [
            'provider' => 'netbank',
            'mode' => ProviderProvisioningMode::BankAccountLink->value,
            'purpose' => 'BankOnboardingRequired',
            'bank_code' => data_get($context->scenario, 'turnkey.bank_code', 'GXCHPHM2XXX'),
            'account_number_masked' => data_get($context->scenario, 'turnkey.account_number_masked', '*******1987'),
            'status' => 'ready',
        ]);

        return [
            'passed' => (bool) data_get($result, 'ready') === true
                && data_get($result, 'provider') === 'netbank'
                && data_get($result, 'mode') === ProviderProvisioningMode::BankAccountLink->value,
            'message' => 'NetBank bank-account readiness resolved through provider provisioning.',
            'actual' => $this->withoutMetadata($result),
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function paynamicsWalletFakeProvisionedCheck(ScenarioRunContext $context): array
    {
        $result = $this->provisioning->startOrResume($context->issuer, [
            'provider' => 'paynamics',
            'mode' => ProviderProvisioningMode::WalletCreate->value,
            'purpose' => 'IssuePayCode',
            'status' => 'ready',
        ]);

        return [
            'passed' => (bool) data_get($result, 'ready') === true
                && data_get($result, 'provider') === 'paynamics'
                && filled(data_get($result, 'link.provider_wallet_id')),
            'message' => 'Paynamics wallet provisioning mapped to a ready provider account link.',
            'actual' => $this->withoutMetadata($result),
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function issuerMissingProviderWalletBlocksCheck(ScenarioRunContext $context): array
    {
        $owner = $this->freshLifecycleOwner($context, 'missing-wallet');
        $readiness = $this->readinessGuard->evaluateIssuer($owner, 'paynamics');

        return [
            'passed' => ! $readiness->ready && in_array('provider_customer_wallet', $readiness->missing, true),
            'message' => 'Issuer is blocked when Paynamics provider wallet is missing.',
            'actual' => $readiness->toArray(),
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function issuerReadyProviderWalletAllowsCheck(ScenarioRunContext $context): array
    {
        $owner = $this->freshLifecycleOwner($context, 'ready-wallet');

        $this->provisioning->startOrResume($owner, [
            'provider' => 'paynamics',
            'mode' => ProviderProvisioningMode::WalletCreate->value,
            'purpose' => 'IssuePayCode',
            'status' => 'ready',
        ]);

        $readiness = $this->readinessGuard->evaluateIssuer($owner, 'paynamics');

        return [
            'passed' => $readiness->ready,
            'message' => 'Issuer is allowed when Paynamics provider wallet is ready.',
            'actual' => $readiness->toArray(),
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function claimMissingBankAccountBlocksCheck(ScenarioRunContext $context): array
    {
        $owner = $this->freshLifecycleOwner($context, 'missing-bank');
        $readiness = $this->readinessGuard->evaluateClaimant($owner, 'netbank', [
            'requires_bank_account' => true,
        ]);

        return [
            'passed' => ! $readiness->ready && in_array('bank_account_link', $readiness->missing, true),
            'message' => 'Claim is blocked when bank-account readiness is missing.',
            'actual' => $readiness->toArray(),
        ];
    }

    /**
     * @return array{passed: bool, message: string, actual: mixed}
     */
    private function claimReadyProviderAccountAllowsCheck(ScenarioRunContext $context): array
    {
        $owner = $this->freshLifecycleOwner($context, 'ready-bank');

        $this->provisioning->startOrResume($owner, [
            'provider' => 'netbank',
            'mode' => ProviderProvisioningMode::BankAccountLink->value,
            'purpose' => 'BankOnboardingRequired',
            'status' => 'ready',
        ]);

        $readiness = $this->readinessGuard->evaluateClaimant($owner, 'netbank', [
            'requires_bank_account' => true,
        ]);

        return [
            'passed' => $readiness->ready,
            'message' => 'Claim is allowed when provider bank-account readiness is ready.',
            'actual' => $readiness->toArray(),
        ];
    }

    private function normalizeResult(mixed $result): mixed
    {
        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function withoutMetadata(array $result): array
    {
        if (isset($result['link']) && is_array($result['link'])) {
            unset($result['link']['metadata']);
        }

        return $result;
    }

    private function freshLifecycleOwner(ScenarioRunContext $context, string $label): mixed
    {
        $issuer = $context->issuer;

        if (! $issuer instanceof Model) {
            return $issuer;
        }

        $class = $issuer::class;
        $token = Str::lower(Str::random(10));

        return $class::query()->create([
            'name' => 'Lifecycle '.$label,
            'email' => "lifecycle-{$label}-{$token}@example.test",
            'mobile' => '63917'.random_int(1000000, 9999999),
            'password' => Hash::make(Str::random(24)),
        ]);
    }
}
