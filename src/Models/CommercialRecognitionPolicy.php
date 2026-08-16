<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CommercialRecognitionPolicy extends Model
{
    protected $table = 'x_change_commercial_recognition_policies';

    protected $fillable = [
        'reference',
        'version',
        'trigger',
        'timing',
        'snapshot_hash',
        'snapshot',
    ];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new \LogicException('Commercial Recognition Policies are immutable.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Commercial Recognition Policies cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function billableEvents(): HasMany
    {
        return $this->hasMany(CommercialBillableEvent::class, 'commercial_recognition_policy_id');
    }
}
