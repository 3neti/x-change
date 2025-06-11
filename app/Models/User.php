<?php

namespace App\Models;

use LBHurtado\PaymentGateway\Contracts\MerchantInterface;
use LBHurtado\ModelChannel\Contracts\ChannelsInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LBHurtado\PaymentGateway\Traits\HasMerchant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use LBHurtado\Wallet\Traits\HasPlatformWallets;
use LBHurtado\ModelChannel\Traits\HasChannels;
use App\Notifications\BalanceNotification;
use Illuminate\Notifications\Notifiable;
use Bavix\Wallet\Interfaces\Confirmable;
use Bavix\Wallet\Traits\HasWalletFloat;
use LBHurtado\Wallet\Enums\WalletType;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\CanConfirm;
use Laravel\Sanctum\HasApiTokens;
use App\Observers\UserObserver;
use App\Actions\CheckBalance;
use Parental\HasChildren;
use App\Enums\ChildType;

/**
 * Class Agent.
 *
 * @property int                         $id
 * @property string                      $name
 * @property string                      $email
 * @property string                      $type
 * @property \Bavix\Wallet\Models\Wallet $wallet

 * @method int getKey()
 */

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements Wallet, Confirmable, ChannelsInterface, MerchantInterface
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HasPlatformWallets;
    use HasWalletFloat;
    use HasApiTokens;
    use HasChannels;
    use HasMerchant;
    use HasChildren;
    use CanConfirm;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'workos_id',
        'type',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'workos_id',
        'remember_token',
    ];

    protected $childTypes = [
        ChildType::SYSTEM->value => System::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function balanceAccessLogs(): HasMany
    {
        return $this->hasMany(BalanceAccessLog::class);
    }

    public function routeNotificationForEngageSpark(): string
    {
        return $this->mobile;
    }

    public function sendBalanceNotification(?WalletType $walletType = null): void
    {
        $this->notify(new BalanceNotification(CheckBalance::run($this, $walletType), $walletType));
    }
}
