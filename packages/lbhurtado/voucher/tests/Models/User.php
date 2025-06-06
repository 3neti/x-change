<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Tests\Models;

use FrittenKeeZ\Vouchers\Concerns\HasRedeemers;
use FrittenKeeZ\Vouchers\Concerns\HasVouchers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use HasRedeemers;
    use HasVouchers;
    use Notifiable;

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
