<?php

declare(strict_types=1);

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Banking\RotateBankAccountIdentityKey;
use LBHurtado\XChange\Models\BankAccountIdentity;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\Banking\BankAccountIdentityRegistry;

it('rotates recoverable account fingerprints while keeping ownership unique', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $oldKey = config('app.key');
    $oldFingerprint = $assignment->account_fingerprint;
    config()->set('app.previous_keys', [$oldKey]);
    $newKey = 'base64:'.base64_encode(random_bytes(32));
    config()->set('app.key', $newKey);
    $encrypter = new Encrypter(base64_decode(substr($newKey, 7)), 'aes-256-cbc');
    $encrypter->previousKeys([base64_decode(substr($oldKey, 7))]);
    app()->instance('encrypter', $encrypter);
    Crypt::clearResolvedInstance('encrypter');

    $result = app(RotateBankAccountIdentityKey::class)->handle($owner);

    expect($result['identities'])->toBe(1)
        ->and($assignment->fresh()->account_fingerprint)->not->toBe($oldFingerprint)
        ->and(app(BankAccountIdentityRegistry::class)->claim($owner, 'netbank', '991100001234')->owner_id)->toBe($owner->id);
    $other = actingAsTestUser();
    expect(fn () => app(BankAccountIdentityRegistry::class)->claim($other, 'netbank', '991100001234'))
        ->toThrow(ValidationException::class);
});

it('refuses to rotate when an identity has no recoverable account record', function () {
    $owner = actingAsTestUser();
    app(BankAccountIdentityRegistry::class)->claim($owner, 'netbank', '991100001234');
    $oldFingerprint = BankAccountIdentity::query()->sole()->account_fingerprint;
    $oldVerifier = DB::table('xchange_bank_identity_key_guard')->where('id', 1)->value('key_verifier');
    $oldKey = config('app.key');
    config()->set('app.previous_keys', [$oldKey]);
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    expect(fn () => app(RotateBankAccountIdentityKey::class)->handle($owner))->toThrow(ValidationException::class);
    expect(BankAccountIdentity::query()->sole()->account_fingerprint)->toBe($oldFingerprint)
        ->and(DB::table('xchange_bank_identity_key_guard')->where('id', 1)->value('key_verifier'))->toBe($oldVerifier);
});

it('refuses to rotate without the prior encryption key', function () {
    $owner = actingAsTestUser();
    app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    config()->set('app.previous_keys', []);
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    expect(fn () => app(RotateBankAccountIdentityKey::class)->handle($owner))->toThrow(ValidationException::class);
});

it('backfills an unregistered legacy account while rotating identities', function () {
    $owner = actingAsTestUser();
    app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $legacyOwner = actingAsTestUser();
    ProviderAccountLink::query()->create([
        'owner_type' => $legacyOwner->getMorphClass(), 'owner_id' => $legacyOwner->getKey(),
        'provider' => 'netbank', 'topology' => 'dedicated',
        'routing_profile_ciphertext' => ['bank_account_number' => '991100009999'],
    ]);
    config()->set('app.previous_keys', [config('app.key')]);
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    expect(app(RotateBankAccountIdentityKey::class)->handle($owner)['identities'])->toBe(2)
        ->and(BankAccountIdentity::query()->count())->toBe(2);
    expect(fn () => app(BankAccountIdentityRegistry::class)->claim($owner, 'netbank', '991100009999'))
        ->toThrow(ValidationException::class);
});

it('does not change identity records if encrypted account details cannot be read', function () {
    $owner = actingAsTestUser();
    app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $oldFingerprint = BankAccountIdentity::query()->sole()->account_fingerprint;
    $oldVerifier = DB::table('xchange_bank_identity_key_guard')->where('id', 1)->value('key_verifier');
    config()->set('app.previous_keys', [config('app.key')]);
    $newKey = random_bytes(32);
    config()->set('app.key', 'base64:'.base64_encode($newKey));
    app()->instance('encrypter', new Encrypter($newKey, 'aes-256-cbc'));
    Crypt::clearResolvedInstance('encrypter');

    expect(fn () => app(RotateBankAccountIdentityKey::class)->handle($owner))->toThrow(ValidationException::class);
    expect(BankAccountIdentity::query()->sole()->account_fingerprint)->toBe($oldFingerprint)
        ->and(DB::table('xchange_bank_identity_key_guard')->where('id', 1)->value('key_verifier'))->toBe($oldVerifier);
});
