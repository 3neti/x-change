<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Disbursement;

use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\MoneyIssuer\Contracts\InstitutionResolverContract;
use LBHurtado\XChange\Contracts\PayoutDestinationValidatorContract;
use LBHurtado\XChange\Contracts\ProviderPayoutDestinationValidationContract;
use LBHurtado\XChange\Data\Disbursement\PayoutDestinationValidationData;
use Throwable;

final readonly class LayeredPayoutDestinationValidator implements PayoutDestinationValidatorContract
{
    public function __construct(
        private InstitutionResolverContract $institutions,
        private ProviderPayoutDestinationValidationContract $provider,
    ) {}

    public function validate(
        string $bankCode,
        string $accountNumber,
        string $settlementRail,
        ?string $mobile = null,
    ): PayoutDestinationValidationData {
        $normalizedBankCode = mb_strtoupper(trim($bankCode));
        $normalizedAccount = preg_replace('/\D+/', '', $accountNumber) ?? '';
        $normalizedMobile = $this->normalizeMobile($mobile);

        try {
            $institution = $this->institutions->resolve(
                $normalizedBankCode,
                SettlementRail::from(mb_strtoupper(trim($settlementRail))),
            );
        } catch (Throwable $exception) {
            return $this->invalid(
                $normalizedBankCode,
                $normalizedAccount,
                $normalizedMobile,
                $exception->getMessage(),
                ['institution' => 'invalid'],
            );
        }

        if (strlen($normalizedAccount) < 8 || strlen($normalizedAccount) > 20) {
            return $this->invalid(
                $institution->bankCode,
                $normalizedAccount,
                $normalizedMobile,
                'Enter a valid 8 to 20 digit receiving account number.',
                ['institution' => 'valid', 'account_format' => 'invalid'],
            );
        }

        if (
            in_array($institution->bankCode, ['GXCHPHM2XXX', 'PAPHPHM1XXX'], true)
            && ! preg_match('/^09\d{9}$/', $normalizedAccount)
        ) {
            return $this->invalid(
                $institution->bankCode,
                $normalizedAccount,
                $normalizedMobile,
                'This wallet requires an 11-digit Philippine mobile account beginning with 09.',
                ['institution' => 'valid', 'account_format' => 'invalid'],
            );
        }

        $providerResult = $this->provider->validate(
            $institution->bankCode,
            $normalizedAccount,
            $settlementRail,
            $normalizedMobile,
        );

        if ($providerResult instanceof PayoutDestinationValidationData) {
            return $providerResult;
        }

        return new PayoutDestinationValidationData(
            status: 'format_valid_provider_unverified',
            bankCode: $institution->bankCode,
            accountNumber: $normalizedAccount,
            mobile: $normalizedMobile,
            message: 'Format checks passed. The receiving institution will make the final decision.',
            providerVerified: false,
            checks: [
                'institution' => 'valid',
                'account_format' => 'valid',
                'provider_account_inquiry' => 'unavailable',
            ],
        );
    }

    /** @param array<string, mixed> $checks */
    private function invalid(
        string $bankCode,
        string $accountNumber,
        ?string $mobile,
        string $message,
        array $checks,
    ): PayoutDestinationValidationData {
        return new PayoutDestinationValidationData(
            status: 'invalid',
            bankCode: $bankCode,
            accountNumber: $accountNumber,
            mobile: $mobile,
            message: $message,
            providerVerified: false,
            checks: $checks,
        );
    }

    private function normalizeMobile(?string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile) ?? '';

        if (str_starts_with($digits, '639') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        return $digits !== '' ? $digits : null;
    }
}
