<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Models\StoredValueHolderBinding;

it('shows activated stored value in the issuer list and links only its holder to the balance', function (): void {
    $holder = actingAsTestUser();
    $voucher = issueVoucher();
    $voucher->forceFill(['redeemed_at' => now()])->save();
    $binding = storedValuePresentationBinding($voucher->getKey(), $holder->getMorphClass(), (string) $holder->getKey());

    $this->actingAs($holder)->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('props.pay_codes_read_model.records.0.stored_value_activated', true);

    $this->actingAs($holder)->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('props.stored_value.activated', true)
        ->assertJsonPath('props.stored_value.holder_url', route('x-change.balances.reusable.show', ['instrument' => $binding->reference], false));

    $otherVoucher = issueVoucher();
    storedValuePresentationBinding($otherVoucher->getKey(), Contact::class, '999');

    $this->actingAs($holder)->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $otherVoucher->code]))
        ->assertOk()
        ->assertJsonPath('props.stored_value.activated', true)
        ->assertJsonPath('props.stored_value.holder_url', null);
});

function storedValuePresentationBinding(int $voucherId, string $holderType, string $holderId): StoredValueHolderBinding
{
    return StoredValueHolderBinding::query()->create([
        'voucher_id' => $voucherId,
        'allocation_reference' => 'presentation-allocation:'.$voucherId,
        'reservation_operation_reference' => 'presentation-reservation:'.$voucherId,
        'activation_operation_reference' => 'presentation-activation:'.$voucherId,
        'holder_type' => $holderType,
        'holder_id' => $holderId,
        'holder_principal_reference' => 'presentation-principal:'.$holderId,
        'holder_authority_reference' => 'presentation-authority:'.$holderId,
        'currency' => 'PHP',
        'activated_at' => now(),
    ]);
}
