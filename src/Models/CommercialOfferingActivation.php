<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;

final class CommercialOfferingActivation extends Model
{
    protected $table = 'x_change_commercial_offering_activations';

    protected $fillable = [
        'profile',
        'commercial_offering_id',
        'offering_reference',
        'offering_version',
        'snapshot_hash',
        'origin',
        'authority',
        'activation_reference',
        'source_package',
        'source_package_version',
        'activated_at',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'commercial_offering_id' => 'integer',
            'offering_version' => 'integer',
            'origin' => CommercialOfferingOrigin::class,
            'authority' => CommercialActivationAuthority::class,
            'activated_at' => 'immutable_datetime',
            'deactivated_at' => 'immutable_datetime',
        ];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CommercialOffering::class, 'commercial_offering_id');
    }
}
