<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleUserSummary;
use Throwable;

final class LiveProviderVerificationScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private readonly ProviderProvisioningManagerContract $provisioning,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $owner = $this->verificationOwner($context);
        $payload = (array) data_get($context->scenario, 'live_provider.payload', []);

        try {
            $result = $this->provisioning->startOrResume($owner, $payload);
            $ready = (bool) data_get($result, 'ready');

            if (! $context->wantsJson()) {
                $context->output->line(sprintf(
                    '[%s] Live provider verification completed for %s:%s.',
                    $ready ? 'PASS' : 'WARN',
                    (string) data_get($result, 'provider', data_get($payload, 'provider', 'provider')),
                    (string) data_get($result, 'mode', data_get($payload, 'mode', 'mode')),
                ));
            }

            return new ScenarioRunResult(
                exitCode: $ready ? Command::SUCCESS : Command::FAILURE,
                payload: [
                    'success' => $ready,
                    'scenario' => $context->scenarioKey,
                    'label' => $context->label(),
                    'mode' => 'live_provider_verification',
                    'owner' => $this->ownerSummary($owner),
                    'provider_verification' => $this->publicResult($result),
                    'attempt_summary' => [
                        'passed' => $ready ? 1 : 0,
                        'failed' => $ready ? 0 : 1,
                        'total' => 1,
                    ],
                ],
            );
        } catch (Throwable $e) {
            return new ScenarioRunResult(
                exitCode: Command::FAILURE,
                payload: [
                    'success' => false,
                    'scenario' => $context->scenarioKey,
                    'label' => $context->label(),
                    'mode' => 'live_provider_verification',
                    'owner' => $this->ownerSummary($owner),
                    'message' => $e->getMessage(),
                    'error' => $e::class,
                    'attempt_summary' => [
                        'passed' => 0,
                        'failed' => 1,
                        'total' => 1,
                    ],
                ],
            );
        }
    }

    private function verificationOwner(ScenarioRunContext $context): mixed
    {
        $issuer = $context->issuer;

        if (! $issuer instanceof Model) {
            return $issuer;
        }

        $class = $issuer::class;
        $token = Str::lower(Str::random(10));

        return $class::query()->create([
            'name' => 'Lifecycle live provider',
            'email' => "lifecycle-live-provider-{$token}@example.test",
            'mobile' => '63917'.random_int(1000000, 9999999),
            'password' => Hash::make(Str::random(24)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerSummary(mixed $owner): array
    {
        if ($owner instanceof Model) {
            return app(LifecycleUserSummary::class)->fromModel($owner);
        }

        return [
            'type' => is_object($owner) ? $owner::class : gettype($owner),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function publicResult(array $result): array
    {
        $link = (array) data_get($result, 'link', []);

        return $this->redact([
            'provider' => data_get($result, 'provider'),
            'topology' => data_get($result, 'topology'),
            'mode' => data_get($result, 'mode'),
            'status' => data_get($result, 'status'),
            'ready' => data_get($result, 'ready'),
            'link_id' => data_get($result, 'link_id'),
            'provider_account_id' => data_get($link, 'provider_account_id'),
            'provider_wallet_id' => data_get($link, 'provider_wallet_id'),
            'provider_bank_account_id' => data_get($link, 'provider_bank_account_id'),
            'verification_status' => data_get($link, 'verification_status'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function redact(array $value): array
    {
        $redacted = [];

        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey((string) $key)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($item) ? $this->redact($item) : $item;
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'secret')
            || str_contains($key, 'password')
            || str_contains($key, 'token')
            || str_contains($key, 'signature')
            || str_contains($key, 'authorization')
            || str_contains($key, 'merchant_key')
            || $key === 'raw'
            || $key === 'request'
            || $key === 'response';
    }
}
