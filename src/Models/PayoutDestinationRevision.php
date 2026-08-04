<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LogicException;

final class PayoutDestinationRevision extends Model
{
    protected $table = 'x_change_payout_destination_revisions';

    protected $fillable = [
        'reference',
        'voucher_id',
        'voucher_claim_id',
        'rejected_reconciliation_id',
        'version',
        'bank_code',
        'account_number_ciphertext',
        'account_number_hash',
        'account_number_masked',
        'mobile_ciphertext',
        'mobile_hash',
        'validation_status',
        'validation_metadata',
        'requested_by_type',
        'requested_by_id',
        'recorded_at',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $revision): void {
            $revision->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new LogicException('Payout destination revisions are immutable.');
        });

        self::deleting(function (): never {
            throw new LogicException('Payout destination revisions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'account_number_ciphertext' => 'encrypted',
            'mobile_ciphertext' => 'encrypted',
            'validation_metadata' => 'array',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(VoucherClaim::class, 'voucher_claim_id');
    }

    public function rejectedReconciliation(): BelongsTo
    {
        return $this->belongsTo(
            DisbursementReconciliation::class,
            'rejected_reconciliation_id',
        );
    }
}
