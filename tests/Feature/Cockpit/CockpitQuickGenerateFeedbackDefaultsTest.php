<?php

declare(strict_types=1);

it('hydrates quick generate with operator-safe feedback defaults', function (): void {
    $user = actingAsTestUser();

    $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/QuickGenerate')
        ->assertJsonPath('props.feedback_defaults.schema', 'x-change.cockpit.quick-generate-feedback-defaults.v1')
        ->assertJsonPath('props.feedback_defaults.email', $user->email)
        ->assertJsonPath('props.feedback_defaults.mobile', null)
        ->assertJsonPath('props.feedback_defaults.source', 'authenticated-user')
        ->assertJsonPath('props.feedback_defaults.read_only', true)
        ->assertJsonMissingPath('props.feedback_defaults.raw_payload')
        ->assertJsonMissingPath('props.feedback_defaults.provider_payload')
        ->assertJsonMissingPath('props.feedback_defaults.wallet')
        ->assertJsonMissingPath('props.feedback_defaults.delivery_payload');

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'));

    expect($response->json('props.feedback_defaults.webhook'))
        ->toStartWith(url('/x/webhooks/operator/'));
});

it('hydrates the effective onboarding OTP policy without exposing configuration details', function (): void {
    config()->set('x-change.onboarding.voucher.require_otp', false);

    actingAsTestUser();

    $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('props.onboarding_policy.otp_required', false)
        ->assertJsonMissingPath('props.onboarding_policy.otp_secret')
        ->assertJsonMissingPath('props.onboarding_policy.provider');
});
