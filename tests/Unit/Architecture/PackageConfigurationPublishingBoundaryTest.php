<?php

use LBHurtado\XChange\Console\Commands\InstallXChangeCommand;
use LBHurtado\XChange\Providers\XChangeServiceProvider;

it('boots from package configuration without publishing advanced overrides', function () {
    $installer = file_get_contents((new ReflectionClass(InstallXChangeCommand::class))->getFileName());
    $provider = file_get_contents((new ReflectionClass(XChangeServiceProvider::class))->getFileName());

    expect($installer)
        ->not->toContain("'x-change-config'")
        ->not->toContain("'onboarding-config'")
        ->not->toContain("'x-feedback-config'")
        ->and($provider)
        ->toContain("\$this->packagePath('config/x-change.php')")
        ->toContain("'x-change'")
        ->toContain("], 'x-change-config'");
});

it('keeps package-owned onboarding and feedback configuration publishable on demand', function () {
    $onboardingProvider = file_get_contents(
        dirname(__DIR__, 3).'/vendor/3neti/onboarding/src/OnboardingServiceProvider.php',
    );
    $feedbackProvider = file_get_contents(
        dirname(__DIR__, 3).'/vendor/3neti/x-feedback/src/XFeedbackServiceProvider.php',
    );

    expect($onboardingProvider)
        ->toContain("mergeConfigFrom(__DIR__.'/../config/onboarding.php', 'onboarding')")
        ->toContain("], 'onboarding-config'")
        ->and($feedbackProvider)
        ->toContain("mergeConfigFrom(dirname(__DIR__).'/config/x-feedback.php', 'x-feedback')")
        ->toContain("], 'x-feedback-config'");
});
