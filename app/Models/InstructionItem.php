<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Bavix\Wallet\Interfaces\ProductInterface;
use Illuminate\Database\Eloquent\Model;
use Bavix\Wallet\Interfaces\Customer;
use Bavix\Wallet\Traits\HasWallet;
use Illuminate\Support\Str;

class InstructionItem extends Model implements ProductInterface
{
    /** @use HasFactory<\Database\Factories\InstructionItemFactory> */
    use HasFactory;
    use HasWallet;

    protected $fillable = [
        'name',
        'index',
        'type',
        'price',
        'currency',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function getAmountProduct(Customer $customer): int|string
    {
        return $this->price;
    }

    public function getMetaProduct(): ?array
    {
        return [
            'type' => $this->type,
            'title' => $this->meta['title'] ?? ucfirst($this->type),
            'description' => $this->meta['description'] ?? "Charge for {$this->type} instruction",
        ];
    }

    public function getUniqueId(): string
    {
        return "{$this->type}:{$this->id}";
    }

    public static function attributesFromIndex(string $index, array $overrides = []): array
    {
        return array_merge([
            'index'    => $index,
            'name'     => Str::of($index)->afterLast('.')->headline(),
            'type'     => Str::of($index)->explode('.')[1] ?? 'general',
            'price'    => 0,
            'currency' => 'PHP',
            'meta'     => [],
        ], $overrides);
    }
}
