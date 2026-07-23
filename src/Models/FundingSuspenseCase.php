<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\EmiCore\Models\WebhookReceipt;

class FundingSuspenseCase extends Model
{
    protected $table = 'x_change_funding_suspense_cases';

    protected $fillable = [
        'reference',
        'case_key',
        'funding_intent_id',
        'provider_funding_observation_id',
        'webhook_receipt_id',
        'provider_code',
        'reason_code',
        'status',
        'details',
        'opened_at',
        'resolved_at',
        'resolved_by_type',
        'resolved_by_id',
        'resolution_code',
        'resolution',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $case): void {
            $case->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new \LogicException('Funding suspense cases must be changed through guarded review actions.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Funding suspense cases cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'opened_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'resolution' => 'array',
        ];
    }

    public function fundingIntent(): BelongsTo
    {
        return $this->belongsTo(FundingIntent::class);
    }

    public function providerFundingObservation(): BelongsTo
    {
        return $this->belongsTo(ProviderFundingObservation::class);
    }

    public function webhookReceipt(): BelongsTo
    {
        return $this->belongsTo(WebhookReceipt::class);
    }
}
