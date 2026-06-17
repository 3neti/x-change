<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Contracts\XChangeOnboardingGatewayContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyResolverContract;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleUserSummary;
use Throwable;

final class TurnkeyOnboardingScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private readonly XChangeOnboardingGatewayContract $onboarding,
        private readonly XChangeProviderTopologyResolverContract $topologies,
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
            'issuer_onboarding' => $this->issuerOnboardingCheck($context),
            'bank_onboarding_required' => $this->bankOnboardingRequiredCheck($context),
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

    private function normalizeResult(mixed $result): mixed
    {
        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        return $result;
    }
}
