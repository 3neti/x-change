<?php

declare(strict_types=1);

it('hydrates the explicit Account invitation preset', function () {
    $user = actingAsTestUser();

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate', [
            'intent' => 'invite',
        ]))
        ->assertOk()
        ->assertJsonPath('props.invitation_preset.enabled', true)
        ->assertJsonPath('props.invitation_preset.source', 'cockpit');
});

it('does not infer Account onboarding from an unknown query value', function () {
    $user = actingAsTestUser();

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate', [
            'intent' => 'something-else',
        ]))
        ->assertOk()
        ->assertJsonPath('props.invitation_preset.enabled', false);
});

it('keeps the invitation entry tied to Quick Generate and its execution driver', function () {
    $dashboard = file_get_contents(
        dirname(__DIR__, 3).'/resources/js/cockpit/pages/Dashboard.vue',
    );
    $quickGenerate = file_get_contents(
        dirname(__DIR__, 3).'/resources/js/cockpit/pages/QuickGenerate.vue',
    );

    expect($dashboard)
        ->toContain("label: 'Invite'")
        ->toContain("quickGenerate({ query: { intent: 'invite' } }).url")
        ->and($quickGenerate)
        ->toContain(':onboarding-preset=')
        ->not->toContain('/x/cockpit/invitations');
});
