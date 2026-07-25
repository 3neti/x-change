<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;
use LogicException;

final class VoucherClaimOutcomeSelection extends Model
{
    protected $table = 'x_change_voucher_claim_outcome_selections';

    protected $fillable = [
        'voucher_id',
        'outcome_key',
        'policy_profile',
        'selection_mode',
        'claimant_type',
        'claimant_id',
        'claimant_reference',
        'selected_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Voucher claim outcome selections are immutable.');
        });

        self::deleting(function (): never {
            throw new LogicException('Voucher claim outcome selections cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'selected_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
