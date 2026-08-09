<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Data\Cockpit\CockpitPayeePolicyData;
use LBHurtado\XChange\Enums\CockpitPayeeKind;

final class CockpitPayeePolicy
{
    public function classify(?string $value): CockpitPayeePolicyData
    {
        $input = trim((string) $value);

        if ($input === '' || strtoupper($input) === 'CASH') {
            return $this->policy(
                CockpitPayeeKind::Open,
                null,
                'Open Recipient',
                false,
                true,
                'Anyone who meets the other claim requirements may claim.',
            );
        }

        $quoted = $this->quotedSecret($input);

        if ($quoted !== null) {
            return $this->secretPolicy($quoted, true);
        }

        if (str_starts_with($input, '"') || str_ends_with($input, '"')) {
            return $this->invalid('Close both double quotes to use this value as a release code.');
        }

        $mobile = $this->normalizePhilippineMobile($input);

        if ($mobile !== null) {
            return $this->policy(
                CockpitPayeeKind::Mobile,
                $mobile,
                'Mobile ending '.substr($mobile, -4),
                false,
                true,
                'Mobile match and OTP will be required.',
            );
        }

        if ($this->looksLikePhilippineMobile($input)) {
            return $this->invalid(
                'Enter a complete Philippine mobile number, or wrap it in double quotes to use it as a release code.',
            );
        }

        if (filter_var($input, FILTER_VALIDATE_EMAIL) !== false) {
            return $this->policy(
                CockpitPayeeKind::Email,
                strtolower($input),
                strtolower($input),
                false,
                false,
                'Email-bound Pay Codes require email OTP, which is not available yet.',
            );
        }

        if (str_contains($input, '@') && ! str_starts_with($input, '@')) {
            return $this->invalid(
                'Enter a complete email address, or wrap it in double quotes to use it as a release code.',
            );
        }

        if (str_starts_with($input, '@')) {
            if (preg_match('/^@[A-Za-z0-9][A-Za-z0-9._-]{0,19}$/', $input) !== 1) {
                return $this->invalid(
                    'Vendor aliases must start with @ and contain only letters, numbers, periods, underscores, or hyphens.',
                );
            }

            $alias = strtoupper(substr($input, 1));

            return $this->policy(
                CockpitPayeeKind::Vendor,
                $alias,
                '@'.$alias,
                false,
                true,
                'The claimant must use this registered vendor alias.',
            );
        }

        return $this->secretPolicy($input, false);
    }

    private function quotedSecret(string $value): ?string
    {
        if (strlen($value) < 2 || ! str_starts_with($value, '"') || ! str_ends_with($value, '"')) {
            return null;
        }

        return substr($value, 1, -1);
    }

    private function secretPolicy(string $value, bool $explicit): CockpitPayeePolicyData
    {
        if (strlen($value) < 4 || strlen($value) > 255) {
            return $this->invalid('Release codes must contain 4 to 255 characters.');
        }

        return $this->policy(
            CockpitPayeeKind::Secret,
            $value,
            'Release Code',
            $explicit,
            true,
            $explicit
                ? 'Quoted value will be required as a release code.'
                : 'This value will be required as a release code.',
        );
    }

    private function normalizePhilippineMobile(string $value): ?string
    {
        $normalized = preg_replace('/[\s().-]+/', '', trim($value));

        if (! is_string($normalized)) {
            return null;
        }

        if (preg_match('/^\+639\d{9}$/', $normalized) === 1) {
            return $normalized;
        }

        if (preg_match('/^639\d{9}$/', $normalized) === 1) {
            return '+'.$normalized;
        }

        if (preg_match('/^09\d{9}$/', $normalized) === 1) {
            return '+63'.substr($normalized, 1);
        }

        if (preg_match('/^9\d{9}$/', $normalized) === 1) {
            return '+63'.$normalized;
        }

        return null;
    }

    private function looksLikePhilippineMobile(string $value): bool
    {
        $normalized = preg_replace('/[\s().-]+/', '', trim($value));

        return is_string($normalized)
            && preg_match('/^(?:\+?63|0?9)/', $normalized) === 1;
    }

    private function invalid(string $message): CockpitPayeePolicyData
    {
        return $this->policy(
            CockpitPayeeKind::Invalid,
            null,
            'Needs Attention',
            false,
            false,
            $message,
        );
    }

    private function policy(
        CockpitPayeeKind $kind,
        ?string $normalizedValue,
        string $displayValue,
        bool $explicitSecret,
        bool $issuable,
        string $message,
    ): CockpitPayeePolicyData {
        return new CockpitPayeePolicyData(
            kind: $kind,
            normalizedValue: $normalizedValue,
            displayValue: $displayValue,
            explicitSecret: $explicitSecret,
            issuable: $issuable,
            message: $message,
        );
    }
}
