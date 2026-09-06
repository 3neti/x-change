<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Tests\Fakes\User;

/**
 * @return array<string, mixed>
 */
function collectionWalletAuthorityPayload(
    ?string $voucherType,
    ?string $flowType,
    ?string $callerWalletId,
): array {
    $metadata = [];

    if ($flowType !== null) {
        $metadata['flow_type'] = $flowType;
    }

    if ($callerWalletId !== null) {
        $metadata['collection_wallet_id'] = $callerWalletId;
    }

    return [
        'cash' => [
            'amount' => 0,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'AUTH',
        'mask' => '****',
        'voucher_type' => $voucherType,
        'target_amount' => 100,
        'metadata' => $metadata,
    ];
}

function collectionWalletAuthorityUser(string $name): User
{
    $user = User::query()->create([
        'name' => $name,
        'email' => Str::uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
    fundTestUserWallet($user);

    return $user;
}

function frozenCollectionWalletId(array $result): string
{
    $voucher = Voucher::query()->findOrFail($result['voucher_id']);

    return (string) data_get(
        $voucher->metadata,
        'instructions.metadata.collection_wallet_id',
    );
}

it('overwrites a foreign caller wallet for payable and settlement issuance', function (string $voucherType): void {
    $issuer = actingAsTestUser();
    $issuerWallet = $issuer->wallet()->where('slug', 'platform')->sole();
    $foreign = collectionWalletAuthorityUser('Foreign wallet owner');
    $foreignWallet = $foreign->wallet()->where('slug', 'platform')->sole();

    $result = app(PayCodeIssuanceContract::class)->issue(
        $issuer,
        collectionWalletAuthorityPayload(
            voucherType: $voucherType,
            flowType: 'collectible',
            callerWalletId: (string) $foreignWallet->getKey(),
        ),
    );

    expect(frozenCollectionWalletId($result))
        ->toBe((string) $issuerWallet->getKey())
        ->not->toBe((string) $foreignWallet->getKey());
})->with(['payable', 'settlement']);

it('overrides a foreign collection wallet for payable issuance without flow type', function (): void {
    $issuer = actingAsTestUser();
    $issuerWallet = $issuer->wallet()->where('slug', 'platform')->sole();
    $foreign = collectionWalletAuthorityUser('Foreign wallet owner');
    $foreignWallet = $foreign->wallet()->where('slug', 'platform')->sole();

    $result = app(PayCodeIssuanceContract::class)->issue(
        $issuer,
        collectionWalletAuthorityPayload(
            voucherType: 'payable',
            flowType: null,
            callerWalletId: (string) $foreignWallet->getKey(),
        ),
    );
    $voucher = Voucher::query()->findOrFail($result['voucher_id']);

    expect(frozenCollectionWalletId($result))
        ->toBe((string) $issuerWallet->getKey())
        ->and(data_get($voucher->metadata, 'instructions.metadata.issuer_id'))
        ->toBe((string) $issuer->getAuthIdentifier());
});

it('applies issuer wallet authority to an explicit collectible flow', function (): void {
    $issuer = actingAsTestUser();
    $issuerWallet = $issuer->wallet()->where('slug', 'platform')->sole();
    $foreign = collectionWalletAuthorityUser('Foreign wallet owner');
    $foreignWallet = $foreign->wallet()->where('slug', 'platform')->sole();

    $result = app(PayCodeIssuanceContract::class)->issue(
        $issuer,
        collectionWalletAuthorityPayload(
            voucherType: null,
            flowType: 'collectible',
            callerWalletId: (string) $foreignWallet->getKey(),
        ),
    );

    expect(frozenCollectionWalletId($result))
        ->toBe((string) $issuerWallet->getKey());
});

it('derives the collection wallet when the caller honestly omits it', function (): void {
    $issuer = actingAsTestUser();
    $issuerWallet = $issuer->wallet()->where('slug', 'platform')->sole();

    $result = app(PayCodeIssuanceContract::class)->issue(
        $issuer,
        collectionWalletAuthorityPayload(
            voucherType: 'payable',
            flowType: 'collectible',
            callerWalletId: null,
        ),
    );

    expect(frozenCollectionWalletId($result))
        ->toBe((string) $issuerWallet->getKey());
});
