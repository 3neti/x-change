<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\ClaimEvidenceKind;
use LBHurtado\XChange\Enums\ClaimEvidenceStatus;

final class VoucherClaimEvidence extends Model
{
    protected $table = 'x_change_voucher_claim_evidence';

    protected $fillable = [
        'voucher_claim_id',
        'voucher_id',
        'requirement_key',
        'kind',
        'status',
        'summary',
        'payload',
        'artifact_disk',
        'artifact_path',
        'mime_type',
        'size',
        'sha256',
        'captured_at',
        'verified_at',
        'metadata',
    ];

    protected $hidden = [
        'payload',
        'artifact_disk',
        'artifact_path',
        'sha256',
        'metadata',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(VoucherClaim::class, 'voucher_claim_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => ClaimEvidenceKind::class,
            'status' => ClaimEvidenceStatus::class,
            'payload' => 'encrypted:array',
            'metadata' => 'encrypted:array',
            'size' => 'integer',
            'captured_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
        ];
    }
}
