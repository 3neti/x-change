<?php

namespace LBHurtado\Voucher\Models;

use FrittenKeeZ\Vouchers\Models\Redeemer;
use FrittenKeeZ\Vouchers\Models\Voucher as BaseVoucher;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Observers\VoucherObserver;
use LBHurtado\Voucher\Scopes\RedeemedScope;
use LBHurtado\Voucher\Data\VoucherData;
use Spatie\LaravelData\WithData;
use Illuminate\Support\Carbon;
use LBHurtado\ModelInput\Contracts\InputInterface;
use LBHurtado\ModelInput\Traits\HasInputs;

/**
 * Class Voucher.
 *
 * @property int                                        $id
 * @property string                                     $code
 * @property \Illuminate\Database\Eloquent\Model        $owner
 * @property array                                      $metadata
 * @property Carbon                                     $starts_at
 * @property Carbon                                     $expires_at
 * @property Carbon                                     $redeemed_at
 * @property Carbon                                     $processed_on
 * @property bool                                       $processed
 * @property VoucherInstructionsData                    $instructions
 * @property \FrittenKeeZ\Vouchers\Models\Redeemer      $redeemer
 * @property \Illuminate\Database\Eloquent\Collection   $voucherEntities
 * @property \Illuminate\Database\Eloquent\Collection   $redeemers
 * @property Cash                                       $cash
 * @property Contact                                    $contact
 *
 * @method int getKey()
 */
#[ObservedBy([VoucherObserver::class])]
class Voucher extends BaseVoucher implements InputInterface
{
    use WithData;
    use HasInputs;

    protected string $dataClass = VoucherData::class;

    public ?Redeemer $redeemer = null;

    protected function casts(): array
    {
        // Include parent's casts and add/override
        return array_merge(parent::casts(), [
            'processed_on' => 'datetime:Y-m-d H:i:s',
        ]);
    }

    public function getRouteKeyName() {
        return "code";
    }

    /**
     * Override the default to trim your incoming code.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $column = $field ?? $this->getRouteKeyName();

        return $this
            ->where($column, strtoupper(trim($value)))
            ->firstOrFail();
    }

    public function setProcessedAttribute(bool $value): self
    {
        $this->setAttribute('processed_on', $value ? now() : null);

        return $this;
    }

    public function getProcessedAttribute(): bool
    {
        return $this->getAttribute('processed_on')
            && $this->getAttribute('processed_on') <= now();
    }

    public function getInstructionsAttribute(): VoucherInstructionsData
    {
        return VoucherInstructionsData::from($this->metadata['instructions']);
    }

    public function getCashAttribute(): ?Cash
    {
        return $this->getEntities(Cash::class)->first();
    }

    public function getRedeemerAttribute(): ?Redeemer
    {
        return $this->redeemers->first();
    }

    public function getContactAttribute(): ?Contact
    {
        return $this->redeemers?->first()?->redeemer;
    }
}
