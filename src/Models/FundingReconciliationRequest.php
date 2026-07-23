<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\FundingReconciliationAction;

class FundingReconciliationRequest extends Model
{
    protected $table = 'x_change_funding_reconciliation_requests';

    protected $fillable = [
        'reference',
        'request_key',
        'funding_suspense_case_id',
        'action',
        'status',
        'payload',
        'requested_by_type',
        'requested_by_id',
        'requested_at',
        'approved_by_type',
        'approved_by_id',
        'approved_at',
        'executed_at',
        'result',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new \LogicException('Funding reconciliation requests must be changed through guarded approvals.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Funding reconciliation requests cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'action' => FundingReconciliationAction::class,
            'payload' => 'array',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
            'result' => 'array',
        ];
    }

    public function suspenseCase(): BelongsTo
    {
        return $this->belongsTo(FundingSuspenseCase::class, 'funding_suspense_case_id');
    }
}
