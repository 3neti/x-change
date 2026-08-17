<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WalletAccessContract;

it('creates a persisted platform compatibility wallet instead of using a transient default', function (): void {
    $treasuryLedger = (object) [
        'id' => 41,
        'exists' => true,
        'slug' => 'treasury-position-client-funds',
    ];
    $owner = new WalletAccessOwnerFixture([$treasuryLedger]);

    $resolved = app(WalletAccessContract::class)->resolveForUser($owner);

    expect($resolved->exists)->toBeTrue()
        ->and($resolved->id)->toBe(43)
        ->and($resolved->slug)->toBe('platform');
});

it('continues to prefer a persisted platform wallet', function (): void {
    $treasuryLedger = (object) [
        'id' => 41,
        'exists' => true,
        'slug' => 'treasury-position-client-funds',
    ];
    $platform = (object) [
        'id' => 42,
        'exists' => true,
        'slug' => 'platform',
    ];
    $owner = new WalletAccessOwnerFixture([$treasuryLedger, $platform]);

    $resolved = app(WalletAccessContract::class)->resolveForUser($owner);

    expect($resolved->exists)->toBeTrue()
        ->and($resolved->id)->toBe(42)
        ->and($resolved->slug)->toBe('platform');
});

final class WalletAccessOwnerFixture
{
    private ?object $createdWallet = null;

    /**
     * @param  list<object>  $wallets
     */
    public function __construct(private array $wallets) {}

    public function getWallet(string $slug): ?object
    {
        return collect([...$this->wallets, $this->createdWallet])
            ->filter()
            ->firstWhere('slug', $slug);
    }

    public function __isset(string $name): bool
    {
        return $name === 'wallet';
    }

    public function __get(string $name): ?object
    {
        if ($name !== 'wallet') {
            return null;
        }

        return (object) [
            'id' => null,
            'exists' => false,
            'slug' => 'default',
        ];
    }

    public function wallets(): WalletAccessRelationFixture
    {
        return new WalletAccessRelationFixture($this->wallets);
    }

    /**
     * @param  array{name:string,slug:string}  $attributes
     */
    public function createWallet(array $attributes): object
    {
        $this->createdWallet = (object) [
            'id' => 43,
            'exists' => true,
            ...$attributes,
        ];

        return $this->createdWallet;
    }
}

final class WalletAccessRelationFixture
{
    private ?string $slug = null;

    /**
     * @param  list<object>  $wallets
     */
    public function __construct(private readonly array $wallets) {}

    public function where(string $column, string $value): self
    {
        expect($column)->toBe('slug');
        $this->slug = $value;

        return $this;
    }

    public function first(): ?object
    {
        if ($this->slug === null) {
            return $this->wallets[0] ?? null;
        }

        return collect($this->wallets)->firstWhere('slug', $this->slug);
    }
}
