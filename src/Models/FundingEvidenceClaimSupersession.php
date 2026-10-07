<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class FundingEvidenceClaimSupersession extends Model
{
    protected $table = 'x_change_funding_evidence_claim_supersessions';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Funding Evidence Claim Supersessions are immutable.');
        });

        self::deleting(function (): never {
            throw new LogicException('Funding Evidence Claim Supersessions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['superseded_at' => 'immutable_datetime'];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(FundingEvidenceClaim::class, 'funding_evidence_claim_id');
    }
}
