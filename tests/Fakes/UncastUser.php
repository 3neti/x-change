<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Fakes;

use Bavix\Wallet\Interfaces\Customer;
use Bavix\Wallet\Traits\CanPay;
use Bavix\Wallet\Traits\HasWalletFloat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use LBHurtado\Wallet\Traits\HasPlatformWallets;

final class UncastUser extends Authenticatable implements Customer
{
    use CanPay;
    use HasFactory;
    use HasPlatformWallets;
    use HasWalletFloat;

    protected $table = 'users';
}
