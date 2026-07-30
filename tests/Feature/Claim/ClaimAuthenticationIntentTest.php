<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;
use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use LBHurtado\XChange\Support\Claim\ClaimAuthenticationIntent;

function claimAuthenticationRequest(): Request
{
    $request = Request::create('/x/claim/ONBD1');
    $request->setLaravelSession(app('session')->driver());

    return $request;
}

it('remembers campaign officer authentication without changing the canonical claim link', function () {
    $request = claimAuthenticationRequest();
    $workflow = new ClaimWorkflowDescriptorData(
        key: 'campaign.officer-authorization.v1',
        requires_mobile: true,
        requires_destination: false,
        requires_amount: false,
        title: 'Campaign Officer Authorization',
        description: 'Authorize the campaign.',
        confirmation_label: 'Authorize Campaign',
        authentication_mode: ClaimAuthenticationMode::AuthenticatedOfficer,
    );

    $payload = app(ClaimAuthenticationIntent::class)->remember($request, 'appr-a1b2', $workflow);

    expect($payload)
        ->type->toBe('campaign_authorization')
        ->authentication_mode->toBe('authenticated_officer')
        ->code->toBe('APPR-A1B2')
        ->intended_url->toBe(route('x-change.claim.show', ['code' => 'APPR-A1B2']))
        ->handoff_url->toBe(route('x-change.claim.authorization-required', ['code' => 'APPR-A1B2']))
        ->and($request->session()->get('url.intended'))
        ->toBe(route('x-change.claim.show', ['code' => 'APPR-A1B2']));
});

it('remembers an onboarding claimant handoff as a distinct authentication intent', function () {
    $request = claimAuthenticationRequest();
    $workflow = new ClaimWorkflowDescriptorData(
        key: 'onboarding.account-provisioning.v1',
        requires_mobile: true,
        requires_destination: false,
        requires_amount: false,
        title: 'Set Up Your Account',
        description: 'Confirm your recipient details.',
        confirmation_label: 'Create My Account',
        authentication_mode: ClaimAuthenticationMode::ClaimantHandoff,
        required_claim_fields: ['full_name', 'email', 'mobile'],
    );

    $payload = app(ClaimAuthenticationIntent::class)->remember($request, 'onbd-1234', $workflow);

    expect($payload)
        ->type->toBe('onboarding_claimant_handoff')
        ->authentication_mode->toBe('claimant_handoff')
        ->workflow_key->toBe('onboarding.account-provisioning.v1')
        ->intended_url->toBe(route('x-change.claim.show', ['code' => 'ONBD-1234']))
        ->handoff_url->toBe(route('x-change.claim.show', ['code' => 'ONBD-1234']))
        ->and(app(ClaimAuthenticationIntent::class)->current($request))
        ->toMatchArray([
            'type' => 'onboarding_claimant_handoff',
            'authentication_mode' => 'claimant_handoff',
            'code' => 'ONBD-1234',
        ]);
});

it('does not create an authentication intent for an ordinary claim workflow', function () {
    $request = claimAuthenticationRequest();
    $workflow = new ClaimWorkflowDescriptorData(
        key: 'disbursement.v1',
        requires_mobile: true,
        requires_destination: true,
        requires_amount: true,
        title: 'Disbursement Details',
        description: 'Provide a destination.',
        confirmation_label: 'Confirm Redemption',
    );

    expect(fn () => app(ClaimAuthenticationIntent::class)->remember(
        $request,
        'CASH',
        $workflow,
    ))->toThrow(LogicException::class);
});
