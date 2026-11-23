<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Funding\UpdateFundingDestination;
use LBHurtado\XChange\Models\ProviderAccountLink;

function bankAssignmentDetails(string $number = '991-100-00123-4'): array
{
    return [
        'account_number' => $number,
        'account_name' => 'Sample Corporation',
        'customer_id' => '2720777',
        'vca_alias' => '54321',
        'nickname' => 'Payroll',
    ];
}

it('encrypts account details and retains the account owner', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, bankAssignmentDetails());
    $raw = DB::table('xchange_bank_account_assignments')->find($assignment->id);

    expect($assignment->owner->is($owner))->toBeTrue()
        ->and($assignment->providerAccountLink->status)->toBe('pending')
        ->and($assignment->providerAccountLink->capabilities)->toBe([])
        ->and($assignment->details_ciphertext['account_number'])->toBe('991100001234')
        ->and($assignment->details_ciphertext['provider_account_number'])->toBe('991-100-00123-4')
        ->and($raw->details_ciphertext)->not->toContain('991100001234')
        ->and($raw->details_ciphertext)->not->toContain('2720777');
});

it('rejects the same account assigned to another user regardless of punctuation', function () {
    $first = actingAsTestUser();
    $second = actingAsTestUser();
    $action = app(AssignNetbankAccount::class);
    $action->handle($first, bankAssignmentDetails());

    expect(fn () => $action->handle($second, bankAssignmentDetails('991100001234')))
        ->toThrow(ValidationException::class);
});

it('does not assign the shared platform account to a user', function () {
    $owner = actingAsTestUser();
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '113-001-00001-9');

    expect(fn () => app(AssignNetbankAccount::class)->handle($owner, bankAssignmentDetails('113001000019')))
        ->toThrow(ValidationException::class);
});

it('rejects an account already linked to another owner in legacy routing', function () {
    $first = actingAsTestUser();
    $second = actingAsTestUser();
    ProviderAccountLink::query()->create([
        'owner_type' => $first->getMorphClass(), 'owner_id' => $first->getKey(),
        'provider' => 'netbank', 'topology' => 'dedicated',
        'routing_profile_ciphertext' => ['bank_account_number' => '991100001234'],
    ]);

    expect(fn () => app(AssignNetbankAccount::class)->handle($second, bankAssignmentDetails()))
        ->toThrow(ValidationException::class);
});

it('blocks new assignments when legacy routing is unreadable', function () {
    $owner = actingAsTestUser();
    ProviderAccountLink::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
        'provider' => 'netbank', 'topology' => 'dedicated',
    ]);

    expect(fn () => app(AssignNetbankAccount::class)->handle($owner, bankAssignmentDetails()))
        ->toThrow(ValidationException::class, 'need reconciliation');
});

it('rejects a NetBank alias already assigned to another account', function () {
    $first = actingAsTestUser();
    $second = actingAsTestUser();
    $action = app(AssignNetbankAccount::class);
    $action->handle($first, bankAssignmentDetails());

    expect(fn () => $action->handle($second, bankAssignmentDetails('991100009999')))
        ->toThrow(ValidationException::class, 'alias is already assigned');
});

it('rejects the shared platform alias for a personal account', function () {
    $owner = actingAsTestUser();
    config()->set('payment-gateway.netbank.funding.vca_alias', '54321');

    expect(fn () => app(AssignNetbankAccount::class)->handle($owner, bankAssignmentDetails()))
        ->toThrow(ValidationException::class, 'alias is already assigned');
});

it('allows an account to adopt its own matching legacy alias', function () {
    $owner = actingAsTestUser();
    ProviderAccountLink::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
        'provider' => 'netbank', 'topology' => 'dedicated',
        'routing_profile_ciphertext' => ['bank_account_number' => '991100001234', 'vca_alias' => '54321'],
    ]);

    $assignment = app(AssignNetbankAccount::class)->handle($owner, bankAssignmentDetails());

    expect($assignment->providerAccountLink->routing_profile_ciphertext['vca_alias'])->toBe('54321');
});

it('rejects a legacy alias belonging to a different account', function () {
    $owner = actingAsTestUser();
    ProviderAccountLink::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
        'provider' => 'netbank', 'topology' => 'dedicated',
        'routing_profile_ciphertext' => ['bank_account_number' => '991100009999', 'vca_alias' => '54321'],
    ]);

    expect(fn () => app(AssignNetbankAccount::class)->handle($owner, bankAssignmentDetails()))
        ->toThrow(ValidationException::class, 'alias is already assigned');
    expect(DB::table('xchange_bank_account_assignments')->count())->toBe(0);
});

it('allows separate NetBank accounts with different aliases', function () {
    $owner = actingAsTestUser();
    $action = app(AssignNetbankAccount::class);
    $action->handle($owner, bankAssignmentDetails());
    $details = bankAssignmentDetails('991100009999');
    $details['vca_alias'] = '54322';

    $second = $action->handle($owner, $details);

    expect($second->details_ciphertext['vca_alias'])->toBe('54322');
});

it('rejects a duplicate alias through legacy funding settings', function () {
    $owner = actingAsTestUser();
    $other = actingAsTestUser();
    app(AssignNetbankAccount::class)->handle($owner, bankAssignmentDetails());

    expect(fn () => app(UpdateFundingDestination::class)->handle($other, 'netbank', [
        'mode' => 'dedicated', 'account_number' => '991100009999',
        'account_name' => 'Other company', 'vca_alias' => '54321',
    ]))->toThrow(ValidationException::class, 'alias is already assigned');
});

it('completes missing routing fields when adopting a matching legacy account', function () {
    $owner = actingAsTestUser();
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
        'provider' => 'netbank', 'topology' => 'dedicated',
        'routing_profile_ciphertext' => ['bank_account_number' => '991100001234'],
    ]);

    $assignment = app(AssignNetbankAccount::class)->handle($owner, bankAssignmentDetails());

    expect($assignment->provider_account_link_id)->toBe($link->id)
        ->and($link->fresh()->routing_profile_ciphertext)->toMatchArray([
            'bank_account_number' => '991100001234', 'bank_account_name' => 'Sample Corporation',
            'customer_id' => '2720777', 'vca_alias' => '54321',
        ]);
});

it('rejects a conflicting customer ID on a legacy account', function () {
    $owner = actingAsTestUser();
    ProviderAccountLink::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
        'provider' => 'netbank', 'topology' => 'dedicated',
        'routing_profile_ciphertext' => ['bank_account_number' => '991100001234', 'customer_id' => '9999'],
    ]);

    expect(fn () => app(AssignNetbankAccount::class)->handle($owner, bankAssignmentDetails()))
        ->toThrow(ValidationException::class, 'customer ID does not match');
});
