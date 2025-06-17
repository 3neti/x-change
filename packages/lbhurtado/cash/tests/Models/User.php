<?php

declare(strict_types=1);

namespace LBHurtado\Cash\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LBHurtado\Cash\Database\Factories\UserFactory;
use Bavix\Wallet\Interfaces\{Customer, Wallet};
use Illuminate\Notifications\Notifiable;
use Bavix\Wallet\Traits\HasWalletFloat;
use Bavix\Wallet\Traits\CanPay;

/**
 * Class User.
 *
 * @property int        $id
 * @property string     $name
 * @property string     $email
 *
 * @method int getKey()
 */
class User extends Authenticatable implements Wallet, Customer
{
    use HasWalletFloat;
    use HasFactory;
    use Notifiable;
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

    public static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
