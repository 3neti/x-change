<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Affiliation;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XAffiliation\Actions\EnrollAffiliationMember;
use LBHurtado\XAffiliation\Contracts\AffiliationIdentityKeyFactoryContract;
use LBHurtado\XAffiliation\Models\AffiliationMembership;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Services\Affiliation\AffiliationInstallationNetwork;
use Throwable;

final readonly class EnrollAffiliationRootAccount
{
    public function __construct(
        private AffiliationInstallationNetwork $networks,
        private AffiliationIdentityKeyFactoryContract $identityKeys,
        private EnrollAffiliationMember $enrollMember,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
    ) {}

    public function handle(Model $account, string $authorizationReference): AffiliationMembership
    {
        $authorizationReference = trim($authorizationReference);

        if ($authorizationReference === '') {
            throw new DomainException('Root adoption requires a stable authorization reference.');
        }

        $network = $this->networks->ensure();

        if ($network === null) {
            throw new DomainException('Affiliation networking is disabled.');
        }

        $mobile = $account instanceof HasMobileChannel
            ? $account->getMobileChannel()
            : $account->getAttribute('mobile');

        if (! is_string($mobile) || trim($mobile) === '') {
            $mobile = $account->getAttribute('mobile');
        }

        if (! is_string($mobile) || trim($mobile) === '') {
            $mobile = $account->getRawOriginal('mobile');
        }

        if (! is_string($mobile) || trim($mobile) === '') {
            throw new DomainException('Root adoption requires an Account with a mobile identity.');
        }

        if (array_key_exists('mobile_verified_at', $account->getAttributes())
            && $account->getAttribute('mobile_verified_at') === null) {
            throw new DomainException('Root adoption requires a verified mobile identity.');
        }

        try {
            $canonicalMobile = phone($mobile, 'PH')->formatE164();
        } catch (Throwable $exception) {
            throw new DomainException('Root adoption requires a valid Philippine mobile identity.', previous: $exception);
        }

        $principalReference = $this->principalReferences->resolve($account);

        return $this->enrollMember->handle(
            network: $network,
            subjectType: 'account',
            subjectReference: $principalReference,
            mobileKey: $this->identityKeys->forMobile($network->reference, $canonicalMobile),
            sourceType: 'x-change.affiliation-root-adoption',
            sourceReference: $authorizationReference,
            metadata: [
                'schema' => 'x-change.affiliation-root-adoption.v1',
                'account_model' => $account->getMorphClass(),
            ],
        );
    }
}
