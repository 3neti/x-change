<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class CommercialAllocation extends Model
{
    protected $table = 'x_change_commercial_allocations';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new \LogicException('Commercial Allocations must be changed through guarded commercial actions.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Commercial Allocations cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'amount_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(CommercialSale::class, 'commercial_sale_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(CommercialPartner::class, 'commercial_partner_id');
    }

    public function partnerRevision(): BelongsTo
    {
        return $this->belongsTo(CommercialPartnerRevision::class, 'commercial_partner_revision_id');
    }

    public function disposition(): HasOne
    {
        return $this->hasOne(CommercialAllocationDisposition::class, 'commercial_allocation_id');
    }
}
