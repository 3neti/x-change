<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Tests\Models;

use LBHurtado\Wallet\Services\WalletProvisioningService;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LBHurtado\Wallet\Database\Factories\UserFactory;
use LBHurtado\Wallet\Traits\HasPlatformWallets;
use Illuminate\Notifications\Notifiable;
use Bavix\Wallet\Interfaces\Confirmable;
use Bavix\Wallet\Traits\HasWalletFloat;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\CanConfirm;

/**
 * Class User.
 *
 * @property int        $id
 * @property string     $name
 * @property string     $email
 *
 * @method int getKey()
 */
class User extends Authenticatable implements Wallet, Confirmable
{
    use HasPlatformWallets;
    use HasWalletFloat;
    use HasFactory;
    use Notifiable;
    use CanConfirm;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile'
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

    public static function booted(): void
    {
        static::created(function (User $user) {
            $walletService = app(WalletProvisioningService::class);
            $walletService->createDefaultWalletsForUser($user);
        });
    }
}
