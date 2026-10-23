<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\XChange\Contracts\Execution\StoredValueHolderAuthorityContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Execution\StoredValueHolderAuthorityData;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LBHurtado\XChange\Support\Claim\VerifiedStoredValueClaim;

final class AuthenticatedStoredValueHolderAuthority implements StoredValueHolderAuthorityContract
{
    private ?bool $ready = null;

    public function __construct(
        private readonly TreasuryPrincipalReferenceResolverContract $principalReferences,
        private readonly ?VerifiedStoredValueClaim $claim = null,
    ) {}

    public function isReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }

        try {
            return $this->ready = Schema::hasColumns((new Contact)->getTable(), ['mobile', 'country']);
        } catch (\Throwable) {
            return $this->ready = false;
        }
    }

    public function authorize(ExecutionContextData $context): StoredValueHolderAuthorityData
    {
        if ($this->claim?->isPublicClaim() === true) {
            $verifiedMobile = $this->claim->verifiedMobile();
            $contactMobile = MobileNumber::normalize($context->contact->mobile);

            if ($verifiedMobile === null || $contactMobile === null
                || ! hash_equals($verifiedMobile, $contactMobile)
                || ! $context->contact instanceof Contact
                || ! $context->contact->exists) {
                throw new StoredValueSpendRejectedException('Verify your mobile number to activate Reusable Balance.');
            }

            $principalReference = $this->principalReferences->resolve($context->contact);

            return new StoredValueHolderAuthorityData(
                holder: $context->contact,
                authorityReference: 'stored-value-holder:verified-contact:'.hash_hmac('sha256', $verifiedMobile, (string) config('app.key')),
                principalReference: $principalReference,
            );
        }

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
