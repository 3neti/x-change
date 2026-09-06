<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;
use LBHurtado\XChange\Services\DefaultVoucherCollectionWalletResolver;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.onboarding.issuer_model', User::class);
});

function collectionWalletResolverUser(string $name): User
{
    $user = User::query()->create([
        'name' => $name,
        'email' => Str::uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
    fundTestUserWallet($user);

    return $user;
}

function collectionWalletResolverVoucher(?User $issuer, ?string $walletId): Voucher
{
    $voucher = issueVoucher();
    $metadata = (array) $voucher->metadata;

    if ($issuer instanceof User) {
        data_set(
            $metadata,
            'instructions.metadata.issuer_id',
            (string) $issuer->getKey(),
        );
    } else {
        data_forget($metadata, 'instructions.metadata.issuer_id');
        data_forget($metadata, 'issuer_id');
    }

    if ($walletId !== null) {
        data_set($metadata, 'instructions.metadata.collection_wallet_id', $walletId);
    } else {
        data_forget($metadata, 'instructions.metadata.collection_wallet_id');
        data_forget($metadata, 'collection_wallet_id');
    }

    $voucher->forceFill(['metadata' => $metadata])->save();

    return $voucher->refresh();
}

it('rejects a foreign explicit wallet when no issuer fallback can resolve', function (): void {
    $issuer = collectionWalletResolverUser('Issuer');
    $foreign = collectionWalletResolverUser('Foreign wallet owner');
    $foreignWallet = $foreign->wallet()->where('slug', 'platform')->sole();
    $voucher = collectionWalletResolverVoucher(
        $issuer,
        (string) $foreignWallet->getKey(),
    );
    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('resolveForUser')
        ->once()
        ->withArgs(fn (mixed $candidate): bool => $candidate->is($issuer))
        ->andThrow(new PayCodeWalletNotResolved('Issuer wallet unavailable.'));
    app()->instance(WalletAccessContract::class, $wallets);

    expect(fn () => app(DefaultVoucherCollectionWalletResolver::class)->resolve($voucher))
        ->toThrow(PayCodeWalletNotResolved::class);
});

it('accepts an explicit wallet owned by the resolved issuer', function (): void {
    $issuer = collectionWalletResolverUser('Issuer');
    $wallet = $issuer->wallet()->where('slug', 'platform')->sole();
    $voucher = collectionWalletResolverVoucher($issuer, (string) $wallet->getKey());

    $resolved = app(DefaultVoucherCollectionWalletResolver::class)->resolve($voucher);

    expect($resolved->is($wallet))->toBeTrue();
});

it('resolves a legacy issuer-only voucher through wallet access authority', function (): void {
    $issuer = collectionWalletResolverUser('Legacy issuer');
    $wallet = $issuer->wallet()->where('slug', 'platform')->sole();
    $voucher = collectionWalletResolverVoucher($issuer, null);
    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('resolveForUser')
        ->once()
        ->withArgs(fn (mixed $candidate): bool => $candidate->is($issuer))
        ->andReturn($wallet);
    app()->instance(WalletAccessContract::class, $wallets);

    $resolved = app(DefaultVoucherCollectionWalletResolver::class)->resolve($voucher);

    expect($resolved->is($wallet))->toBeTrue();
});

it('preserves explicit-wallet compatibility when legacy issuer evidence is absent', function (): void {
    $owner = collectionWalletResolverUser('Legacy wallet owner');
    $wallet = $owner->wallet()->where('slug', 'platform')->sole();
    $voucher = collectionWalletResolverVoucher(null, (string) $wallet->getKey());

    $resolved = app(DefaultVoucherCollectionWalletResolver::class)->resolve($voucher);

    expect($resolved->is($wallet))->toBeTrue();
});
