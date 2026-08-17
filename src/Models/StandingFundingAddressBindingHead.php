<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StandingFundingAddressBindingHead extends Model
{
    protected $table = 'x_change_standing_funding_address_binding_heads';

    protected $primaryKey = 'standing_funding_address_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['standing_funding_address_id', 'current_binding_revision_id'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new \LogicException('Standing Funding Address binding heads must be changed through guarded actions.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Standing Funding Address binding heads cannot be deleted.');
        });
    }

    public function standingFundingAddress(): BelongsTo
    {
        return $this->belongsTo(StandingFundingAddress::class);
    }

    public function currentBindingRevision(): BelongsTo
    {
        return $this->belongsTo(
            StandingFundingAddressBindingRevision::class,
            'current_binding_revision_id',
        );
    }
}
