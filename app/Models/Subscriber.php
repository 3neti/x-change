<?php

namespace App\Models;

use Parental\HasParent;
use LBHurtado\PaymentGateway\Contracts\MerchantInterface;
use LBHurtado\ModelChannel\Contracts\ChannelsInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LBHurtado\PaymentGateway\Traits\HasMerchant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use LBHurtado\Wallet\Traits\HasPlatformWallets;
use LBHurtado\ModelChannel\Traits\HasInputs;
use App\Notifications\BalanceNotification;
use Illuminate\Notifications\Notifiable;
use Bavix\Wallet\Interfaces\Confirmable;
use Bavix\Wallet\Traits\HasWalletFloat;
use LBHurtado\Wallet\Enums\WalletType;
use Bavix\Wallet\Interfaces\Customer;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\CanConfirm;
use Laravel\Sanctum\HasApiTokens;
use App\Observers\UserObserver;
use Bavix\Wallet\Traits\CanPay;
use App\Actions\CheckBalance;

class Subscriber extends User
{
    use HasParent;
//    use Notifiable;

//    use HasFactory, Notifiable;
//    use HasPlatformWallets;
//    use HasWalletFloat;
//    use HasApiTokens;
//    use HasChannels;
//    use HasMerchant;
//    use CanConfirm;
//    use CanPay;

//    public function routeNotificationForEngageSpark(): string
//    {
//        return $this->mobile;
//    }
}
