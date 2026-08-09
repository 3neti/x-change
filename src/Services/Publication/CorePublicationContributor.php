<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Publication;

use LBHurtado\XChange\Contracts\Publication\XChangePublicationContributor;
use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;
use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationOverwritePolicy;
use LBHurtado\XChange\Enums\PublicationScope;

final class CorePublicationContributor implements XChangePublicationContributor
{
    /** @return iterable<PublicationDefinitionData> */
    public function publications(): iterable
    {
        yield from $this->buildPublications();
        yield from $this->installPublications();
        yield from $this->advancedPublications();
    }

    /** @return iterable<PublicationDefinitionData> */
    private function buildPublications(): iterable
    {
        yield $this->build('x-change.ui', '3neti/x-change', 'x-change-ui', 'Cockpit and claim frontend build inputs.', [
            resource_path('js/cockpit'),
            resource_path('js/pages/x-change'),
        ]);
        yield $this->build('x-change.assets', '3neti/x-change', 'x-change-assets', 'X-Change public branding assets.', [
            public_path('vendor/x-change/favicon.svg'),
            public_path('vendor/x-change/images/brand-library/inventory.json'),
            public_path('vendor/x-change/images/brand-library/x-change/svg/x-change-logo.svg'),
            public_path('vendor/x-change/images/brand-library/g-clef-pulley/svg/g-clef-pulley-logo.svg'),
        ]);
        yield $this->build('x-change.form-flow-driver', '3neti/x-change', 'x-change-form-flow-drivers', 'Voucher redemption Form Flow driver.', [
            config_path('form-flow-drivers/voucher-redemption.yaml'),
        ]);
        yield $this->build('x-change.envelope-driver', '3neti/x-change', 'x-change-envelope-drivers', 'Account funding settlement-envelope driver.', [
            config_path('envelope-drivers/account-funding-review.yaml'),
        ]);
        yield $this->build('form-flow.drivers', '3neti/form-flow', 'form-flow-drivers', 'Form Flow package-owned drivers.', [
            config_path('form-flow-drivers'),
        ], 'LBHurtado\\FormFlowManager\\FormFlowServiceProvider');
        yield $this->build('form-flow.views', '3neti/form-flow', 'form-flow-views', 'Form Flow Vue build inputs.', [
            resource_path('js/pages/form-flow/core'),
        ], 'LBHurtado\\FormFlowManager\\FormFlowServiceProvider');

        foreach ($this->handlerBuildPublications() as $definition) {
            yield $definition;
        }

        yield $this->build('x-rider.drivers', '3neti/x-rider', 'x-rider-drivers', 'Rider presentation drivers.', [
            config_path('x-rider-drivers'),
        ], 'LBHurtado\\XRider\\XRiderServiceProvider');
        yield $this->build('x-rider.ui', '3neti/x-rider', 'x-rider-ui', 'Rider Vue build inputs.', [
            resource_path('js/pages/x-rider'),
        ], 'LBHurtado\\XRider\\XRiderServiceProvider');
        yield $this->build('x-ray.ui', '3neti/x-ray', 'x-ray-assets', 'X-Ray frontend build inputs.', [
            resource_path('js/vendor/x-ray'),
        ], 'LBHurtado\\XRay\\XRayServiceProvider');
    }

    /** @return iterable<PublicationDefinitionData> */
    private function handlerBuildPublications(): iterable
    {
        $handlers = [
            ['kyc', '3neti/form-handler-kyc', 'kyc-handler-stubs', 'LBHurtado\\FormHandlerKYC\\KYCHandlerServiceProvider'],
            ['location', '3neti/form-handler-location', 'location-handler-stubs', 'LBHurtado\\FormHandlerLocation\\LocationHandlerServiceProvider'],
            ['otp', '3neti/form-handler-otp', 'otp-handler-stubs', 'LBHurtado\\FormHandlerOtp\\OtpHandlerServiceProvider'],
            ['selfie', '3neti/form-handler-selfie', 'selfie-handler-stubs', 'LBHurtado\\FormHandlerSelfie\\SelfieHandlerServiceProvider'],
            ['signature', '3neti/form-handler-signature', 'signature-handler-stubs', 'LBHurtado\\FormHandlerSignature\\SignatureHandlerServiceProvider'],
        ];

        foreach ($handlers as [$name, $owner, $tag, $provider]) {
            yield $this->build(
                "form-handler.{$name}.ui",
                $owner,
                $tag,
                ucfirst($name).' Form Flow Vue build inputs.',
                [resource_path("js/pages/form-flow/{$name}")],
                $provider,
            );
        }
    }

    /** @return iterable<PublicationDefinitionData> */
    private function installPublications(): iterable
    {
        yield $this->install('x-change.shell', 'x-change-shell', 'Application shell integration.');
        yield $this->install('x-change.auth', 'x-change-auth-scaffold', 'Mobile-first authentication scaffold.');
        yield $this->install('x-change.auth-tests', 'x-change-auth-tests', 'Mobile-first authentication tests.');
        yield $this->install('x-change.settings', 'x-change-settings', 'Mobile-first settings scaffold.');
        yield $this->install('x-change.settings-tests', 'x-change-settings-tests', 'Mobile-first settings tests.');
        yield $this->install('x-change.host-migrations', 'x-change-host-migrations', 'Host user compatibility migration.');
        yield new PublicationDefinitionData(
            id: 'onboarding.migrations',
            owner: '3neti/onboarding',
            scope: PublicationScope::Install,
            invocation: PublicationInvocation::Tag,
            target: 'onboarding-migrations',
            overwritePolicy: PublicationOverwritePolicy::CreateIfMissing,
            description: 'Onboarding database migrations.',
            available: class_exists('LBHurtado\\Onboarding\\OnboardingServiceProvider'),
        );
    }

    /** @return iterable<PublicationDefinitionData> */
    private function advancedPublications(): iterable
    {
        yield $this->advanced('x-change.config', '3neti/x-change', 'x-change-config', 'Full advanced X-Change configuration override.');
        yield $this->advanced('x-change.scripts', '3neti/x-change', 'x-change-scripts', 'Lifecycle diagnostic scripts.');
        yield $this->advanced('x-change.auth-user', '3neti/x-change', 'x-change-auth-user-replacement', 'Complete host User model replacement.');
        yield $this->advanced('form-flow.config', '3neti/form-flow', 'form-flow-config', 'Form Flow configuration override.', 'LBHurtado\\FormFlowManager\\FormFlowServiceProvider');
        yield $this->advanced('x-rider.config', '3neti/x-rider', 'x-rider-config', 'Rider configuration override.', 'LBHurtado\\XRider\\XRiderServiceProvider');
        yield $this->advanced('x-ray.config', '3neti/x-ray', 'x-ray-config', 'X-Ray configuration override.', 'LBHurtado\\XRay\\XRayServiceProvider');
        yield $this->advanced('x-ray.views', '3neti/x-ray', 'x-ray-views', 'X-Ray view overrides.', 'LBHurtado\\XRay\\XRayServiceProvider');
        yield $this->advanced('onboarding.config', '3neti/onboarding', 'onboarding-config', 'Onboarding configuration override.', 'LBHurtado\\Onboarding\\OnboardingServiceProvider');

        $handlers = [
            ['kyc', '3neti/form-handler-kyc', 'kyc-handler-config', 'LBHurtado\\FormHandlerKYC\\KYCHandlerServiceProvider'],
            ['location', '3neti/form-handler-location', 'location-handler-config', 'LBHurtado\\FormHandlerLocation\\LocationHandlerServiceProvider'],
            ['otp', '3neti/form-handler-otp', 'otp-handler-config', 'LBHurtado\\FormHandlerOtp\\OtpHandlerServiceProvider'],
            ['selfie', '3neti/form-handler-selfie', 'selfie-handler-config', 'LBHurtado\\FormHandlerSelfie\\SelfieHandlerServiceProvider'],
            ['signature', '3neti/form-handler-signature', 'signature-handler-config', 'LBHurtado\\FormHandlerSignature\\SignatureHandlerServiceProvider'],
        ];

        foreach ($handlers as [$name, $owner, $tag, $provider]) {
            yield $this->advanced("form-handler.{$name}.config", $owner, $tag, ucfirst($name).' handler configuration override.', $provider);
        }
    }

    /** @param list<string> $verificationPaths */
    private function build(
        string $id,
        string $owner,
        string $tag,
        string $description,
        array $verificationPaths,
        ?string $provider = null,
    ): PublicationDefinitionData {
        return new PublicationDefinitionData(
            id: $id,
            owner: $owner,
            scope: PublicationScope::Build,
            invocation: PublicationInvocation::Tag,
            target: $tag,
            overwritePolicy: PublicationOverwritePolicy::AlwaysGenerated,
            description: $description,
            available: $provider === null || class_exists($provider),
            generated: true,
            verificationPaths: $verificationPaths,
        );
    }

    private function install(string $id, string $tag, string $description): PublicationDefinitionData
    {
        return new PublicationDefinitionData(
            id: $id,
            owner: '3neti/x-change',
            scope: PublicationScope::Install,
            invocation: PublicationInvocation::Tag,
            target: $tag,
            overwritePolicy: PublicationOverwritePolicy::CreateIfMissing,
            description: $description,
        );
    }

    private function advanced(
        string $id,
        string $owner,
        string $tag,
        string $description,
        ?string $provider = null,
    ): PublicationDefinitionData {
        return new PublicationDefinitionData(
            id: $id,
            owner: $owner,
            scope: PublicationScope::Advanced,
            invocation: PublicationInvocation::Tag,
            target: $tag,
            overwritePolicy: PublicationOverwritePolicy::ExplicitForceOnly,
            description: $description,
            required: false,
            available: $provider === null || class_exists($provider),
        );
    }
}
