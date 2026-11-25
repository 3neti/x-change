<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\Banking\NetbankAccountAssignmentPreflight;

it('accepts one matching assignment and legacy link for the same owner', function () {
    $owner = actingAsTestUser();
    app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991-100-00123-4', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    expect(app(NetbankAccountAssignmentPreflight::class)->inspect())
        ->toMatchArray(['ready' => true, 'assignments' => 1, 'links' => 1, 'duplicate_accounts' => 0]);
});

it('blocks duplicate legacy assignments across different users without exposing account numbers', function () {
    $first = actingAsTestUser();
    $second = actingAsTestUser();
    foreach ([$first, $second] as $owner) {
        ProviderAccountLink::query()->create([
            'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
            'provider' => 'netbank', 'topology' => 'dedicated',
            'routing_profile_ciphertext' => ['bank_account_number' => '991-100-00123-4'],
        ]);
    }

    $result = app(NetbankAccountAssignmentPreflight::class)->inspect();
    expect($result)->toMatchArray(['ready' => false, 'duplicate_accounts' => 1])
        ->and(json_encode($result))->not->toContain('991100001234');
});

it('blocks unreadable routing details rather than declaring a clean inventory', function () {
    $owner = actingAsTestUser();
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
        'provider' => 'netbank', 'topology' => 'dedicated',
    ]);
    DB::table('xchange_provider_account_links')->where('id', $link->id)->update(['routing_profile_ciphertext' => 'invalid']);

    expect(app(NetbankAccountAssignmentPreflight::class)->inspect())
        ->toMatchArray(['ready' => false, 'unreadable_records' => 1]);
});

it('reports a legacy alias assigned to different accounts without disclosing them', function () {
    $owner = actingAsTestUser();
    foreach (['991100001234', '991100009999'] as $number) {
        ProviderAccountLink::query()->create([
            'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
            'provider' => 'netbank', 'topology' => 'dedicated',
            'routing_profile_ciphertext' => ['bank_account_number' => $number, 'vca_alias' => '54321'],
        ]);
    }

    $result = app(NetbankAccountAssignmentPreflight::class)->inspect();

    expect($result)->toMatchArray(['ready' => false, 'duplicate_aliases' => 1])
        ->and(json_encode($result))->not->toContain('54321')->not->toContain('991100001234');
});

it('detects a legacy personal account using the platform alias', function () {
    $owner = actingAsTestUser();
    config()->set('payment-gateway.netbank.funding.vca_alias', '54321');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '113001000019');
    ProviderAccountLink::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(),
        'provider' => 'netbank', 'topology' => 'dedicated',
        'routing_profile_ciphertext' => ['bank_account_number' => '991100001234', 'vca_alias' => '54321'],
    ]);

    expect(app(NetbankAccountAssignmentPreflight::class)->inspect())
        ->toMatchArray(['ready' => false, 'duplicate_aliases' => 1]);
});
