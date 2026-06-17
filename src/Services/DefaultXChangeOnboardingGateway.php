<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LBHurtado\XChange\Contracts\XChangeOnboardingGatewayContract;

class DefaultXChangeOnboardingGateway implements XChangeOnboardingGatewayContract
{
    private const ONBOARDING_SERVICE_CONTRACT = 'LBHurtado\\Onboarding\\Contracts\\OnboardingServiceContract';

    private const ONBOARDING_GUARD = 'LBHurtado\\Onboarding\\Support\\OnboardingGuard';

    private const ONBOARDING_INTENT_DATA = 'LBHurtado\\Onboarding\\Data\\OnboardingIntentData';

    private const ONBOARDING_PURPOSE = 'LBHurtado\\Onboarding\\Enums\\OnboardingPurpose';

    public function __construct(
        protected Container $container,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function startIssuer(array $payload): mixed
    {
        return $this->start('IssuePayCode', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function startRedemption(array $payload): mixed
    {
        $purpose = (string) data_get($payload, 'purpose', 'RedeemPayCode');

        if (data_get($payload, 'disbursement.bank_onboarding') === 'required') {
            $purpose = 'BankOnboardingRequired';
        }

        return $this->start($purpose, $payload);
    }

    public function ensureReady(?string $reference): mixed
    {
        if (! is_string($reference) || trim($reference) === '') {
            throw new InvalidArgumentException('An onboarding reference is required.');
        }

        if (! $this->onboardingAvailable()) {
            return [
                'ready' => false,
                'available' => false,
                'reference' => $reference,
                'reason' => '3neti/onboarding is not installed, not discoverable, or not registered.',
            ];
        }

        return $this->container
            ->make(self::ONBOARDING_GUARD)
            ->ensureReady($reference);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function start(string $purposeCase, array $payload): mixed
    {
        if (! $this->onboardingAvailable()) {
            return [
                'available' => false,
                'status' => 'unavailable',
                'purpose' => $purposeCase,
                'mobile' => data_get($payload, 'mobile'),
                'reason' => '3neti/onboarding is not installed, not discoverable, or not registered.',
            ];
        }

        $intentClass = self::ONBOARDING_INTENT_DATA;
        $purposeClass = self::ONBOARDING_PURPOSE;

        if (! defined($purposeClass.'::'.$purposeCase)) {
            throw new InvalidArgumentException("Unsupported onboarding purpose [{$purposeCase}].");
        }

        $intent = new $intentClass(
            purpose: constant($purposeClass.'::'.$purposeCase),
            mobile: data_get($payload, 'mobile'),
            email: data_get($payload, 'email'),
            name: data_get($payload, 'name'),
            bankCode: data_get($payload, 'bank_code', data_get($payload, 'bank_account.bank_code')),
            accountNumber: data_get($payload, 'account_number', data_get($payload, 'bank_account.account_number')),
            payload: $payload,
            meta: (array) data_get($payload, 'meta', data_get($payload, 'metadata', [])),
        );

        return $this->container
            ->make(self::ONBOARDING_SERVICE_CONTRACT)
            ->start($intent);
    }

    protected function onboardingAvailable(): bool
    {
        return interface_exists(self::ONBOARDING_SERVICE_CONTRACT)
            && class_exists(self::ONBOARDING_GUARD)
            && class_exists(self::ONBOARDING_INTENT_DATA)
            && enum_exists(self::ONBOARDING_PURPOSE)
            && $this->container->bound(self::ONBOARDING_SERVICE_CONTRACT)
            && $this->container->bound(self::ONBOARDING_GUARD);
    }
}
