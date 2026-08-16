<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;

final class CommercialComponentEconomicsActivation extends Model
{
    protected $table = 'x_change_commercial_component_economics_activations';

    protected $fillable = [
        'profile', 'commercial_component_economics_id', 'previous_activation_id',
        'authority', 'activation_reference', 'authorization_reference',
        'actor_type', 'actor_id', 'source_package', 'source_package_version', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'commercial_component_economics_id' => 'integer',
            'previous_activation_id' => 'integer',
            'authority' => CommercialActivationAuthority::class,
            'activated_at' => 'immutable_datetime',
        ];
    }

    public function economics(): BelongsTo
    {
        return $this->belongsTo(CommercialComponentEconomics::class, 'commercial_component_economics_id');
    }

    public function previousActivation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_activation_id');
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
