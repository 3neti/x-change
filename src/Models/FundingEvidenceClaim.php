<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LogicException;

final class FundingEvidenceClaim extends Model
{
    protected $table = 'x_change_funding_evidence_claims';

    protected $guarded = [];

    protected $hidden = ['provider_transaction_hash'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Funding Evidence Claims are immutable.');
        });

        self::deleting(function (): never {
            throw new LogicException('Funding Evidence Claims cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['claimed_at' => 'immutable_datetime'];
    }

    public function fundingIntent(): BelongsTo
    {
        return $this->belongsTo(FundingIntent::class);
    }

    public function observation(): BelongsTo
    {
        return $this->belongsTo(
            ProviderFundingObservation::class,
            'provider_funding_observation_id',
        );
    }
}
