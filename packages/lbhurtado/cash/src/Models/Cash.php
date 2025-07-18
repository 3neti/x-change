<?php

namespace LBHurtado\Cash\Models;

use Bavix\Wallet\Interfaces\{Confirmable, Customer, ProductInterface, Wallet};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Bavix\Wallet\Traits\{CanConfirm, HasWalletFloat};
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use LBHurtado\Cash\Database\Factories\CashFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Cash\Enums\CashStatus;
use Illuminate\Support\Facades\Hash;
use Spatie\ModelStatus\HasStatuses;
use Bavix\Wallet\Traits\HasWallet;
use Illuminate\Support\Number;
use Spatie\Tags\HasTags;
use Brick\Money\Money;

/**
 * Class Cash.
 *
 * @property int         $id
 * @property Money       $amount
 * @property string      $currency
 * @property Model       $reference
 * @property ArrayObject $meta
 * @property string      $secret
 * @property \DateTime   $expires_on
 * @property bool        $expired
 * @property string      $status
 * @property Collection  $tags
 *
 * @method int getKey()
 */
class Cash extends Model implements ProductInterface
{
    use HasStatuses {
        HasStatuses::setStatus as traitSetStatus; // Rename the HasStatuses method to traitSetStatus
    }
    use HasWalletFloat;
    use CanConfirm;
    use HasFactory;
    use HasWallet;
    use HasTags;

    protected $table = 'cash';

    protected $fillable = [
        'amount',
        'value',
        'currency',
        'meta',
        'secret',
        'expires_on',
    ];

    protected $casts = [
        'meta' => AsArrayObject::class,
        'expires_on' => 'datetime',
    ];

    public static function newFactory(): CashFactory
    {
        return CashFactory::new();
    }
    public static function booted(): void
    {
        static::creating(function (Cash $cash) {
            $cash->currency = $cash->currency ?: Number::defaultCurrency();
        });
//        static::created(function (Cash $cash) {
//            $float = $cash->amount->getAmount()->toFloat();
//            $cash->depositFloat($float);
//        });
//        // **never** let this model be updated again:
//        static::updating(function (self $cash) {
//            return false; // cancels the update
//        });
    }

    public function withdrawTransaction()
    {
        return $this->morphOne(\Bavix\Wallet\Models\Transaction::class, 'payable')
            ->where('type', 'withdraw');
    }

    /** @deprecated  */
    public function reference(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $currency = $attributes['currency'] ?? Number::defaultCurrency();

                return Money::ofMinor($value, $currency);
            },
            set: function ($value, $attributes) {
                $currency = $attributes['currency'] ?? Number::defaultCurrency();

                return $value instanceof Money
                    ? $value->getMinorAmount()->toInt()  // Extract minor units if already Money
                    : Money::of($value, $currency)->getMinorAmount()->toInt(); // Convert before storing
            }
        );
    }

    /** @deprecated */
    public function getValueAttribute(): Money
    {
        logger()->warning('[Deprecated] Accessed $cash->value getter. Use $cash->amount instead.', [
            'id' => $this->getKey(),
        ]);

        return $this->amount;
    }

    /** @deprecated */
    public function setValueAttribute(Money|float $value): void
    {
        logger()->warning('[Deprecated] Accessed $cash->value setter. Use $cash->amount instead.', [
            'id' => $this->getKey(),
        ]);

        $currency = $this->currency ?? Number::defaultCurrency();
        $this->amount = $value instanceof Money
            ? $value
            : Money::of($value, $currency);
    }

    /**
     * Set the status of the Cash model.
     *
     * @param CashStatus $status The status to be set.
     * @param string|null $reason Optional reason for the status change.
     * @return $this
     */
    public function setStatus(CashStatus $status, ?string $reason = null): self
    {
        // Explicitly call the renamed method from the HasStatuses trait
        $this->traitSetStatus($status->value, $reason);

        return $this;
    }


    /**
     * Check if the Cash model has a specific status.
     *
     * @param CashStatus $status The status to check.
     * @return bool
     */
    public function hasStatus(CashStatus $status): bool
    {
        return $this->status === $status->value;
    }

    /**
     * Check if the Cash model had a specific status in the past.
     *
     * @param CashStatus $status The status to check.
     * @return bool
     */
    public function hasHadStatus(CashStatus $status): bool
    {
        return $this->statuses()->where('name', $status->value)->exists();
    }

    /**
     * Get the current status of the model.
     *
     * @return CashStatus|null
     */
    public function getCurrentStatus(): ?CashStatus
    {
        return $this->status
            ? CashStatus::tryFrom($this->status)
            : null;
    }

    /**
     * Alias for retrieving the latest status instance.
     *
     * @return \Spatie\ModelStatus\Status
     */
    public function getStatusInstance(): \Spatie\ModelStatus\Status
    {
        return $this->latestStatus();
    }

    public function setExpiredAttribute(bool $value): self
    {
        $this->setAttribute('expires_on', $value ? now() : null);
        $this->traitSetStatus(  CashStatus::EXPIRED->value, 'Manually Expired');

        return $this;
    }

    public function getExpiredAttribute(): bool
    {
        return $this->getAttribute('expires_on')
            && $this->getAttribute('expires_on') <= now();
    }

    // Mutator to hash the secret before saving it into the database
    public function setSecretAttribute($value): void
    {
        $this->attributes['secret'] = Hash::make($value);
    }

    /**
     * Verify if the provided secret matches the hashed secret.
     *
     * @param  string  $providedSecret
     * @return bool
     */
    public function verifySecret(string $providedSecret): bool
    {
        return Hash::check($providedSecret, $this->secret);
    }

    /**
     * Determine if the cash can be redeemed.
     *
     * @param  string  $providedSecret
     * @return bool
     */
    public function canRedeem(string $providedSecret): bool
    {
        // Check if it is not expired and the provided secret matches
        return (!$this->expires_on || !$this->expires_on->isPast()) // Not expired
            && $this->verifySecret($providedSecret); // Secret is valid
    }

    public function getAmountProduct(Customer $customer): int|string
    {
        return $this->amount->getMinorAmount()->toInt();
    }

    public function getMetaProduct(): ?array
    {
        return [
            'title' => $this->title,
            'description' => 'Purchase of Product #' . $this->id,
        ];
    }
}
