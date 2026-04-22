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
use LBHurtado\ModelChannel\Traits\HasChannels;
use LBHurtado\XChange\Contracts\HasLifecycleMetadata;

class User extends Authenticatable implements Confirmable, Customer, HasLifecycleMetadata, HasMobileChannel
{
    use CanConfirm;
    use CanPay;
    use HasChannels;
    use HasFactory;
    use HasRedeemers;
    use HasVouchers;
    use HasWalletFloat;
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'country',
        'metadata',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'metadata' => 'array',
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

    public function setMobileChannel(?string $mobile): static
    {
        $this->mobile = $mobile;

        return $this;
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function putLifecycleMetadata(string $key, array $attributes): void
    {
        $metadata = (array) ($this->metadata ?? []);
        $metadata[$key] = $attributes;

        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * @return array<string,mixed>
     */
    public function getLifecycleMetadata(string $key): array
    {
        $metadata = (array) ($this->metadata ?? []);
        $value = $metadata[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
