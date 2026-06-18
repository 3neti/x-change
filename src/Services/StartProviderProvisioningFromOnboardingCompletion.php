<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Onboarding\Data\OnboardingResultData;
use LBHurtado\Onboarding\Enums\OnboardingPurpose;
use LBHurtado\Onboarding\Models\OnboardingSession;
use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;

class StartProviderProvisioningFromOnboardingCompletion
{
    public function __construct(
        protected ProviderProvisioningManagerContract $provisioning,
        protected ProviderRuntimeSettingsResolverContract $settings,
        protected UserResolverContract $users,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(OnboardingResultData $onboarding): ?array
    {
        $session = $this->findSession($onboarding->reference);

        if (! $session instanceof OnboardingSession) {
            return null;
        }

        $payload = $this->buildProvisioningPayload($session, $onboarding);

        if ($payload === null) {
            return null;
        }

        $owner = $this->resolveOwner($session, $onboarding, $payload);

        if (! is_object($owner)) {
            return null;
        }

        return $this->provisioning->startOrResume($owner, $payload);
    }

    protected function findSession(string $reference): ?OnboardingSession
    {
        /** @var class-string<OnboardingSession> $sessionModel */
        $sessionModel = config('onboarding.models.session', OnboardingSession::class);

        if (! class_exists($sessionModel)) {
            return null;
        }

        return $sessionModel::query()
            ->where('reference', $reference)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildProvisioningPayload(OnboardingSession $session, OnboardingResultData $onboarding): ?array
    {
        $purpose = $this->provisioningPurpose((string) $session->purpose);

        if ($purpose === null) {
            return null;
        }

        $provider = $this->provider($session, $onboarding);
        $mode = $this->mode($session, $onboarding, $purpose, $provider);
        $accountNumber = $this->firstString(
            data_get($onboarding->result, 'account_number'),
            data_get($session->payload, 'account_number'),
            data_get($session->payload, 'bank_account.account_number'),
        );

        return array_filter([
            'provider' => $provider,
            'mode' => $mode,
            'purpose' => $purpose,
            'issuer_id' => data_get($session->payload, 'metadata.issuer_id'),
            'mobile' => $onboarding->subject->mobile ?? $session->mobile,
            'email' => $onboarding->subject->email ?? $session->email,
            'name' => $onboarding->subject->name,
            'bank_code' => $this->firstString(
                data_get($onboarding->result, 'bank_code'),
                data_get($session->payload, 'bank_code'),
                data_get($session->payload, 'bank_account.bank_code'),
            ),
            'account_number' => $accountNumber,
            'account_number_masked' => $this->maskAccountNumber($accountNumber),
            'metadata' => $this->metadata($session, $onboarding),
            'onboarding' => [
                'reference' => $onboarding->reference,
                'status' => $onboarding->status->status->value,
            ],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    protected function resolveOwner(
        OnboardingSession $session,
        OnboardingResultData $onboarding,
        array $payload,
    ): mixed {
        return $this->users->resolve([
            'id' => $onboarding->subject->id ?? $session->subject_id,
            'issuer_id' => data_get($payload, 'issuer_id'),
            'mobile' => data_get($payload, 'mobile'),
            'email' => data_get($payload, 'email'),
            'metadata' => data_get($payload, 'metadata', []),
        ]);
    }

    protected function provisioningPurpose(string $purpose): ?string
    {
        return match ($purpose) {
            OnboardingPurpose::IssuePayCode->value => 'IssuePayCode',
            OnboardingPurpose::BankOnboardingRequired->value,
            OnboardingPurpose::LinkBankAccount->value => 'BankOnboardingRequired',
            default => null,
        };
    }

    protected function provider(OnboardingSession $session, OnboardingResultData $onboarding): string
    {
        return $this->settings->provider(
            $this->firstString(
                data_get($session->payload, 'provider'),
                data_get($onboarding->result, 'provider'),
                data_get($session->meta, 'provider'),
                data_get($onboarding->meta, 'provider'),
            )
        );
    }

    protected function mode(
        OnboardingSession $session,
        OnboardingResultData $onboarding,
        string $purpose,
        string $provider,
    ): string {
        return $this->firstString(
            data_get($session->payload, 'mode'),
            data_get($onboarding->result, 'mode'),
            data_get($session->meta, 'mode'),
            data_get($onboarding->meta, 'mode'),
        ) ?? $this->defaultMode($purpose, $provider);
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadata(OnboardingSession $session, OnboardingResultData $onboarding): array
    {
        $metadata = (array) data_get($session->payload, 'metadata', []);
        $metadata['onboarding_reference'] = $onboarding->reference;

        if ($onboarding->subject->mobile) {
            $metadata['issuer_mobile'] = $onboarding->subject->mobile;
        }

        if ($onboarding->subject->email) {
            $metadata['issuer_email'] = $onboarding->subject->email;
        }

        return $metadata;
    }

    protected function defaultMode(string $purpose, string $provider): string
    {
        return match ($purpose) {
            'IssuePayCode' => $provider === 'paynamics'
                ? ProviderProvisioningMode::WalletCreate->value
                : ProviderProvisioningMode::LedgerWallet->value,
            default => ProviderProvisioningMode::BankAccountLink->value,
        };
    }

    protected function maskAccountNumber(?string $accountNumber): ?string
    {
        if (! is_string($accountNumber) || trim($accountNumber) === '') {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $accountNumber) ?: $accountNumber;
        $suffix = substr($normalized, -4);

        return str_repeat('*', max(strlen($normalized) - 4, 0)).$suffix;
    }

    protected function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
