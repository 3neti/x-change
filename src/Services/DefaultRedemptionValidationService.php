<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Exceptions\RedemptionException;
use LBHurtado\Voucher\Guards\RedemptionGuard;
use LBHurtado\Voucher\MobileVerification\MobileVerificationManager;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Specifications\InputsSpecification;
use LBHurtado\Voucher\Specifications\KycSpecification;
use LBHurtado\Voucher\Specifications\LocationSpecification;
use LBHurtado\Voucher\Specifications\MobileSpecification;
use LBHurtado\Voucher\Specifications\MobileVerificationSpecification;
use LBHurtado\Voucher\Specifications\PayableSpecification;
use LBHurtado\Voucher\Specifications\SecretSpecification;
use LBHurtado\Voucher\Specifications\TimeLimitSpecification;
use LBHurtado\Voucher\Specifications\TimeWindowSpecification;
use LBHurtado\XChange\Contracts\RedemptionValidationContract;

class DefaultRedemptionValidationService implements RedemptionValidationContract
{
    protected RedemptionGuard $guard;

    protected InputsSpecification $inputsSpecification;

    public function __construct(?MobileVerificationManager $mobileVerificationManager = null)
    {
        $this->inputsSpecification = new InputsSpecification;

        $mobileVerificationManager ??= app(MobileVerificationManager::class);

        $this->guard = new RedemptionGuard(
            new SecretSpecification,
            new MobileSpecification,
            new PayableSpecification,
            $this->inputsSpecification,
            new KycSpecification,
            new LocationSpecification,
            new TimeWindowSpecification,
            new TimeLimitSpecification,
            new MobileVerificationSpecification($mobileVerificationManager),
        );
    }

    public function validate(Voucher $voucher, RedemptionContext $context): void
    {
        $result = $this->guard->check($voucher, $context);

        if ($result->failed()) {
            $this->throwValidationException($result->failures, $voucher, $context);
        }
    }

    /**
     * @param  array<int, string>  $failures
     */
    protected function throwValidationException(array $failures, Voucher $voucher, RedemptionContext $context): void
    {
        $messages = [];

        foreach ($failures as $failure) {
            $messages[] = match ($failure) {
                'secret' => 'Invalid secret code provided.',
                'mobile' => 'This voucher is restricted to a specific mobile number.',
                'payable' => sprintf(
                    'This voucher is payable to merchant "%s". Please use the correct merchant account.',
                    (string) data_get($voucher->instructions, 'cash.validation.payable')
                ),
                'inputs' => $this->buildInputsErrorMessage($voucher, $context),
                'kyc' => 'KYC verification is required but not approved. Please complete identity verification.',
                'location' => 'Location data is required for this voucher.',
                'time_window' => 'This voucher can only be redeemed during specific time periods. Please try again later.',
                'time_limit' => 'Redemption time limit exceeded. Please start the redemption process again.',
                'mobile_verification' => 'Mobile verification failed. Your mobile number is not authorized for this voucher.',
                default => "Validation failed: {$failure}",
            };
        }

        throw new RedemptionException(implode(' ', $messages));
    }

    protected function buildInputsErrorMessage(Voucher $voucher, RedemptionContext $context): string
    {
        $missingFields = $this->inputsSpecification->getMissingFields($voucher, $context);

        if ($missingFields === []) {
            return 'Required information is missing. Please complete all required fields.';
        }

        $formattedFields = array_map(
            static fn (string $field): string => str_replace('_', ' ', ucwords($field, '_')),
            $missingFields
        );

        if (count($formattedFields) === 1) {
            $fieldsList = $formattedFields[0];
        } else {
            $lastField = array_pop($formattedFields);
            $fieldsList = implode(', ', $formattedFields).' and '.$lastField;
        }

        return sprintf('Missing required fields: %s.', $fieldsList);
    }
}
