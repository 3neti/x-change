<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Fakes;

use Bavix\Wallet\Interfaces\Confirmable;
use Bavix\Wallet\Interfaces\Customer;
use Bavix\Wallet\Traits\CanConfirm;
use Bavix\Wallet\Traits\CanPay;
use Bavix\Wallet\Traits\HasWalletFloat;
use FrittenKeeZ\Vouchers\Concerns\HasRedeemers;
use FrittenKeeZ\Vouchers\Concerns\HasVouchers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;

class User extends Authenticatable implements Confirmable, Customer, HasMobileChannel
{
    use CanConfirm;
    use CanPay;
    use HasFactory;
    use HasRedeemers;
    use HasVouchers;
    use HasWalletFloat;
    use Notifiable;

    protected $table = 'users';

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

    public function getMobileChannel(): ?string
    {
        return $this->mobile;
    }

    public function setMobileChannel(string|null $mobile): static
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function hasMobileChannel(): bool
    {
        // TODO: Implement hasMobileChannel() method.
    }
}
