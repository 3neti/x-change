<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\XChange\Contracts\Execution\StoredValueHolderAuthorityContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Execution\StoredValueHolderAuthorityData;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final readonly class AuthenticatedStoredValueHolderAuthority implements StoredValueHolderAuthorityContract
{
    public function __construct(
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
    ) {}

    public function isReady(): bool
    {
        try {
            $modelClass = config('x-change.onboarding.issuer_model');

            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                return false;
            }

            $model = new $modelClass;

            return $model instanceof Model
                && Schema::hasColumns($model->getTable(), ['mobile', 'mobile_verified_at']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function authorize(ExecutionContextData $context): StoredValueHolderAuthorityData
    {
        $holder = Auth::user();

        if (! $holder instanceof Model || ! $holder->exists) {
            throw new StoredValueSpendRejectedException(
                'Reusable Balance activation requires an authenticated Account holder.',
            );
        }

        $verifiedAt = $holder->getAttribute('mobile_verified_at');
        $holderMobile = MobileNumber::normalize($holder->getAttribute('mobile'));
        $contactMobile = MobileNumber::normalize($context->contact->mobile);

        if ($verifiedAt === null || $holderMobile === null || ! hash_equals($holderMobile, (string) $contactMobile)) {
            throw new StoredValueSpendRejectedException(
                'Reusable Balance activation requires the authenticated holder\'s verified mobile number.',
            );
        }

        $principalReference = $this->principalReferences->resolve($holder);

        return new StoredValueHolderAuthorityData(
            holder: $holder,
            authorityReference: 'stored-value-holder:verified-account:'.$principalReference,
            principalReference: $principalReference,
        );
    }
}
