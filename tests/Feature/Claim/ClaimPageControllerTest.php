<?php

declare(strict_types=1);

it('renders the canonical human claim page without exposing the experience JSON', function () {
    $voucher = issueVoucher(validVoucherInstructions(100));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => strtolower((string) $voucher->code)]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Entry')
        ->assertJsonPath('props.initial_code', (string) $voucher->code)
        ->assertJsonPath('props.provisioning_requirement', null)
        ->assertJsonStructure(['props' => ['claim_experience']]);
});

it('renders the public claim error page for a missing code', function () {
    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => 'missing']))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Error')
        ->assertJsonPath('props.message', 'Invalid Pay Code.')
        ->assertJsonPath('props.code', 'MISSING');
});

it('does not admit a collectible Pay Code into the outward claim experience', function () {
    $voucher = issueVoucher(validVoucherInstructions(100, 'INSTAPAY', [
        'voucher_type' => 'payable',
        'target_amount' => 100,
        'metadata' => [
            'flow_type' => 'collectible',
        ],
    ]));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Error')
        ->assertJsonPath('props.message', 'This Pay Code accepts payment and cannot be claimed.')
        ->assertJsonPath('props.code', (string) $voucher->code);
});
