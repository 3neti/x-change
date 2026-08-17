<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\XChange\Enums\DeploymentRuntimeTier;
use Throwable;

final readonly class PreInstallReadinessInspector
{
    public function __construct(
        private DeploymentConfigurationInspector $deploymentConfiguration,
        private InstructionCapabilityReadinessRegistry $instructionCapabilities,
        private ClaimEvidenceStorageReadinessInspector $claimEvidenceStorage,
        private TimeAuthorityInspector $timeAuthority,
    ) {}

    /**
     * @return array{
     *     ready: bool,
     *     profile: string,
     *     missing_variables: list<string>,
     *     checks: list<array{name: string, passed: bool, message: string, meta: array<string, mixed>}>
     * }
     */
    public function inspect(): array
    {
        $deployment = $this->deploymentCheck();
        $profile = (string) data_get($deployment, 'meta.profile', config(
            'x-change.deployment.profile',
            'development',
        ));
        $liveProfile = $profile !== 'development';
        $checks = [
            $deployment,
            $this->timeAuthorityCheck(),
            $this->systemPrincipalIdentityCheck($liveProfile),
            $this->productionApplicationSecurityCheck(),
            $this->partnerApiOAuthCheck(),
            $this->productionOnboardingOtpCheck(),
            $this->instructionCapabilityCheck(),
            $this->claimEvidenceStorageCheck(),
            $this->queueRuntimeCheck($liveProfile),
            $this->schedulerLockCacheCheck($liveProfile),
            $this->emailDeliveryCheck(),
            $this->smsDeliveryCheck(),
            $this->broadcastRuntimeCheck($liveProfile),
        ];
        $missing = collect($checks)
            ->flatMap(static fn (array $check): array => (array) data_get(
                $check,
                'meta.missing_variables',
                [],
            ))
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'ready' => collect($checks)->every(
                static fn (array $check): bool => $check['passed'],
            ),
            'profile' => $profile,
            'missing_variables' => $missing,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function timeAuthorityCheck(): array
    {
        $status = $this->timeAuthority->inspect();

        return $this->check(
            'time authority',
            $status['operational'],
            $status['message'],
            $status,
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function partnerApiOAuthCheck(): array
    {
        $enabled = (bool) config('x-change.partner_api.enabled', false);
        $privateKeyReady = filled(config('passport.private_key'))
            || is_readable(storage_path('oauth-private.key'));
        $publicKeyReady = filled(config('passport.public_key'))
            || is_readable(storage_path('oauth-public.key'));
        $missing = [];

        if ($enabled && ! $privateKeyReady) {
            $missing[] = 'PASSPORT_PRIVATE_KEY';
        }

        if ($enabled && ! $publicKeyReady) {
            $missing[] = 'PASSPORT_PUBLIC_KEY';
        }

        return $this->check(
            'partner api oauth',
            ! $enabled || $missing === [],
            ! $enabled
                ? 'Partner API financial operations are disabled; public discovery remains safe'
                : ($missing === []
                    ? 'Partner API OAuth signing keys are ready'
                    : 'Partner API is enabled but OAuth signing keys are unavailable'),
            [
                'enabled' => $enabled,
                'public_discovery_enabled' => (bool) config('x-change.partner_api.public_discovery_enabled', true),
                'missing_variables' => $missing,
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function claimEvidenceStorageCheck(): array
    {
        try {
            $readiness = $this->claimEvidenceStorage->inspect(
                DeploymentRuntimeTier::resolve((string) config(
                    'x-change.deployment.runtime_tier',
                    'production',
                )),
            );
        } catch (Throwable $exception) {
            return $this->check(
                'claim evidence storage',
                false,
                $exception->getMessage(),
                ['missing_variables' => ['XCHANGE_RUNTIME_TIER']],
            );
        }

        $passed = (bool) data_get($readiness, 'ready', false);

        return $this->check(
            'claim evidence storage',
            $passed,
            (string) data_get(
                $readiness,
                'message',
                'claim-evidence storage readiness could not be determined',
            ),
            [
                ...$readiness,
                'missing_variables' => (array) data_get(
                    $readiness,
                    'missing_variables',
                    ['XCHANGE_CLAIM_EVIDENCE_DISK'],
                ),
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function instructionCapabilityCheck(): array
    {
        $capabilities = $this->instructionCapabilities->all();
        $required = array_values(array_filter(
            (array) config('x-change.instruction_capabilities.required', []),
            static fn (mixed $key): bool => is_string($key) && trim($key) !== '',
        ));
        $unavailable = collect($capabilities)
            ->filter(static fn ($capability): bool => ! $capability->issuanceAllowed);
        $requiredUnavailable = collect($required)
            ->filter(fn (string $key): bool => ! ($capabilities[$key]->issuanceAllowed ?? false))
            ->values();
        $missingVariables = $requiredUnavailable
            ->flatMap(fn (string $key): array => $capabilities[$key]->missingConfiguration ?? [])
            ->unique()
            ->sort()
            ->values()
            ->all();
        $passed = $requiredUnavailable->isEmpty();

        return $this->check(
            'instruction services',
            $passed,
            $passed
                ? sprintf(
                    '%d instruction services ready; %d optional services unavailable',
                    collect($capabilities)->where('issuanceAllowed', true)->count(),
                    $unavailable->count(),
                )
                : 'required instruction services are unavailable: '.$requiredUnavailable->implode(', '),
            [
                'required' => $required,
                'unavailable' => $unavailable->keys()->values()->all(),
                'capabilities' => $this->instructionCapabilities->sanitized(),
                'missing_variables' => $missingVariables,
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function deploymentCheck(): array
    {
        try {
            $result = $this->deploymentConfiguration->inspect();
            $explicit = (bool) config(
                'x-change.deployment.profile_explicitly_configured',
                false,
            );
            $missing = $result['missing_variables'];

            if (! $explicit) {
                $missing[] = 'XCHANGE_DEPLOYMENT_PROFILE';
            }

            $missing = array_values(array_unique($missing));
            sort($missing);
            $passed = $result['ready'] && $explicit;

            return $this->check(
                'deployment configuration',
                $passed,
                $passed
                    ? "deployment profile [{$result['profile']}] is explicitly configured"
                    : 'deployment configuration is incomplete',
                [
                    ...$result,
                    'missing_variables' => $missing,
                    'profile_explicitly_configured' => $explicit,
                ],
            );
        } catch (Throwable $exception) {
            return $this->check(
                'deployment configuration',
                false,
                $exception->getMessage(),
                ['missing_variables' => []],
            );
        }
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function systemPrincipalIdentityCheck(bool $required): array
    {
        $column = trim((string) config('x-change.payout.system_user_column'));
        $identifier = trim((string) config('x-change.payout.system_user_id'));
        $missing = [];

        if ($required && $column !== 'email') {
            $missing[] = 'XCHANGE_SYSTEM_USER_COLUMN';
        }

        if (
            $required
            && filter_var($identifier, FILTER_VALIDATE_EMAIL) === false
        ) {
            $missing[] = 'XCHANGE_SYSTEM_USER_ID';
        }

        $passed = ! $required || (
            $column === 'email'
            && filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false
        );

        return $this->check(
            'system principal identity',
            $passed,
            $passed
                ? ($required
                    ? 'system principal uses a stable email identity'
                    : 'system principal identity is optional for development')
                : 'live deployment profiles require XCHANGE_SYSTEM_USER_COLUMN=email and a valid XCHANGE_SYSTEM_USER_ID email',
            [
                'required' => $required,
                'lookup_column' => $column === '' ? null : $column,
                'identifier_configured' => $identifier !== '',
                'missing_variables' => $missing,
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function productionApplicationSecurityCheck(): array
    {
        $environment = (string) config('app.env');
        $production = $environment === 'production';
        $debug = (bool) config('app.debug');
        $hasStableKey = filled(config('app.key'));
        $usesHttps = str_starts_with((string) config('app.url'), 'https://');
        $secureCookies = (bool) config('session.secure');
        $ready = ! $production || (
            ! $debug
            && $hasStableKey
            && $usesHttps
            && $secureCookies
        );
        $missing = [];

        if ($production && $debug) {
            $missing[] = 'APP_DEBUG';
        }

        if ($production && ! $hasStableKey) {
            $missing[] = 'APP_KEY';
        }

        if ($production && ! $usesHttps) {
            $missing[] = 'APP_URL';
        }

        if ($production && ! $secureCookies) {
            $missing[] = 'SESSION_SECURE_COOKIE';
        }

        return $this->check(
            'production application security',
            $ready,
            $ready
                ? 'production application security controls are ready'
                : 'production requires debug off, a stable key, HTTPS, and secure cookies',
            [
                'environment' => $environment,
                'debug' => $debug,
                'app_key_configured' => $hasStableKey,
                'https' => $usesHttps,
                'secure_cookies' => $secureCookies,
                'missing_variables' => $missing,
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function productionOnboardingOtpCheck(): array
    {
        $environment = (string) config('app.env');
        $production = $environment === 'production';
        $enabled = (bool) config('x-change.onboarding.mobile_verification.enabled', false);
        $required = (bool) config('x-change.onboarding.voucher.require_otp', false);
        $pinSetupRequired = (bool) config(
            'x-change.onboarding.voucher.require_pin_setup',
            true,
        );
        $driver = trim((string) config('x-change.onboarding.identity_otp.driver', 'unavailable'));
        $tokenConfigured = filled(config('x-change.onboarding.identity_otp.token'));
        $baseUrl = trim((string) config('x-change.onboarding.identity_otp.base_url', ''));
        $secureEndpoint = str_starts_with($baseUrl, 'https://');
        $ready = ! $production || (
            $enabled
            && $required
            && $pinSetupRequired
            && $driver === 'txtcmdr'
            && $tokenConfigured
            && $secureEndpoint
        );
        $missing = [];

        if ($production && ! $enabled) {
            $missing[] = 'XCHANGE_MOBILE_VERIFICATION_ENABLED';
        }

        if ($production && ! $required) {
            $missing[] = 'XCHANGE_ONBOARDING_REQUIRE_OTP';
        }

        if ($production && ! $pinSetupRequired) {
            $missing[] = 'XCHANGE_ONBOARDING_REQUIRE_PIN_SETUP';
        }

        if ($production && $driver !== 'txtcmdr') {
            $missing[] = 'XCHANGE_IDENTITY_OTP_DRIVER';
        }

        if ($production && ! $tokenConfigured) {
            $missing[] = 'TXTCMDR_API_TOKEN';
        }

        if ($production && ! $secureEndpoint) {
            $missing[] = 'TXTCMDR_API_URL';
        }

        return $this->check(
            'production onboarding OTP',
            $ready,
            $ready
                ? 'onboarding credential verification is ready'
                : 'production onboarding requires OTP, PIN setup, and a secured txtcmdr identity-verification gateway',
            [
                'environment' => $environment,
                'mobile_verification_enabled' => $enabled,
                'pin_setup_required' => $pinSetupRequired,
                'otp_required' => $required,
                'driver' => $driver,
                'token_configured' => $tokenConfigured,
                'secure_endpoint' => $secureEndpoint,
                'missing_variables' => $missing,
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function queueRuntimeCheck(bool $required): array
    {
        $connection = trim((string) config('queue.default'));
        $feedbackQueue = trim((string) config('x-change.redemption.feedback.queue'));
        $durable = ! in_array($connection, ['', 'sync', 'null'], true);
        $passed = ! $required || ($durable && $feedbackQueue === 'x-change-feedback');
        $missing = [];

        if ($required && ! $durable) {
            $missing[] = 'QUEUE_CONNECTION';
        }

        if ($required && $feedbackQueue !== 'x-change-feedback') {
            $missing[] = 'XCHANGE_REDEMPTION_FEEDBACK_QUEUE';
        }

        return $this->check(
            'durable queue runtime',
            $passed,
            $passed
                ? ($required
                    ? "queue connection [{$connection}] and the dedicated feedback queue are ready"
                    : 'durable queues are optional for the development profile')
                : 'live profiles require a durable queue connection and the dedicated x-change-feedback queue',
            [
                'required' => $required,
                'connection' => $connection,
                'required_queues' => ['default', 'x-change-feedback', 'x-change-funding'],
                'missing_variables' => $missing,
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function schedulerLockCacheCheck(bool $required): array
    {
        $store = trim((string) config('cache.default'));
        $shared = in_array($store, ['database', 'dynamodb', 'memcached', 'redis'], true);
        $passed = ! $required || $shared;

        return $this->check(
            'shared scheduler lock cache',
            $passed,
            $passed
                ? ($required
                    ? "cache store [{$store}] supports shared scheduler locks"
                    : 'shared scheduler locks are optional for the development profile')
                : "cache store [{$store}] is not approved for live scheduler locks",
            [
                'required' => $required,
                'store' => $store,
                'missing_variables' => $passed ? [] : ['CACHE_STORE'],
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function emailDeliveryCheck(): array
    {
        $enabled = (bool) config('x-change.campaigns.delivery.email.enabled');
        $mailer = trim((string) config('mail.default'));
        $from = trim((string) config('mail.from.address'));
        $missing = [];

        if ($enabled && in_array($mailer, ['', 'array', 'log', 'null'], true)) {
            $missing[] = 'MAIL_MAILER';
        }

        if (
            $enabled
            && (
                filter_var($from, FILTER_VALIDATE_EMAIL) === false
                || str_ends_with(mb_strtolower($from), '@example.com')
                || str_ends_with(mb_strtolower($from), '@example.test')
            )
        ) {
            $missing[] = 'MAIL_FROM_ADDRESS';
        }

        if ($enabled && $mailer === 'smtp') {
            foreach ([
                'mail.mailers.smtp.host' => 'MAIL_HOST',
                'mail.mailers.smtp.port' => 'MAIL_PORT',
                'mail.mailers.smtp.username' => 'MAIL_USERNAME',
                'mail.mailers.smtp.password' => 'MAIL_PASSWORD',
            ] as $path => $key) {
                if (! filled(config($path))) {
                    $missing[] = $key;
                }
            }
        }

        if ($enabled && $mailer === 'resend' && ! filled(config('services.resend.key'))) {
            $missing[] = 'RESEND_KEY';
        }

        if ($enabled && $mailer === 'mailgun') {
            foreach ([
                'services.mailgun.domain' => 'MAILGUN_DOMAIN',
                'services.mailgun.secret' => 'MAILGUN_SECRET',
            ] as $path => $key) {
                if (! filled(config($path))) {
                    $missing[] = $key;
                }
            }
        }

        $missing = array_values(array_unique($missing));

        return $this->check(
            'campaign email delivery',
            ! $enabled || $missing === [],
            ! $enabled
                ? 'campaign email delivery is disabled'
                : ($missing === []
                    ? "campaign email delivery uses [{$mailer}]"
                    : 'campaign email delivery is enabled but incomplete'),
            [
                'enabled' => $enabled,
                'mailer' => $mailer,
                'missing_variables' => $missing,
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function smsDeliveryCheck(): array
    {
        $campaignEnabled = (bool) config('x-change.campaigns.delivery.sms.enabled');
        $otpEnabled = config('app.env') === 'production'
            && (bool) config('x-change.onboarding.voucher.require_otp', false);
        $enabled = $campaignEnabled || $otpEnabled;
        $driver = trim((string) config('x-feedback.transports.sms.driver'));
        $sender = trim((string) config('x-feedback.transports.sms.sender'));
        $missing = [];

        if ($enabled && in_array($driver, ['', 'log', 'null'], true)) {
            $missing[] = 'X_FEEDBACK_SMS_DRIVER';
        }

        if ($enabled && $sender === '') {
            $missing[] = 'X_FEEDBACK_SMS_SENDER';
        }

        if ($enabled && $driver === 'engagespark') {
            foreach ([
                'engagespark.api_key' => 'ENGAGESPARK_API_KEY',
                'engagespark.org_id' => 'ENGAGESPARK_ORGANIZATION_ID',
                'engagespark.sender_id' => 'ENGAGESPARK_SENDER_ID',
            ] as $path => $key) {
                if (! filled(config($path))) {
                    $missing[] = $key;
                }
            }
        }

        $missing = array_values(array_unique($missing));

        return $this->check(
            'SMS delivery',
            ! $enabled || $missing === [],
            ! $enabled
                ? 'campaign and production onboarding SMS delivery are disabled'
                : ($missing === []
                    ? "SMS delivery uses [{$driver}]"
                    : 'SMS delivery is enabled but incomplete'),
            [
                'enabled' => $enabled,
                'driver' => $driver,
                'missing_variables' => $missing,
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function broadcastRuntimeCheck(bool $liveProfile): array
    {
        $enabled = (bool) config('x-change.funding.broadcast_enabled');
        $connection = trim((string) config('broadcasting.default'));
        $passed = ! $enabled || ! $liveProfile || ! in_array(
            $connection,
            ['', 'log', 'null'],
            true,
        );

        return $this->check(
            'funding broadcast runtime',
            $passed,
            $passed
                ? ($enabled ? "funding broadcasts use [{$connection}]" : 'funding broadcasts are disabled')
                : 'live funding broadcasts require a real broadcast connection',
            [
                'enabled' => $enabled,
                'connection' => $connection,
                'missing_variables' => $passed ? [] : ['BROADCAST_CONNECTION'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    private function check(
        string $name,
        bool $passed,
        string $message,
        array $meta = [],
    ): array {
        return compact('name', 'passed', 'message', 'meta');
    }
}
