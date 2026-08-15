<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Brick\Money\Money;
use Carbon\CarbonInterval;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\PartnerApiOperation;
use Throwable;

class PartnerApiMandateGuard
{
    /** @param array<string, mixed> $payload */
    public function assertAllows(array $payload, PartnerApiRequestContext $context): void
    {
        $mandate = $context->client()->mandate;
        $currency = strtoupper((string) data_get($payload, 'cash.currency'));
        $principalMinor = $this->principalMinor($payload);

        $errors = [];

        if (! in_array($currency, (array) data_get($mandate, 'currencies', []), true)) {
            $errors['cash.currency'][] = 'The Partner API mandate does not allow this currency.';
        }

        if ($principalMinor > (int) data_get($mandate, 'maximum_amount_minor', 0)) {
            $errors['cash.amount'][] = 'The amount exceeds the Partner API per-issuance limit.';
        }

        $profile = $this->voucherProfile($payload);

        if (! in_array($profile, (array) data_get($mandate, 'voucher_profiles', []), true)) {
            $errors['onboarding'][] = 'The Partner API mandate does not allow this Pay Code profile.';
        }

        $ttlSeconds = $this->ttlSeconds(data_get($payload, 'ttl'));

        if ($ttlSeconds > (int) data_get($mandate, 'maximum_ttl_seconds', 0)) {
            $errors['ttl'][] = 'The Pay Code expiry exceeds the Partner API mandate.';
        }

        $rail = data_get($payload, 'cash.settlement_rail');
        $normalizedRail = filled($rail) ? strtoupper((string) $rail) : 'automatic';
        $allowedRails = array_map(
            static fn (mixed $value): string => strtoupper((string) $value),
            (array) data_get($mandate, 'settlement_rails', []),
        );

        if (! in_array(strtoupper($normalizedRail), $allowedRails, true)) {
            $errors['cash.settlement_rail'][] = 'The Partner API mandate does not allow this settlement rail.';
        }

        if (! (bool) data_get($mandate, 'unbound_pay_codes', false) && $this->isUnbound($payload)) {
            $errors['cash.validation'][] = 'The Partner API mandate requires a bound recipient, vendor, or secret.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $payload */
    public function principalMinor(array $payload): int
    {
        $currency = strtoupper((string) data_get($payload, 'cash.currency'));
        $amountMinor = Money::of((string) data_get($payload, 'cash.amount'), $currency)
            ->getMinorAmount()
            ->toInt();

        return $amountMinor * max(1, (int) data_get($payload, 'count', 1));
    }

    public function assertDailyPrincipalAvailable(
        int $principalMinor,
        PartnerApiRequestContext $context,
    ): void {
        $limit = (int) data_get($context->client()->mandate, 'daily_principal_limit_minor', 0);

        if ($limit <= 0) {
            throw ValidationException::withMessages([
                'cash.amount' => ['The Partner API daily principal limit is not configured.'],
            ]);
        }

        $used = (int) PartnerApiOperation::query()
            ->whereBelongsTo($context->client(), 'client')
            ->where('operation', 'pay_code_issued')
            ->whereBetween('occurred_at', [now()->startOfDay(), now()->endOfDay()])
            ->sum('principal_minor');

        if ($used + $principalMinor > $limit) {
            throw ValidationException::withMessages([
                'cash.amount' => ['The amount exceeds the Partner API daily principal limit.'],
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    protected function isUnbound(array $payload): bool
    {
        return ! filled(data_get($payload, 'cash.validation.mobile'))
            && ! filled(data_get($payload, 'cash.validation.payable'))
            && ! filled(data_get($payload, 'cash.validation.secret'))
            && data_get($payload, 'claim.claimant.mode', 'unbound') !== 'recipient';
    }

    /** @param array<string, mixed> $payload */
    protected function voucherProfile(array $payload): string
    {
        if ((bool) data_get($payload, 'onboarding', false)) {
            return 'onboarding';
        }

        if (in_array('account_funding', (array) data_get(
            $payload,
            'metadata.custom.settlement.destinations',
            [],
        ), true)) {
            return 'account_funding';
        }

        return 'disbursement';
    }

    protected function ttlSeconds(mixed $ttl): int
    {
        if ($ttl === null || $ttl === '') {
            return 0;
        }

        try {
            $interval = CarbonInterval::make($ttl);

            return $interval instanceof CarbonInterval
                ? (int) $interval->totalSeconds
                : PHP_INT_MAX;
        } catch (Throwable) {
            return PHP_INT_MAX;
        }
    }
}
