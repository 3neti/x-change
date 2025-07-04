<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Tests\Models;

use FrittenKeeZ\Vouchers\Concerns\HasRedeemers;
use FrittenKeeZ\Vouchers\Concerns\HasVouchers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Interfaces\Confirmable;
use Bavix\Wallet\Traits\{CanConfirm, CanPay};
use Bavix\Wallet\Traits\HasWalletFloat;
use LBHurtado\ModelChannel\Traits\HasChannels;
use Bavix\Wallet\Interfaces\Customer;


class User extends Authenticatable implements Wallet, Confirmable, Customer
{
    use HasFactory;
    use HasRedeemers;
    use HasVouchers;
    use Notifiable;
    use HasWalletFloat;
    use CanConfirm;
    use HasChannels;
    use CanPay;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
}
