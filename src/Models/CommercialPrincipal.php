<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Bavix\Wallet\Interfaces\Confirmable;
use Bavix\Wallet\Interfaces\Customer;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Interfaces\WalletFloat;
use Bavix\Wallet\Traits\CanConfirm;
use Bavix\Wallet\Traits\CanPay;
use Bavix\Wallet\Traits\HasWalletFloat;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Traits\HasPlatformWallets;

final class CommercialPrincipal extends Model implements Authenticatable, Confirmable, Customer, Wallet, WalletFloat
{
    use AuthenticatableTrait;
    use CanConfirm;
    use CanPay;
    use HasPlatformWallets;
    use HasWalletFloat;

    protected $table = 'x_change_commercial_principals';

    protected $fillable = [
        'reference',
        'legal_name',
        'authorization_reference',
        'active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
