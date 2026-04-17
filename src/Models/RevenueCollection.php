<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Bavix\Wallet\Models\Transfer;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\Instruction\Models\InstructionItem;

class RevenueCollection extends Model
{
    protected $table = 'revenue_collections';

    protected $fillable = [
        'instruction_item_id',
        'collected_by_user_id',
        'destination_type',
        'destination_id',
        'amount',
        'transfer_uuid',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function instructionItem(): BelongsTo
    {
        return $this->belongsTo(
            config('x-change.revenue.instruction_item_model', InstructionItem::class),
            'instruction_item_id'
        );
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(
            config('x-change.lifecycle.defaults.user_model', \App\Models\User::class),
            'collected_by_user_id'
        );
    }

    public function destination(): MorphTo
    {
        return $this->morphTo();
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'transfer_uuid', 'uuid');
    }

    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => Money::ofMinor((int) $this->amount, 'PHP')->formatTo('en_PH')
        );
    }

    protected function amountFloat(): Attribute
    {
        return Attribute::make(
            get: fn () => ((int) $this->amount) / 100
        );
    }

    protected function destinationName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $destination = $this->destination;

                if (! $destination) {
                    return 'Unknown';
                }

                return match (true) {
                    isset($destination->name) && filled($destination->name) => (string) $destination->name,
                    isset($destination->email) && filled($destination->email) => (string) $destination->email,
                    method_exists($destination, 'getName') => (string) $destination->getName(),
                    default => class_basename($destination).' #'.$destination->getKey(),
                };
            }
        );
    }
}
