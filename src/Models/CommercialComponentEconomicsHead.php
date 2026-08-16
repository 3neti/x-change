<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommercialComponentEconomicsHead extends Model
{
    protected $table = 'x_change_commercial_component_economics_heads';

    protected $primaryKey = 'profile';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['profile', 'current_activation_id'];

    protected function casts(): array
    {
        return ['current_activation_id' => 'integer'];
    }

    public function currentActivation(): BelongsTo
    {
        return $this->belongsTo(CommercialComponentEconomicsActivation::class, 'current_activation_id');
    }
}
